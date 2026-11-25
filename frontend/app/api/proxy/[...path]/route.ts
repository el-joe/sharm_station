import type { NextRequest } from "next/server";
import { API_BASE, csrfFailure, json, readSession, unreachable, upstreamHeaders, upstreamSignal, writeSession } from "@/lib/server/session";

/**
 * /api/proxy/<api path>: authenticated calls from the browser to the Laravel API v1.
 *
 * The Sanctum token is read from the httpOnly `ssv_session` cookie and sent as a Bearer header
 * server-side (API_INTERNAL_URL), so browser JavaScript never sees it. Method, query string, body
 * (JSON or multipart, streamed) and the locale / currency / nationality headers are forwarded; the
 * answer is streamed back (JSON, or files such as statement exports and voucher PDFs with their
 * Content-Type / Content-Disposition). Public catalogue reads keep calling the API directly.
 *
 * Only API paths that need the user's token are allowed (ALLOW below); anything else is 404.
 * Unsafe methods need `x-ssv-csrf: 1` and a same-origin Origin/Referer (lib/server/session.ts).
 * A 401 from the API clears the session cookies (token expired or revoked).
 */

const ALLOW: RegExp[] = [
  /^me(\/|$)/, // account: bookings, profile, password
  /^partner\//, // partner panel incl. statement exports
  /^bookings(\/|$)/, // owner / partner access to a booking, payments, proofs, cancellation, vouchers
  /^auth\/(?!login$|register$)/, // me, logout, future auth steps (login/register go through /api/auth/*)
  /^coupons\/validate$/, // partner coupon checks (for_partner) and per-customer limits
  /^partners\/apply$/, // creates the partner user: the token it returns is stripped (below)
];

/** Answers that contain a freshly issued token the browser must not receive. */
const TOKEN_ISSUING = /^partners\/apply$/;

const PASS_RESPONSE_HEADERS = ["content-type", "content-disposition", "retry-after", "x-ratelimit-limit", "x-ratelimit-remaining", "etag", "last-modified", "location"];
const SEGMENT = /^[A-Za-z0-9._~@:-]+$/;
const TIMEOUT_MS = 60_000;

async function forward(request: NextRequest, ctx: { params: Promise<{ path: string[] }> }) {
  const { path: segments } = await ctx.params;
  if (!segments.length || segments.some((s) => s === "." || s === ".." || !SEGMENT.test(s))) return json({ message: "Not found." }, 404);
  const path = segments.join("/");
  if (!ALLOW.some((rule) => rule.test(path))) return json({ message: "Not found." }, 404);

  const refused = csrfFailure(request);
  if (refused) return refused;

  const method = request.method.toUpperCase();
  const token = readSession(request);
  const target = `${API_BASE}/${segments.map(encodeURIComponent).join("/")}${request.nextUrl.search}`;
  const hasBody = method !== "GET" && method !== "HEAD" && request.body !== null;

  let upstream: Response;
  try {
    upstream = await fetch(target, {
      method,
      headers: upstreamHeaders(request, token),
      body: hasBody ? request.body : undefined,
      // Streams the request body (multipart uploads) instead of buffering it.
      ...(hasBody ? { duplex: "half" } : {}),
      redirect: "manual",
      signal: upstreamSignal(request, TIMEOUT_MS),
      cache: "no-store",
    } as RequestInit);
  } catch (error) {
    return unreachable(error);
  }

  const headers = new Headers({ "Cache-Control": "private, no-store", "X-Content-Type-Options": "nosniff" });
  for (const name of PASS_RESPONSE_HEADERS) {
    const value = upstream.headers.get(name);
    if (value) headers.set(name, value);
  }
  // fetch() already decoded a compressed body, so its length no longer matches; keep it only when plain.
  const length = upstream.headers.get("content-length");
  if (length && !upstream.headers.get("content-encoding")) headers.set("Content-Length", length);
  if (upstream.status === 401 && token) writeSession(headers, request, null);

  if (TOKEN_ISSUING.test(path) && upstream.ok && upstream.headers.get("content-type")?.includes("json")) {
    const payload = (await upstream.json().catch(() => ({}))) as Record<string, unknown>;
    const { token: issued, ...rest } = payload;
    // The applicant is not signed in by the application (an admin approves the partner first):
    // revoke the unused token instead of leaving it valid.
    if (typeof issued === "string" && issued) {
      void fetch(`${API_BASE}/auth/logout`, { method: "POST", headers: { Accept: "application/json", Authorization: `Bearer ${issued}` }, cache: "no-store" }).catch(() => {});
    }
    headers.delete("Content-Length");
    return json(rest, upstream.status, headers);
  }

  return new Response(method === "HEAD" || upstream.status === 204 || upstream.status === 304 ? null : upstream.body, { status: upstream.status, headers });
}

export const GET = forward;
export const HEAD = forward;
export const POST = forward;
export const PUT = forward;
export const PATCH = forward;
export const DELETE = forward;
