import type { NextRequest } from "next/server";
import { PUBLIC_API_URL, USE_MOCKS } from "../site";
import { SESSION_COOKIE, SESSION_HINT_COOKIE, CSRF_HEADER } from "../session-shared";

/**
 * Server side of the cookie session (F6). The Sanctum personal access token never reaches
 * browser JavaScript: the /api/auth/* handlers put it in the httpOnly cookie `ssv_session`
 * and the /api/proxy/* handler adds it as a Bearer header when it forwards to Laravel.
 *
 *  ssv_session    httpOnly; SameSite=Lax; Secure on https; Path=/; 30 days  -> the token
 *  ssv_signed_in  readable by JS (value "1", same flags otherwise)            -> only a hint, so the
 *                 client knows whether to ask /api/auth/me (no request at all for anonymous visitors)
 *
 * CSRF: every unsafe method needs the custom header `x-ssv-csrf: 1` (a cross-site form or
 * <img> cannot send it, and a cross-site fetch with it needs a CORS preflight we never answer)
 * and an Origin (or, without Origin, a Referer) on this site's host.
 */

export const API_BASE = (process.env.API_INTERNAL_URL ?? PUBLIC_API_URL).replace(/\/$/, "");
const MAX_AGE = 60 * 60 * 24 * 30;

export const noStore = { "Cache-Control": "private, no-store" } as const;

export function json(body: unknown, status = 200, init?: HeadersInit) {
  const headers = new Headers(init);
  headers.set("Cache-Control", noStore["Cache-Control"]);
  return Response.json(body, { status, headers });
}

function isHttps(request: NextRequest) {
  const forwarded = request.headers.get("x-forwarded-proto")?.split(",")[0]?.trim();
  return (forwarded ?? request.nextUrl.protocol.replace(":", "")) === "https";
}

function cookieString(name: string, value: string, maxAge: number, httpOnly: boolean, secure: boolean) {
  return [
    `${name}=${encodeURIComponent(value)}`,
    "Path=/",
    `Max-Age=${maxAge}`,
    "SameSite=Lax",
    httpOnly ? "HttpOnly" : "",
    secure ? "Secure" : "",
  ]
    .filter(Boolean)
    .join("; ");
}

/** Appends the Set-Cookie headers that store the token (or clear it with `null`). */
export function writeSession(headers: Headers, request: NextRequest, token: string | null) {
  const secure = isHttps(request);
  const age = token ? MAX_AGE : 0;
  headers.append("Set-Cookie", cookieString(SESSION_COOKIE, token ?? "", age, true, secure));
  headers.append("Set-Cookie", cookieString(SESSION_HINT_COOKIE, token ? "1" : "", age, false, secure));
}

export function readSession(request: NextRequest): string | null {
  const value = request.cookies.get(SESSION_COOKIE)?.value;
  return value ? value : null;
}

function siteHost(request: NextRequest) {
  return (request.headers.get("x-forwarded-host") ?? request.headers.get("host") ?? request.nextUrl.host).split(",")[0].trim().toLowerCase();
}

/** null when the request passes the CSRF check, else the 403 response to return. */
export function csrfFailure(request: NextRequest): Response | null {
  const method = request.method.toUpperCase();
  if (method === "GET" || method === "HEAD" || method === "OPTIONS") return null;
  if (request.headers.get(CSRF_HEADER) !== "1") return json({ message: "Missing CSRF header." }, 403);
  const source = request.headers.get("origin") ?? request.headers.get("referer");
  let host: string | null = null;
  try {
    host = source && source !== "null" ? new URL(source).host.toLowerCase() : null;
  } catch {
    host = null;
  }
  if (!host || host !== siteHost(request)) return json({ message: "Cross-origin request refused." }, 403);
  return null;
}

/** Headers forwarded to Laravel (locale, currency, nationality, content type, client IP). */
export function upstreamHeaders(request: NextRequest, token: string | null, extra?: Record<string, string>) {
  const headers = new Headers();
  for (const name of ["accept", "accept-language", "content-type", "x-locale", "x-currency", "x-nationality", "user-agent"]) {
    const value = request.headers.get(name);
    if (value) headers.set(name, value);
  }
  if (!headers.has("accept")) headers.set("accept", "application/json");
  // Laravel only honours these behind TrustProxies; without it every login is throttled per Next server IP.
  const forwardedFor = request.headers.get("x-forwarded-for") ?? request.headers.get("x-real-ip");
  if (forwardedFor) headers.set("x-forwarded-for", forwardedFor);
  if (token) headers.set("authorization", `Bearer ${token}`);
  for (const [k, v] of Object.entries(extra ?? {})) headers.set(k, v);
  return headers;
}

export function upstreamSignal(request: NextRequest, ms: number) {
  return AbortSignal.any([request.signal, AbortSignal.timeout(ms)]);
}

/** Sanctum token check: GET /auth/me with the given token. */
export async function fetchMe(request: NextRequest, token: string): Promise<Response> {
  if (USE_MOCKS || token === "demo-token") return json({ data: DEMO_USER });
  const search = new URLSearchParams({ locale: request.nextUrl.searchParams.get("locale") ?? "en" });
  return fetch(`${API_BASE}/auth/me?${search}`, {
    headers: upstreamHeaders(request, token, { accept: "application/json" }),
    signal: upstreamSignal(request, 8000),
    cache: "no-store",
  });
}

export const DEMO_USER = { id: 1, name: "Demo traveller", email: "demo@example.com", role: "customer", partner: null };

/** Response for an API that could not be reached. */
export function unreachable(error: unknown) {
  const aborted = error instanceof DOMException && (error.name === "TimeoutError" || error.name === "AbortError");
  return json({ message: aborted ? "The API did not answer in time." : "The API is unreachable." }, aborted ? 504 : 502);
}
