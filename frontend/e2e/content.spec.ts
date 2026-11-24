import type { Page } from "@playwright/test";
import { expect, test } from "./fixtures";
import { ADMIN_URL, apiContext, authFile, setNationality } from "./helpers";

/** CMS (PLAN-v2.3 A4/W2): pages, blog and FAQ come from the admin through the API. */

async function jsonLd(page: Page): Promise<Record<string, unknown>[]> {
  const raw = await page.locator('script[type="application/ld+json"]').allTextContents();
  return raw.flatMap((r) => {
    const v = JSON.parse(r);
    return Array.isArray(v) ? v : v["@graph"] ?? [v];
  });
}

test.beforeEach(async ({ context }) => setNationality(context, "IT"));

test("blog index and article render the API posts with BlogPosting JSON-LD", async ({ page }) => {
  const api = await apiContext();
  const posts = (await (await api.get("blog")).json()).data as { slug: string; title: string; excerpt: string }[];
  await api.dispose();
  expect(posts.length).toBeGreaterThan(0);

  await page.goto("/en/blog");
  await expect(page.getByRole("heading", { level: 1 })).toBeVisible();
  for (const post of posts.slice(0, 3)) await expect(page.getByRole("main").getByRole("link", { name: post.title }).first()).toBeVisible();

  await page.goto(`/en/blog/${posts[0].slug}`);
  await expect(page.getByRole("heading", { level: 1 })).toHaveText(posts[0].title);
  await expect(page.getByRole("main").getByRole("heading", { level: 2 }).first()).toBeVisible();
  const ld = await jsonLd(page);
  const article = ld.find((n) => /BlogPosting|Article/.test(String(n["@type"])));
  expect(article, `JSON-LD types: ${ld.map((n) => n["@type"]).join(", ")}`).toBeTruthy();
  expect(article!.headline).toBe(posts[0].title);
});

test("FAQ renders the API questions with FAQPage JSON-LD", async ({ page }) => {
  const api = await apiContext();
  const groups = (await (await api.get("faqs")).json()).data as { category: string; items: { question: string }[] }[];
  await api.dispose();
  const questions = groups.flatMap((g) => g.items.map((i) => i.question));
  expect(questions.length).toBeGreaterThan(0);

  await page.goto("/en/faq");
  await expect(page.getByRole("heading", { level: 1 })).toBeVisible();
  for (const g of groups) await expect(page.getByRole("main").getByText(g.category, { exact: true }).first()).toBeVisible();
  await expect(page.getByRole("main").getByText(questions[0]).first()).toBeVisible();
  const faq = (await jsonLd(page)).find((n) => n["@type"] === "FAQPage") as { mainEntity: { name: string }[] } | undefined;
  expect(faq, "FAQPage JSON-LD").toBeTruthy();
  expect(faq!.mainEntity.map((q) => q.name)).toEqual(expect.arrayContaining(questions.slice(0, 3)));
});

test.describe("admin edits", () => {
  test.use({ storageState: authFile("admin") });

  test("About intro edited in the admin reaches the API and the website, then is reverted", async ({ page, browser }) => {
    test.setTimeout(10 * 60_000);
    const marker = ` E2E ${Date.now()}`;
    const intro = page.getByRole("textbox", { name: "Introduction (English)" });
    const save = async (value: string) => {
      await page.goto(`${ADMIN_URL}/content/pages/about`);
      await intro.fill(value);
      await page.getByRole("button", { name: "Save" }).first().click();
      await expect(page.getByRole("status").or(page.getByRole("alert")).filter({ hasText: /saved|updated/i }).first()).toBeVisible();
    };
    await page.goto(`${ADMIN_URL}/content/pages/about`);
    const original = await intro.inputValue();
    expect(original).not.toContain("E2E");

    const site = await browser.newContext({ storageState: { cookies: [], origins: [] } });
    await site.addInitScript(() => window.localStorage.setItem("ssv_cookie_consent", "essential"));
    await setNationality(site, "IT");
    const sitePage = await site.newPage();
    const api = await apiContext();
    const apiIntro = async () => (await (await api.get("pages/about", { headers: { "Accept-Language": "en" } })).json()).data.intro as string;
    const siteText = async () => {
      await sitePage.goto("/en/about");
      return sitePage.getByRole("main").innerText();
    };

    try {
      await save(original + marker);
      // The API is live immediately.
      expect(await apiIntro()).toContain(marker.trim());
      // The website caches content with cacheLife("minutes") (revalidate 60 s, no on-demand revalidation from the admin):
      // the first request after a minute triggers the background refresh, a later one serves it.
      await expect.poll(siteText, { message: "website shows the edited intro after the cache revalidates", timeout: 4 * 60_000, intervals: [15_000] }).toContain(marker.trim());
    } finally {
      await save(original);
      expect(await apiIntro()).toBe(original);
      await api.dispose();
    }
    await expect.poll(siteText, { message: "website back to the original intro", timeout: 4 * 60_000, intervals: [15_000] }).not.toContain(marker.trim());
    await site.close();
  });
});
