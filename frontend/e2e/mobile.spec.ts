import { expect, test } from "./fixtures";
import { ADMIN_URL, authFile, horizontalOverflow, setNationality } from "./helpers";

/** 390 px checks (mobile-chromium project only): gate bottom sheet, header drawer, partner panel, admin drawer. */

test("nationality gate is a bottom sheet", async ({ page }) => {
  await page.goto("/en");
  const gate = page.getByRole("dialog", { name: "Where are you from?" });
  await expect(gate).toBeVisible();
  await page.waitForTimeout(400); // sheet-up animation
  const box = (await gate.boundingBox())!;
  const viewport = page.viewportSize()!;
  expect(Math.round(box.x)).toBe(0);
  expect(Math.round(box.width)).toBe(viewport.width);
  expect(Math.abs(box.y + box.height - viewport.height)).toBeLessThanOrEqual(2);
  await expect(gate.getByRole("button", { name: "Continue" })).toBeVisible();
  await page.keyboard.press("Escape");
  await expect(gate).toBeVisible();
});

test("cookie banner: Essential only hides it", async ({ browser }) => {
  const context = await browser.newContext({ viewport: { width: 390, height: 844 }, isMobile: true, hasTouch: true });
  await setNationality(context, "IT");
  const page = await context.newPage();
  await page.goto("/en");
  const banner = page.getByRole("region", { name: "Cookies on this site" });
  await expect(banner).toBeVisible();
  await banner.getByRole("button", { name: "Essential only" }).click();
  await expect(banner).toBeHidden();
  expect(await page.evaluate(() => localStorage.getItem("ssv_cookie_consent"))).toBe("essential");
  await context.close();
});

test("header drawer has the nationality chip and language/currency pills", async ({ page, context }) => {
  await setNationality(context, "IT");
  await page.goto("/en");
  await page.getByRole("banner").getByRole("button", { name: "Open menu" }).click();
  const drawer = page.getByRole("dialog", { name: "Menu" });
  await expect(drawer).toBeVisible();
  await expect(drawer.getByRole("navigation", { name: "Main" }).getByRole("link", { name: "Trips" })).toBeVisible();
  const chip = drawer.getByRole("button", { name: /^Nationality: Italy/ });
  await expect(chip).toBeVisible();
  await expect(drawer.getByRole("combobox", { name: "Language" })).toBeVisible();
  await expect(drawer.getByRole("combobox", { name: "Currency" })).toBeVisible();

  // The chip opens the picker as a sheet; change to Egypt.
  await chip.click();
  const picker = page.getByRole("dialog", { name: "Your nationality" });
  await expect(picker).toBeVisible();
  const box = picker.getByRole("combobox", { name: "Nationality" });
  await box.click();
  await box.fill("Egypt");
  await page.getByRole("option", { name: /Egypt$/ }).first().click();
  await picker.getByRole("button", { name: "Apply" }).click();
  await expect(picker).toBeHidden();
  expect((await context.cookies()).find((c) => c.name === "ssv_nationality")?.value).toBe("EG");
  expect(await horizontalOverflow(page)).toBeLessThanOrEqual(0);
});

test.describe("partner panel at 390 px", () => {
  test.use({ storageState: authFile("partner") });

  for (const path of ["/en/partner", "/en/partner/new", "/en/partner/bookings", "/en/partner/statements", "/en/partner/price-list", "/en/partner/profile"]) {
    test(`no horizontal scroll on ${path}`, async ({ page }) => {
      await page.goto(path);
      await expect(page.getByRole("heading", { level: 1 })).toBeVisible();
      // Not "networkidle": on mobile the header's favourites link triggers a Next.js segment prefetch
      // that the router abandons, which Playwright never sees finish. The page itself is fully loaded.
      await page.waitForLoadState("load");
      await expect(page.getByRole("dialog", { name: "Where are you from?" })).toBeHidden();
      expect(await horizontalOverflow(page), `overflow on ${path}`).toBeLessThanOrEqual(0);
    });
  }
});

test.describe("admin at 390 px", () => {
  test("login page fits", async ({ browser }) => {
    const context = await browser.newContext({ viewport: { width: 390, height: 844 }, isMobile: true, hasTouch: true, storageState: { cookies: [], origins: [] } });
    const page = await context.newPage();
    await page.goto(`${ADMIN_URL}/login`);
    await expect(page.getByRole("heading", { name: "Sign in" })).toBeVisible();
    await expect(page.getByLabel("Email")).toBeVisible();
    expect(await horizontalOverflow(page)).toBeLessThanOrEqual(0);
    await context.close();
  });

  test.describe("signed in", () => {
    test.use({ storageState: authFile("admin") });

    test("dashboard with the navigation drawer", async ({ page }) => {
      await page.goto(`${ADMIN_URL}/dashboard`);
      await expect(page.getByRole("heading", { name: "Overview", level: 1 })).toBeVisible();
      expect(await horizontalOverflow(page)).toBeLessThanOrEqual(0);
      const nav = page.getByRole("complementary", { name: "Main navigation" });
      const right = async () => {
        const box = await nav.boundingBox();
        return box ? box.x + box.width : 0;
      };
      await expect.poll(right).toBeLessThanOrEqual(0); // off-canvas
      await page.getByRole("banner").getByRole("button", { name: "Open menu" }).click();
      await expect.poll(right).toBeGreaterThan(200);
      // v2.3: "Overview" is a group (Dashboard + Reports), open on the dashboard.
      await expect(nav.getByRole("button", { name: "Overview" })).toBeVisible();
      await expect(nav.getByRole("link", { name: "Dashboard" })).toBeVisible();
      await page.keyboard.press("Escape");
      await expect.poll(right).toBeLessThanOrEqual(0);
      await expect(page.getByRole("banner").getByRole("button", { name: "Open menu" })).toBeFocused();
    });
  });
});
