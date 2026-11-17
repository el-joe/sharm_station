import type { Page } from "@playwright/test";
import { expect, test } from "./fixtures";
import { API_ORIGIN, BUGGY, authFile, money, setNationality } from "./helpers";

/** CR-10 / D8: mandatory first-visit nationality modal, header chip, price change per group. */

const gate = (page: Page) => page.getByRole("dialog", { name: "Where are you from?" });

/** Simulate Cloudflare's country header on the API host (the API reports it as `detected`). */
async function fakeCountryHeader(page: Page, country: string) {
  await page.route(`${API_ORIGIN}/**`, (route) => route.continue({ headers: { ...route.request().headers(), "cf-ipcountry": country } }));
}

async function pickCountry(page: Page, scope: ReturnType<typeof gate>, name: string) {
  const box = scope.getByRole("combobox", { name: "Nationality" });
  await box.click();
  await box.fill(name);
  // Wait until the filtered country list shows the option (on a cold first load it arrives a moment later).
  const option = page.getByRole("option", { name: new RegExp(`^\\S*\\s*${name}$`) }).first();
  await expect(option).toBeVisible({ timeout: 15_000 });
  await option.click();
  await expect(box).toHaveValue(name);
  // The typed text alone fills the box, so also confirm the selection took effect:
  // "Continue" in the first-visit gate, "Apply" in the change-nationality dialog.
  await expect(scope.getByRole("button", { name: /^(Continue|Apply)$/ })).toBeEnabled();
}

test.describe("Nationality gate", () => {
  test("first visit shows a mandatory modal with the detected country preselected", async ({ page }) => {
    await fakeCountryHeader(page, "IT");
    await page.goto("/en");
    const dialog = gate(page);
    await expect(dialog).toBeVisible();
    await expect(dialog.getByRole("combobox", { name: "Nationality" })).toHaveValue("Italy");
    await expect(dialog.getByText("Detected from your location")).toBeVisible();
    // No close button in the mandatory mode.
    await expect(dialog.getByRole("button", { name: "Close" })).toHaveCount(0);

    // Esc does not close it (twice: some browsers force-close a modal on the second Esc).
    await page.keyboard.press("Escape");
    await page.keyboard.press("Escape");
    await expect(dialog).toBeVisible();

    // The page behind is locked: scroll is disabled and content is inert.
    expect(await page.evaluate(() => getComputedStyle(document.documentElement).overflow)).toBe("hidden");
    await page.mouse.wheel(0, 1500);
    await page.waitForTimeout(300);
    expect(await page.evaluate(() => window.scrollY)).toBe(0);
    const headerLink = page.getByRole("banner").getByRole("link", { name: "Trips" }).first();
    await expect(headerLink).toBeAttached();
    await headerLink.click({ force: true, timeout: 3000 }).catch(() => {});
    await expect(page).toHaveURL(/\/en\/?$/);
    await expect(dialog).toBeVisible();
  });

  test("without a detected country the browser language region is the fallback", async ({ browser }) => {
    const context = await browser.newContext({ locale: "it-IT" });
    const page = await context.newPage();
    await page.goto("/en");
    const dialog = gate(page);
    await expect(dialog).toBeVisible();
    await expect(dialog.getByRole("combobox", { name: "Nationality" })).toHaveValue("Italy");
    await expect(dialog.getByText("Suggested from your browser language")).toBeVisible();
    await context.close();
  });

  test("choosing Egypt stores the cookie and shows the header chip; changing to Italy changes the buggy price", async ({ page, context, isMobile }) => {
    test.skip(isMobile, "the header chip lives in the drawer on mobile (covered in mobile.spec)");
    await page.goto(`/en/trips/${BUGGY.slug}`);
    const dialog = gate(page);
    await expect(dialog).toBeVisible();
    await pickCountry(page, dialog, "Egypt");
    await dialog.getByRole("button", { name: "Continue" }).click();
    await expect(dialog).toBeHidden();

    const cookie = (await context.cookies()).find((c) => c.name === "ssv_nationality");
    expect(cookie?.value).toBe("EG");

    const chip = page.getByRole("button", { name: /^Nationality: Egypt/ });
    await expect(chip).toBeVisible();
    // Prices re-render after the choice (router.refresh); reload to read the settled EG price.
    await page.reload();
    await expect(chip).toBeVisible();

    const widget = page.getByRole("complementary", { name: "Booking" });
    // Unit cards list "Single buggy" first: "€24 per buggy" (the "From €18 per buggy" teaser does not start with the amount).
    const singlePrice = widget.getByText(/^\D{0,4}[\d.,]+\s?\D{0,4} per buggy$/).first();
    await expect(widget.getByRole("group", { name: `Quantity of ${BUGGY.single}` })).toBeVisible();
    const egPrice = money((await singlePrice.textContent()) ?? "");

    await chip.click();
    const change = page.getByRole("dialog", { name: "Your nationality" });
    await expect(change).toBeVisible();
    await pickCountry(page, change, "Italy");
    await change.getByRole("button", { name: "Apply" }).click();
    await expect(change).toBeHidden();
    await expect(page.getByRole("button", { name: /^Nationality: Italy/ })).toBeVisible();
    expect((await context.cookies()).find((c) => c.name === "ssv_nationality")?.value).toBe("IT");

    await expect.poll(async () => money((await singlePrice.textContent()) ?? ""), { timeout: 15_000 }).not.toBe(egPrice);
    const itPrice = money((await singlePrice.textContent()) ?? "");
    // Seeded data: the Egyptian group is cheaper than Europe (PLAN §10.2).
    expect(egPrice).toBeLessThan(itPrice);
  });

  test("the change dialog can be dismissed with Esc", async ({ page, context, isMobile }) => {
    test.skip(isMobile, "desktop header only");
    await setNationality(context, "EG");
    await page.goto("/en");
    await expect(gate(page)).toHaveCount(0);
    await page.getByRole("button", { name: /^Nationality: Egypt/ }).click();
    const change = page.getByRole("dialog", { name: "Your nationality" });
    await expect(change).toBeVisible();
    await page.keyboard.press("Escape");
    await expect(change).toBeHidden();
  });

  test("the gate is not shown in the partner panel", async ({ browser }) => {
    // Signed-in partner, no nationality cookie: the panel never asks (the client's nationality is chosen per booking).
    const context = await browser.newContext({ storageState: authFile("partner") });
    await context.clearCookies();
    const page = await context.newPage();
    await page.goto("/en/partner");
    await expect(page.getByRole("heading", { level: 1 })).toBeVisible();
    await page.waitForTimeout(1500);
    await expect(gate(page)).toBeHidden();
    await context.close();
  });

  for (const path of ["/en/sign-in?returnTo=/partner", "/en/sign-up", "/en/become-a-partner"]) {
    test(`the gate is not shown on ${path}`, async ({ page }) => {
      await page.goto(path);
      await expect(page.getByRole("heading", { level: 1 })).toBeVisible();
      await page.waitForTimeout(1500);
      await expect(gate(page)).toBeHidden();
      if (path.includes("returnTo")) await expect(page).toHaveURL(/returnTo=\/partner/);
    });
  }

  test("an anonymous visit to the partner panel redirects to sign-in", async ({ page }) => {
    await page.goto("/en/partner");
    await expect(page).toHaveURL(/\/en\/sign-in\?returnTo=/);
  });
});
