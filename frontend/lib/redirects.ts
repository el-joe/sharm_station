/**
 * Former slugs (SRS 15.3): when a trip, package, building or blog post is renamed the API keeps the old slug and
 *   GET /redirects/resolve?path=/it/nile/old-slug -> 200 {data: {type, old_slug, slug, path, status: 301}} | 404
 * (detail endpoints also answer their 404 with the same object as `redirect_to`).
 *
 * Used in two places, both server-side:
 *  - proxy.ts, for full page loads of /{locale}/{trips|nile|packages|buildings|blog}/{slug}: answers a real 301
 *    before rendering starts, so crawlers and old links get a permanent redirect (the detail pages stream inside
 *    <Suspense>, where a redirect can only be a client-side one);
 *  - the detail pages' not-found path (resolveMovedPath + permanentRedirect), which also covers client-side
 *    navigations and any request the proxy skipped.
 */
import { API_URL, USE_MOCKS } from "./site";

export const REDIRECT_SEGMENTS = ["trips", "nile", "packages", "buildings", "blog"] as const;
export type RedirectSegment = (typeof REDIRECT_SEGMENTS)[number];

export interface SlugRedirect {
  type: string;
  old_slug: string;
  slug: string;
  path: string;
  status: number;
}

/** /en/trips/old-slug -> { locale, segment, slug }; null for any other path. */
export function matchDetailPath(pathname: string): { locale: string; segment: RedirectSegment; slug: string } | null {
  const m = /^\/(en|it)\/(trips|nile|packages|buildings|blog)\/([^/]+)\/?$/.exec(pathname);
  if (!m) return null;
  let slug: string;
  try {
    slug = decodeURIComponent(m[3]);
  } catch {
    return null;
  }
  if (!slug || slug.length > 200) return null;
  return { locale: m[1], segment: m[2] as RedirectSegment, slug };
}

/** Only same-site, locale-prefixed paths are followed. */
function safePath(path: unknown): string | null {
  return typeof path === "string" && /^\/(en|it)\/[^/]/.test(path) && !path.startsWith("//") && !path.includes("\\") ? path : null;
}

/** The detail endpoint behind a website segment (Nile cruises are trips). */
const DETAIL_ENDPOINT: Record<RedirectSegment, string> = { trips: "trips", nile: "trips", packages: "packages", buildings: "buildings", blog: "blog" };

/**
 * New path of a renamed detail page, or null (not moved, API unreachable, rate limited).
 * With `verify`, a hit is confirmed by the detail endpoint answering 404 for the old slug, so a slug that was
 * later reused by another product is never redirected away.
 */
export async function fetchMovedPath(segment: RedirectSegment, slug: string, locale: string, opts: { verify?: boolean; timeoutMs?: number } = {}): Promise<SlugRedirect | null> {
  if (USE_MOCKS) return null;
  const timeout = opts.timeoutMs ?? 2500;
  const headers = { Accept: "application/json", "Accept-Language": locale };
  try {
    const query = new URLSearchParams({ path: `/${locale}/${segment}/${slug}` });
    const res = await fetch(`${API_URL}/redirects/resolve?${query}`, { headers, signal: AbortSignal.timeout(timeout), cache: "no-store" });
    if (!res.ok) return null;
    const data = ((await res.json()) as { data?: SlugRedirect })?.data;
    const path = safePath(data?.path);
    if (!data || !path) return null;
    if (opts.verify) {
      const detail = await fetch(`${API_URL}/${DETAIL_ENDPOINT[segment]}/${encodeURIComponent(slug)}?locale=${locale}`, { headers, signal: AbortSignal.timeout(timeout), cache: "no-store" });
      if (detail.status !== 404) return null;
    }
    return { ...data, path };
  } catch {
    return null;
  }
}
