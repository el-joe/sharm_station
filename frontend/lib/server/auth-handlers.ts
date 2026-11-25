import type { NextRequest } from "next/server";
import { USE_MOCKS } from "../site";
import { API_BASE, DEMO_USER, csrfFailure, fetchMe, json, readSession, unreachable, upstreamHeaders, upstreamSignal, writeSession } from "./session";

/**
 * POST /api/auth/login | /api/auth/register: forwards the JSON body to Laravel; on success the
 * `token` of the answer goes into the httpOnly session cookie and the browser gets `{ data: user }`
 * only. Any other answer (422, 401, 429, or a future step such as a two-factor challenge without
 * a token) is passed through unchanged.
 */
export async function issueSession(request: NextRequest, path: "auth/login" | "auth/register") {
  const refused = csrfFailure(request);
  if (refused) return refused;

  let body: Record<string, unknown>;
  try {
    body = (await request.json()) as Record<string, unknown>;
    if (!body || typeof body !== "object" || Array.isArray(body)) throw new Error("not an object");
  } catch {
    return json({ message: "Body must be a JSON object." }, 400);
  }

  if (USE_MOCKS) {
    const headers = new Headers();
    writeSession(headers, request, "demo-token");
    const email = String(body.email ?? DEMO_USER.email);
    return json({ data: { ...DEMO_USER, email, name: String(body.name ?? email.split("@")[0]) } }, 200, headers);
  }

  const locale = request.nextUrl.searchParams.get("locale") ?? "en";
  let upstream: Response;
  try {
    upstream = await fetch(`${API_BASE}/${path}?locale=${encodeURIComponent(locale)}`, {
      method: "POST",
      headers: upstreamHeaders(request, null, { accept: "application/json", "content-type": "application/json" }),
      body: JSON.stringify(body),
      signal: upstreamSignal(request, 15000),
      cache: "no-store",
    });
  } catch (error) {
    return unreachable(error);
  }

  const payload = (await upstream.json().catch(() => ({ message: upstream.statusText }))) as Record<string, unknown>;
  const headers = new Headers();
  const retryAfter = upstream.headers.get("retry-after");
  if (retryAfter) headers.set("Retry-After", retryAfter);
  if (upstream.ok && typeof payload.token === "string" && payload.token) {
    const { token, ...rest } = payload;
    writeSession(headers, request, token as string);
    return json(rest, upstream.status, headers);
  }
  return json(payload, upstream.status, headers);
}

/** POST /api/auth/logout: revokes the token (best effort) and clears both cookies. */
export async function endSession(request: NextRequest) {
  const refused = csrfFailure(request);
  if (refused) return refused;
  const token = readSession(request);
  if (token && !USE_MOCKS && token !== "demo-token") {
    try {
      await fetch(`${API_BASE}/auth/logout`, { method: "POST", headers: upstreamHeaders(request, token, { accept: "application/json" }), signal: upstreamSignal(request, 8000), cache: "no-store" });
    } catch {
      /* API down: the cookie is dropped anyway */
    }
  }
  const headers = new Headers({ "Cache-Control": "private, no-store" });
  writeSession(headers, request, null);
  return new Response(null, { status: 204, headers });
}

/** GET /api/auth/me: the signed-in user (401 and cleared cookies when the token is gone or revoked). */
export async function currentUser(request: NextRequest) {
  const token = readSession(request);
  const headers = new Headers();
  if (!token) {
    // Stale hint cookie without a session: clear it so the client stops asking.
    if (request.cookies.get("ssv_signed_in")) writeSession(headers, request, null);
    return json({ message: "Unauthenticated." }, 401, headers);
  }
  let upstream: Response;
  try {
    upstream = await fetchMe(request, token);
  } catch (error) {
    return unreachable(error);
  }
  if (upstream.status === 401) writeSession(headers, request, null);
  const payload = await upstream.json().catch(() => ({ message: upstream.statusText }));
  return json(payload, upstream.status, headers);
}

/**
 * POST /api/auth/migrate {token}: one-time exchange of a pre-F6 localStorage token for the cookie.
 * The token is checked with GET /auth/me first; an invalid one only gets a 401 (nothing is set).
 */
export async function migrateSession(request: NextRequest) {
  const refused = csrfFailure(request);
  if (refused) return refused;
  let token: unknown;
  try {
    token = ((await request.json()) as { token?: unknown })?.token;
  } catch {
    token = null;
  }
  if (typeof token !== "string" || !/^[\w|.-]{8,255}$/.test(token)) return json({ message: "Invalid token." }, 422);
  let upstream: Response;
  try {
    upstream = await fetchMe(request, token);
  } catch (error) {
    return unreachable(error);
  }
  const payload = await upstream.json().catch(() => ({ message: upstream.statusText }));
  if (!upstream.ok) return json(payload, upstream.status === 401 ? 401 : upstream.status);
  const headers = new Headers();
  writeSession(headers, request, token);
  return json(payload, 200, headers);
}
