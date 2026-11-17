import type { Page } from "@playwright/test";
import { expect, test } from "./fixtures";
import { horizontalOverflow, setNationality, trackConsoleErrors } from "./helpers";

/**
 * Public website smoke (EN + IT). Every page: renders its H1, no first-party console errors and,
 * on the 390 px mobile project, no horizontal overflow.
 */

let errors: string[] = [];

test.beforeEach(async ({ page, context }) => {
  await setNationality(context, "IT");
  errors = trackConsoleErrors(page);
});

test.afterEach(async ({ page }, testInfo) => {
  if (testInfo.status !== testInfo.expectedStatus) return;
  expect(errors, "console errors").toEqual([]);
  const overflow = await horizontalOverflow(page);
  expect(overflow, `horizontal overflow on ${page.url()}`).toBeLessThanOrEqual(0);
});

async function open(page: Page, path: string, h1?: string | RegExp) {
  const res = await page.goto(path);
  expect(res?.status(), `HTTP status of ${path}`).toBeLessThan(400);
  const heading = page.getByRole("heading", { level: 1 });
  if (h1) await expect(heading).toHaveText(h1);
  else await expect(heading.first()).toBeVisible();
  await page.waitForLoadState("networkidle");
}

/** Booking widget: the sidebar on desktop, a bottom sheet behind "Check availability" on mobile. */
async function bookingWidget(page: Page) {
  const open = page.getByRole("button", { name: "Check availability" });
  if (await open.isVisible().catch(() => false)) {
    await open.click();
    const sheet = page.getByRole("dialog", { name: "Book this trip" });
    await expect(sheet).toBeVisible();
    return sheet;
  }
  return page.getByRole("complementary", { name: "Booking" });
}

const STATIC_PAGES: [string, RegExp | undefined][] = [
  ["/en/about", undefined],
  ["/en/contact", undefined],
  ["/en/faq", undefined],
  ["/en/booking-terms", undefined],
  ["/en/privacy", undefined],
  ["/en/cancellation-policy", undefined],
  ["/en/cookie-policy", undefined],
  ["/en/destinations", undefined],
  ["/en/destinations/luxor", /Luxor/],
];

test.describe("Public website", () => {
  test("home page", async ({ page }) => {
    await open(page, "/en", "Discover Egypt, your way");
    await expect(page.getByRole("tablist", { name: "What are you looking for?" })).toBeVisible();
    await expect(page.getByRole("region", { name: /Top destinations/ })).toBeVisible();
  });

  test("Italian home page", async ({ page }) => {
    await open(page, "/it");
    await expect(page.locator("html")).toHaveAttribute("lang", "it");
  });

  test("trips listing and destination filter (Luxor)", async ({ page }) => {
    await open(page, "/en/trips", "Trips and tours in Egypt");
    const cards = page.getByRole("main").getByRole("article");
    await expect(cards.first()).toBeVisible();

    // The filter panel is a drawer on mobile.
    const filters = page.getByRole("button", { name: /^Filters/ });
    if (await filters.isVisible().catch(() => false)) await filters.click();
    const destination = page.getByRole("combobox", { name: "Destination" }).last();
    await destination.click();
    await destination.fill("Luxor");
    await page.getByRole("option", { name: /Luxor/ }).first().click();
    const show = page.getByRole("button", { name: /^Show/ });
    if (await show.isVisible().catch(() => false)) await show.click();
    await expect(page).toHaveURL(/destination=luxor/);
    await expect(cards.first()).toContainText("Luxor");
    for (const card of await cards.all()) await expect(card).toContainText("Luxor");
  });

  test("trip detail: per-unit widget and season table", async ({ page }) => {
    await open(page, "/en/trips/desert-buggy-safari", "Desert buggy safari");
    const widget = await bookingWidget(page);
    await expect(widget.getByRole("heading", { name: "Choose your buggy" })).toBeVisible();
    await expect(widget.getByRole("group", { name: "Quantity of Single buggy" })).toBeAttached();
    await expect(widget.getByRole("group", { name: "Quantity of Double buggy" })).toBeAttached();
    const table = page.getByRole("table", { name: "Prices and seasons" });
    await expect(table).toBeVisible();
    await expect(table.getByRole("columnheader", { name: "From" })).toBeVisible();
    expect(await table.getByRole("row").count()).toBeGreaterThan(1);
  });

  test("per-person trip detail", async ({ page }) => {
    await open(page, "/en/trips/ras-mohammed-snorkelling", /Ras Mohammed/);
    await expect((await bookingWidget(page)).getByRole("button", { name: "Reserve now" })).toBeVisible();
  });

  test("Nile listing and detail without cabin deck (CR-8)", async ({ page }) => {
    await open(page, "/en/nile", "Nile trips and cruises");
    await page.getByRole("link", { name: /M\/S Nile Sun/ }).first().click();
    await expect(page.getByRole("heading", { level: 1 })).toContainText("Nile Sun");
    await page.waitForLoadState("networkidle");
    // "sun deck" is a ship facility, not a cabin deck choice.
    const text = (await page.getByRole("main").innerText()).replace(/sun deck/gi, "");
    expect(text).not.toMatch(/\bdecks?\b/i);
  });

  test("packages listing and detail", async ({ page }) => {
    await open(page, "/en/packages", "Egypt holiday packages");
    await page.getByRole("link", { name: "Classic Egypt 8 days" }).first().click();
    await expect(page.getByRole("heading", { level: 1 })).toHaveText(/Classic Egypt 8 days/);
    await page.waitForLoadState("networkidle");
  });

  test("accommodation listing and room detail with Tax included (CR-2)", async ({ page }) => {
    await open(page, "/en/accommodation", "Rooms and hotels");
    const card = page.getByRole("main").getByRole("article").filter({ hasText: "Standard double room" });
    await expect(card.getByText("Tax included")).toBeVisible();
    await card.getByRole("link", { name: "Standard double room" }).click();
    await expect(page).toHaveURL(/\/en\/rooms\/2/);
    await expect(page.getByRole("heading", { level: 1 })).toContainText("Standard double room");
    await expect(page.getByText("Tax included").first()).toBeVisible();
    await page.waitForLoadState("networkidle");
  });

  test("transfers: image before the form and a fixed-price quote (CR-3)", async ({ page }) => {
    await open(page, "/en/transfers", "Airport transfers");
    const img = page.getByRole("main").getByRole("img", { name: /driver welcoming/i });
    const form = page.getByRole("heading", { name: "Book an airport transfer" });
    await expect(img).toBeVisible();
    const imgBox = (await img.boundingBox())!;
    const formBox = (await form.boundingBox())!;
    // Image first: above the form on mobile, before it (left or above) on desktop.
    expect(imgBox.y < formBox.y || imgBox.x < formBox.x).toBeTruthy();

    const from = page.getByRole("combobox", { name: "From" });
    await from.click();
    await from.fill("Cairo");
    await page.getByRole("option", { name: /Cairo Airport/ }).first().click();
    const to = page.getByRole("combobox", { name: "To" });
    await to.click();
    await to.fill("Giza");
    await page.getByRole("option", { name: /^Giza pyramids hotels/ }).click();
    const quote = page.waitForResponse((r) => r.url().includes("/transfers/quote") && r.request().method() === "POST");
    await page.getByRole("button", { name: "Get price" }).click();
    const res = await quote;
    expect(res.status()).toBe(200);
    await expect(page.getByText(/fixed price/i).first()).toBeVisible();
    await expect(page.getByRole("main").getByText(/€\s?\d+/).first()).toBeVisible();
  });

  for (const [path, h1] of STATIC_PAGES) {
    test(`static page ${path}`, async ({ page }) => {
      await open(page, path, h1);
    });
  }

  test("Italian trips listing", async ({ page }) => {
    await open(page, "/it/trips");
    await expect(page.getByRole("main").getByRole("article").first()).toBeVisible();
  });

  test("unknown page renders the 404", async ({ page }) => {
    const res = await page.goto("/en/this-page-does-not-exist-e2e");
    expect(res?.status()).toBe(404);
    // The browser logs the document's own 404 status: not an application error.
    errors.splice(0, errors.length, ...errors.filter((e) => !e.includes("status of 404")));
    await expect(page.getByRole("heading", { level: 1 })).toBeVisible();
    await expect(page.getByRole("link", { name: /home/i }).first()).toBeVisible();
  });
});
