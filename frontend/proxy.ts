import createMiddleware from "next-intl/middleware";
import { NextResponse, type NextRequest } from "next/server";
import { routing } from "./i18n/routing";
import { fetchMovedPath, matchDetailPath } from "./lib/redirects";

// Next.js 16 renamed Middleware to Proxy. next-intl negotiates the locale
// (cookie, Accept-Language) and redirects "/" and unprefixed paths to /en or /it.
const intl = createMiddleware(routing);

/*
 * Former slugs (SRS 15.3, lib/redirects.ts): a full page load of /{locale}/{trips|nile|packages|buildings|blog}/{slug}
 * whose slug was renamed answers a permanent redirect here, before rendering, so crawlers get a real 301. Results are
 * memoised per path (moved: 5 min, not moved: 3 min) so a detail page costs at most one small API call every few minutes,
 * and the API being slow or down only skips the check (the page's own not-found path still redirects).
 * Requests recognisable as router prefetches are skipped; anything the proxy misses (memoised "not moved" right
 * after a rename, API timeout) still reaches the page-level fallback.
 */
const HIT_TTL = 5 * 60_000;
const MISS_TTL = 3 * 60_000;
const MEMO_MAX = 2000;
const memo = new Map<string, { path: string | null; status: number; until: number }>();

async function movedTo(pathname: string) {
  const match = matchDetailPath(pathname);
  if (!match) return null;
  const key = `${match.locale}/${match.segment}/${match.slug}`;
  const now = Date.now();
  const cached = memo.get(key);
  if (cached && cached.until > now) return cached.path ? cached : null;
  const moved = await fetchMovedPath(match.segment, match.slug, match.locale, { verify: true, timeoutMs: 1500 });
  if (memo.size >= MEMO_MAX) memo.delete(memo.keys().next().value!);
  const entry = { path: moved?.path ?? null, status: moved?.status === 308 ? 308 : 301, until: now + (moved ? HIT_TTL : MISS_TTL) };
  memo.set(key, entry);
  return entry.path ? entry : null;
}

function isPageLoad(request: NextRequest) {
  if (request.method !== "GET" && request.method !== "HEAD") return false;
  const h = request.headers;
  return !h.has("rsc") && !h.has("next-router-prefetch") && h.get("purpose") !== "prefetch" && h.get("sec-purpose")?.includes("prefetch") !== true;
}

export default async function proxy(request: NextRequest) {
  if (isPageLoad(request)) {
    const moved = await movedTo(request.nextUrl.pathname);
    if (moved?.path) {
      const target = request.nextUrl.clone();
      target.pathname = moved.path;
      return NextResponse.redirect(target, moved.status);
    }
  }
  return intl(request);
}

export const config = {
  // Skip API routes, Next internals, metadata routes and static files.
  matcher: ["/((?!api|_next|_vercel|sitemap.xml|robots.txt|.*\\..*).*)"],
};
