import type { Page, TestInfo } from "@playwright/test";
import { expect, test } from "./fixtures";
import { ADMIN_URL, SITE_URL, apiContext, authFile, readToken, setNationality } from "./helpers";
import { overflowPx, placeholderText, settle, urlPattern, watchPage, type PageWatch } from "./quality";

/**
 * Crawl (PLAN-v2.3 §5.3): every website, account, partner and admin page, desktop and mobile (390 px).
 * A page fails on: HTTP >= 400 (document or first-party request), first-party console errors, hydration
 * errors, JS dialogs (DataTables "alert" errors), placeholder text and, on the website at <= 430 px,
 * horizontal overflow. Every problem is collected and reported at the end of each test.
 */

const MAX_PUBLIC = Number(process.env.E2E_CRAWL_MAX ?? 250);
const PER_PATTERN = 2; // detail pages visited per dynamic pattern and locale
const SITE_ORIGIN = new URL(SITE_URL).origin;
const ADMIN_ORIGIN = new URL(ADMIN_URL).origin;

interface Visit {
  url: string;
  status: number;
  problems: string[];
}

async function inspect(page: Page, watch: PageWatch, url: string, opts: { overflow: boolean; expectStatus?: number }): Promise<Visit> {
  watch.reset();
  const problems: string[] = [];
  let status = 0;
  try {
    const res = await page.goto(url, { waitUntil: "domcontentloaded" });
    status = res?.status() ?? 0;
  } catch (err) {
    problems.push(`navigation failed: ${(err as Error).message.split("\n")[0]}`);
    return { url, status, problems };
  }
  await settle(page);
  const expected = opts.expectStatus ?? 200;
  if (opts.expectStatus ? status !== expected : status >= 400) problems.push(`HTTP ${status}`);
  if (opts.expectStatus === 404) watch.allow(/404/);
  const placeholder = await placeholderText(page);
  if (placeholder) problems.push(`placeholder text "${placeholder}"`);
  if (opts.overflow) {
    const overflow = await overflowPx(page);
    if (overflow > 0) problems.push(`horizontal overflow ${overflow}px`);
  }
  problems.push(...watch.hydration.map((e) => `hydration: ${e}`), ...watch.console.map((e) => `console: ${e}`), ...watch.http.map((e) => `request: ${e}`), ...watch.dialogs.map((e) => `dialog: ${e}`));
  return { url, status, problems };
}

function report(testInfo: TestInfo, area: string, visits: Visit[]) {
  const failed = visits.filter((v) => v.problems.length);
  void testInfo.attach(`${area}-crawl.json`, { body: JSON.stringify(visits, null, 2), contentType: "application/json" });
  console.log(`[crawl] ${testInfo.project.name} ${area}: ${visits.length} pages, ${failed.length} with problems`);
  expect(
    failed.map((v) => `${v.url.replace(SITE_ORIGIN, "").replace(ADMIN_ORIGIN, "")} → ${v.problems.join(" | ")}`),
    `${area}: pages with problems (${visits.length} crawled)`,
  ).toEqual([]);
}

const isMobile = (testInfo: TestInfo) => (testInfo.project.use.viewport?.width ?? 1440) <= 430;

/** Links on the page that stay on the given origin. */
async function links(page: Page): Promise<string[]> {
  return page.$$eval("a[href]", (as) => as.filter((a) => !a.hasAttribute("download") && a.getAttribute("target") !== "_blank").map((a) => (a as HTMLAnchorElement).href));
}

test.describe("public website crawl", () => {
  test.beforeEach(async ({ context }) => setNationality(context, "IT"));

  test("EN + IT: breadth-first from the home pages plus explicit routes", async ({ page }, testInfo) => {
    test.setTimeout(30 * 60_000);
    const api = await apiContext();
    const first = async (path: string) => ((await (await api.get(path)).json()).data?.[0] ?? {}) as Record<string, unknown>;
    const [post, room, pkg, nile, trip, destination] = await Promise.all([first("blog"), first("rooms"), first("packages"), first("trips?type=nile"), first("trips"), first("destinations")]);
    await api.dispose();
    const building = (room.building as { slug?: string } | undefined)?.slug;

    const explicit = (l: string) =>
      [
        "",
        "/about",
        "/contact",
        "/faq",
        "/booking-terms",
        "/privacy",
        "/cancellation-policy",
        "/cookie-policy",
        "/destinations",
        `/destinations/${destination.slug ?? "luxor"}`,
        "/destinations/luxor",
        "/blog",
        post.slug ? `/blog/${post.slug}` : null,
        "/accommodation",
        "/accommodation?view=map",
        room.id ? `/rooms/${room.id}` : null,
        building ? `/buildings/${building}` : null,
        "/transfers",
        "/packages",
        pkg.slug ? `/packages/${pkg.slug}` : null,
        "/nile",
        nile.slug ? `/nile/${nile.slug}` : null,
        "/trips",
        trip.slug ? `/trips/${trip.slug}` : null,
        "/sign-in",
        "/sign-up",
        "/become-a-partner",
      ]
        .filter((p): p is string => p !== null)
        .map((p) => `${SITE_ORIGIN}/${l}${p}`);

    const queue: string[] = [...explicit("en"), ...explicit("it")];
    const seen = new Set<string>(queue);
    const perPattern = new Map<string, number>();
    const watch = watchPage(page);
    const visits: Visit[] = [];
    const overflow = isMobile(testInfo);

    const enqueue = (href: string) => {
      let u: URL;
      try {
        u = new URL(href);
      } catch {
        return;
      }
      if (u.origin !== SITE_ORIGIN || !/^\/(en|it)(\/|$)/.test(u.pathname)) return;
      // Signed-in areas, checkout and booking links (need state / tokens) are crawled by the signed-in tests.
      if (/^\/(en|it)\/(account|partner|checkout|booking)(\/|$)/.test(u.pathname)) return;
      if (/\.(pdf|jpe?g|png|webp|svg|xml|txt)$/i.test(u.pathname)) return;
      const key = u.pathname.replace(/\/$/, "") || u.pathname;
      const url = `${SITE_ORIGIN}${key}`;
      if (seen.has(url)) return;
      const pattern = urlPattern(key);
      if (pattern !== key) {
        const n = perPattern.get(pattern) ?? 0;
        if (n >= PER_PATTERN) return;
        perPattern.set(pattern, n + 1);
      }
      seen.add(url);
      queue.push(url);
    };

    while (queue.length && visits.length < MAX_PUBLIC) {
      const url = queue.shift()!;
      const visit = await inspect(page, watch, url, { overflow });
      visits.push(visit);
      if (visit.status < 400) for (const href of await links(page)) enqueue(href);
    }
    report(testInfo, "public", visits);
  });

  test("unknown page renders the 404 page", async ({ page }, testInfo) => {
    const watch = watchPage(page);
    const visit = await inspect(page, watch, `${SITE_ORIGIN}/en/e2e-crawl-missing-page`, { overflow: isMobile(testInfo), expectStatus: 404 });
    await expect(page.getByRole("heading", { level: 1 })).toBeVisible();
    report(testInfo, "404", [visit]);
  });
});

test.describe("customer account crawl", () => {
  test.use({ storageState: authFile("customer") });

  test("every /account page and a booking detail", async ({ page }, testInfo) => {
    test.setTimeout(5 * 60_000);
    const api = await apiContext(readToken("customer"));
    const bookings = ((await (await api.get("me/bookings")).json()).data ?? []) as { reference: string; access_token?: string }[];
    await api.dispose();
    const watch = watchPage(page);
    const urls = ["/en/account", "/en/account/bookings", "/en/account/favourites", "/en/account/profile", "/it/account", "/it/account/bookings"];
    if (bookings[0]) urls.push(`/en/account/bookings/${bookings[0].reference}`, `/en/booking/${bookings[0].reference}`);
    const visits: Visit[] = [];
    const seen = new Set(urls.map((u) => `${SITE_ORIGIN}${u}`));
    const queue = [...seen];
    while (queue.length) {
      const url = queue.shift()!;
      const visit = await inspect(page, watch, url, { overflow: isMobile(testInfo) });
      if (/\/sign-in/.test(page.url())) visit.problems.push(`redirected to sign-in (${page.url()})`);
      visits.push(visit);
      for (const href of await links(page)) {
        const u = new URL(href);
        if (u.origin !== SITE_ORIGIN || !/^\/en\/account(\/|$)/.test(u.pathname)) continue;
        const key = `${SITE_ORIGIN}${u.pathname}`;
        if (seen.has(key) || [...seen].some((s) => urlPattern(new URL(s).pathname) === urlPattern(u.pathname))) continue;
        seen.add(key);
        queue.push(key);
      }
    }
    expect(bookings.length, "the demo customer has bookings (booking.spec creates them)").toBeGreaterThan(0);
    report(testInfo, "account", visits);
  });
});

test.describe("partner panel crawl", () => {
  test.use({ storageState: authFile("partner") });

  test("every /partner page, new-booking steps, a booking detail and vouchers", async ({ page }, testInfo) => {
    test.setTimeout(5 * 60_000);
    const api = await apiContext(readToken("partner"));
    const bookings = ((await (await api.get("partner/bookings")).json()).data ?? []) as { reference: string }[];
    await api.dispose();
    const watch = watchPage(page);
    const ref = bookings[0]?.reference;
    const urls = [
      "/en/partner",
      "/en/partner/new",
      "/en/partner/bookings",
      "/en/partner/statements",
      "/en/partner/price-list",
      "/en/partner/profile",
      "/it/partner",
      "/it/partner/new",
      ...(ref ? [`/en/partner/bookings/${ref}`, `/en/partner/bookings/${ref}/voucher?prices=1`, `/en/partner/bookings/${ref}/voucher?prices=0`] : []),
    ];
    const visits: Visit[] = [];
    const overflow = isMobile(testInfo);
    for (const path of urls) {
      const visit = await inspect(page, watch, `${SITE_ORIGIN}${path}`, { overflow });
      if (/\/sign-in/.test(page.url())) visit.problems.push(`redirected to sign-in (${page.url()})`);
      visits.push(visit);
    }

    // The new-booking flow keeps its steps in client state (not in the URL): walk catalogue -> configure for every service tab.
    watch.reset();
    await page.goto(`${SITE_ORIGIN}/en/partner/new`);
    await expect(page.getByRole("heading", { name: "New booking", level: 1 })).toBeVisible();
    const select = page.getByRole("button", { name: /^Select / }).first();
    await expect(select).toBeVisible();
    await select.click();
    await expect(page.getByRole("heading", { level: 1 })).not.toHaveText("New booking");
    await settle(page);
    const step2: Visit = { url: `${SITE_ORIGIN}/en/partner/new#configure`, status: 200, problems: [] };
    const placeholder = await placeholderText(page);
    if (placeholder) step2.problems.push(`placeholder text "${placeholder}"`);
    if (overflow && (await overflowPx(page)) > 0) step2.problems.push("horizontal overflow");
    step2.problems.push(...watch.console, ...watch.hydration, ...watch.http);
    visits.push(step2);

    expect(ref, "the demo partner has bookings (partner-panel.spec creates them)").toBeTruthy();
    report(testInfo, "partner", visits);
  });
});

const ADMIN_SKIP = /\/(logout|export|data)(\/|$|\?)|\/files\/|\/receipts\/\d+\/files|format=|\.csv|\.pdf|\.xlsx/;

function adminPattern(href: string): string {
  const u = new URL(href);
  return `${urlPattern(u.pathname)}?${[...u.searchParams.keys()].sort().join("&")}`;
}

async function sidebarLinks(page: Page): Promise<{ label: string; href: string }[]> {
  return page
    .getByRole("complementary", { name: "Main navigation" })
    .locator("a[href]")
    .evaluateAll((as) => as.map((a) => ({ label: (a.textContent ?? "").trim(), href: (a as HTMLAnchorElement).href })));
}

test.describe("admin crawl", () => {
  test.beforeEach(({}, testInfo) => test.skip(isMobile(testInfo), "admin crawl runs on desktop; the 390 px admin shell is covered by mobile.spec"));

  test.describe("super admin", () => {
    test.use({ storageState: authFile("admin") });

    test("every sidebar link plus one level of in-page links", async ({ page }, testInfo) => {
      test.setTimeout(20 * 60_000);
      const watch = watchPage(page);
      await page.goto(`${ADMIN_URL}/dashboard`);
      await expect(page).toHaveURL(/\/admin\/dashboard/);
      const nav = await sidebarLinks(page);
      expect(nav.length).toBeGreaterThan(20);

      const visits: Visit[] = [];
      const seenPatterns = new Set<string>();
      const second: string[] = [];
      for (const { href } of nav) {
        seenPatterns.add(adminPattern(href));
      }
      for (const { href } of nav) {
        const visit = await inspect(page, watch, href, { overflow: false });
        if (/\/admin\/login/.test(page.url())) visit.problems.push("redirected to login");
        visits.push(visit);
        const inPage = await page.locator("main a[href]").evaluateAll((as) => as.filter((a) => !a.hasAttribute("download") && a.getAttribute("target") !== "_blank").map((a) => (a as HTMLAnchorElement).href));
        for (const link of inPage) {
          if (!link.startsWith(`${ADMIN_ORIGIN}/admin`) || ADMIN_SKIP.test(link)) continue;
          const pattern = adminPattern(link.split("#")[0]);
          if (seenPatterns.has(pattern)) continue;
          seenPatterns.add(pattern);
          second.push(link.split("#")[0]);
        }
      }
      for (const href of second.slice(0, 200)) {
        const visit = await inspect(page, watch, href, { overflow: false });
        visits.push(visit);
      }
      report(testInfo, "admin", visits);
    });
  });

  test.describe("editor", () => {
    test.use({ storageState: authFile("editor") });

    const FORBIDDEN: [string, string][] = [
      ["Bookings", "bookings"],
      ["Customers", "customers"],
      ["Coupons", "coupons"],
      ["Partners", "partners"],
      ["Payments", "payments"],
      ["Receipts review", "receipts"],
      ["Reports", "reports"],
      ["Commissions", "settings/commissions"],
      ["General", "settings"],
      ["MCP access", "settings/mcp"],
      ["Users and roles", "users"],
      ["Activity log", "activity-log"],
      ["Currencies", "currencies"],
      ["Payment methods", "payment-methods"],
      ["Nationality groups", "nationality-groups"],
      ["Contact messages", "contact-messages"],
      ["Requests", "transfers/requests"],
    ];

    test("sidebar hides the forbidden modules, they return 403, allowed pages work", async ({ page }, testInfo) => {
      test.setTimeout(10 * 60_000);
      const watch = watchPage(page);
      await page.goto(`${ADMIN_URL}/dashboard`);
      await expect(page).toHaveURL(/\/admin\/dashboard/);
      const nav = await sidebarLinks(page);
      const labels = nav.map((l) => l.label);
      const problems: string[] = [];
      for (const [label, path] of FORBIDDEN) {
        if (labels.includes(label)) problems.push(`sidebar shows "${label}"`);
        const res = await page.goto(`${ADMIN_URL}/${path}`);
        if (res?.status() !== 403) problems.push(`/${path} returned ${res?.status()} instead of 403`);
      }
      expect(problems, "editor restrictions").toEqual([]);

      const visits: Visit[] = [];
      for (const { href } of nav) visits.push(await inspect(page, watch, href, { overflow: false }));
      report(testInfo, "admin-editor", visits);
    });
  });
});
