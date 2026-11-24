import { createHash, timingSafeEqual } from "node:crypto";
import { revalidateTag } from "next/cache";
import type { NextRequest } from "next/server";

/**
 * POST /api/revalidate — on-demand revalidation called by the Laravel admin after a content change.
 *
 *   POST /api/revalidate
 *   x-revalidate-secret: <REVALIDATE_SECRET>
 *   Content-Type: application/json
 *   {"tags": ["content", "pages", "page:about", "blog", "blog:my-post", "faqs"]}
 *
 * Only the content tags set in lib/api/content.ts are accepted. 401 on a missing / wrong secret
 * (or when REVALIDATE_SECRET is not configured), 400 on a malformed body or an unknown tag.
 */

const FIXED_TAGS = new Set(["content", "pages", "blog", "faqs"]);
const SLUG_TAG = /^(page|blog):[a-z0-9][a-z0-9-]{0,199}$/;
const MAX_TAGS = 50;

/** Constant-time comparison (hashing first makes both buffers the same length). */
function sameSecret(given: string, expected: string): boolean {
  const a = createHash("sha256").update(given).digest();
  const b = createHash("sha256").update(expected).digest();
  return timingSafeEqual(a, b);
}

const json = (body: unknown, status: number) => Response.json(body, { status, headers: { "Cache-Control": "no-store" } });

export async function POST(request: NextRequest) {
  const expected = process.env.REVALIDATE_SECRET ?? "";
  const given = request.headers.get("x-revalidate-secret") ?? "";
  // Compare even when unconfigured so the timing does not reveal it; an empty secret never matches.
  const ok = sameSecret(given, expected) && expected.length > 0;
  if (!ok) return json({ revalidated: false, message: "Unauthorized" }, 401);

  let body: unknown;
  try {
    body = await request.json();
  } catch {
    return json({ revalidated: false, message: "Body must be JSON: {\"tags\": string[]}" }, 400);
  }
  const tags = (body as { tags?: unknown } | null)?.tags;
  if (!Array.isArray(tags) || tags.length === 0 || tags.length > MAX_TAGS || !tags.every((x) => typeof x === "string")) {
    return json({ revalidated: false, message: `"tags" must be a non-empty array of at most ${MAX_TAGS} strings` }, 400);
  }
  const unique = [...new Set(tags as string[])];
  const invalid = unique.filter((tag) => !FIXED_TAGS.has(tag) && !SLUG_TAG.test(tag));
  if (invalid.length) return json({ revalidated: false, message: "Unknown tags", invalid }, 400);

  // Editors expect their change on the next request: expire immediately instead of serving stale.
  for (const tag of unique) revalidateTag(tag, { expire: 0 });
  return json({ revalidated: true, tags: unique, now: Date.now() }, 200);
}
