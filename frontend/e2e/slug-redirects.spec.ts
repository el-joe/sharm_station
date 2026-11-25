import { expect, test } from "./fixtures";
import { tinker } from "./flows";
import { BUGGY, apiContext, setNationality } from "./helpers";

/**
 * Former slugs (SRS 15.3): a renamed trip keeps its old URL as a permanent redirect. The test gives the buggy trip a
 * throw-away slug and renames it straight back, which records "<throw-away> -> desert-buggy-safari" in slug_redirects
 * (the trip ends with its real slug, so cached pages and other specs are unaffected). The redirect row is removed after.
 */

test.beforeEach(async ({ context }) => setNationality(context, "IT"));

test("old trip URL answers a permanent redirect to the current one (HTTP and browser)", async ({ page, request }) => {
  const old = `${BUGGY.slug}-e2e-${Date.now()}`;
  const slug = JSON.stringify(BUGGY.slug);
  tinker(`$t = App\\Models\\Trip::where('slug', ${slug})->firstOrFail(); $t->update(['slug' => '${old}']); $t->update(['slug' => ${slug}]);`);
  try {
    const api = await apiContext();
    const resolved = await api.get("redirects/resolve", { params: { path: `/it/trips/${old}` } });
    expect(resolved.status()).toBe(200);
    expect((await resolved.json()).data).toMatchObject({ old_slug: old, slug: BUGGY.slug, path: `/it/trips/${BUGGY.slug}` });
    const detail = await api.get(`trips/${old}`);
    expect(detail.status()).toBe(404);
    expect((await detail.json()).redirect_to?.slug).toBe(BUGGY.slug);
    await api.dispose();

    // A real 301/308 before any HTML is rendered (crawlers), query string kept.
    const res = await request.get(`/it/trips/${old}?utm_source=e2e`, { maxRedirects: 0 });
    expect([301, 308]).toContain(res.status());
    expect(res.headers()["location"]).toMatch(new RegExp(`/it/trips/${BUGGY.slug}\\?utm_source=e2e$`));

    // Browser: lands on the current URL in the same locale.
    await page.goto(`/en/trips/${old}`);
    await expect(page).toHaveURL(new RegExp(`/en/trips/${BUGGY.slug}$`));
    await expect(page.getByRole("heading", { name: BUGGY.title, level: 1 })).toBeVisible();

    // Not a moved slug: still the 404 page.
    await page.goto(`/en/trips/${old}-unknown`);
    await expect(page).toHaveURL(new RegExp(`/en/trips/${old}-unknown$`));
    await expect(page.getByText("404", { exact: true })).toBeVisible();
  } finally {
    tinker(`App\\Models\\SlugRedirect::where('old_slug', '${old}')->delete();`);
  }
});
