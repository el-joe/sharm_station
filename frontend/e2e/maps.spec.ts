import type { Locator, Page } from "@playwright/test";
import { expect, test } from "./fixtures";
import { BUGGY, setNationality } from "./helpers";
import { overflowPx, watchPage } from "./quality";

/**
 * Google Maps (v2.3): single-location embeds (contact, trip meeting point, building / room),
 * multi-place maps (destination, accommodation map view) and the transfer route link.
 *
 * Key-agnostic: the suite passes with and without NEXT_PUBLIC_GOOGLE_MAPS_API_KEY. Google itself is
 * never contacted: embed requests are answered with a stub page, so only our markup is asserted.
 * The shared fixture answers the cookie banner with "Essential only", so maps start as a
 * click-to-load placeholder.
 */

const GOOGLE_EMBED_SRC = /^https:\/\/(maps\.google\.com\/maps\?.*output=embed|www\.google\.com\/maps\/embed\/v1\/(place|view)\?)/;
const SHOW_MAP = "Show map — loads Google Maps";

test.beforeEach(async ({ context }) => {
  await setNationality(context, "IT");
  // Stub Google: keyless and Embed API iframes get a blank page (no third-party traffic in tests).
  await context.route(/^https:\/\/(maps\.google\.com\/maps|www\.google\.com\/maps\/embed)/, (route) =>
    route.fulfill({ status: 200, contentType: "text/html", body: "<!doctype html><title>Google Maps stub</title>" }),
  );
});

/** Scroll until the map region is on screen (maps load lazily near the viewport). */
async function mapRegion(page: Page, name: string | RegExp): Promise<Locator> {
  const map = page.getByRole("region", { name });
  for (let i = 0; i < 20 && !(await map.first().isVisible().catch(() => false)); i++) {
    await page.mouse.wheel(0, 700);
    await page.waitForTimeout(200);
  }
  await expect(map.first()).toBeVisible();
  await map.first().evaluate((el) => el.scrollIntoView({ block: "center" }));
  return map.first();
}

/** "Essential only": a click-to-load placeholder, nothing from Google until "Show map". */
async function expectPlaceholderThenLoad(map: Locator) {
  await expect(map.locator("iframe")).toHaveCount(0);
  const show = map.getByRole("button", { name: SHOW_MAP });
  await expect(show).toBeVisible();
  await show.click();
  await expect(show).toBeHidden();
}

/** After consent: the keyless / Embed API iframe, or (with a key, multi-place maps) the JS API map. */
async function expectGoogleMap(map: Locator, { iframe }: { iframe: "always" | "either" }) {
  if (iframe === "always") {
    const frame = map.locator("iframe");
    await expect(frame).toHaveCount(1);
    await expect(frame).toHaveAttribute("src", GOOGLE_EMBED_SRC);
    await expect(frame).toHaveAttribute("loading", "lazy");
    await expect(frame).toHaveAttribute("title", /^Google Maps: .+/);
    return;
  }
  await expect
    .poll(async () => (await map.locator("iframe").count()) + (await map.locator(".gm-style").count()), { message: "Google embed or JS map rendered", timeout: 20_000 })
    .toBeGreaterThan(0);
  if (await map.locator("iframe").count()) await expect(map.locator("iframe")).toHaveAttribute("src", GOOGLE_EMBED_SRC);
}

async function expectLinks(scope: Locator) {
  const open = scope.getByRole("link", { name: /^Open in Google Maps/ }).first();
  await expect(open).toHaveAttribute("href", /^https:\/\/www\.google\.com\/maps\/search\/\?api=1&query=-?\d+(\.\d+)?,-?\d+(\.\d+)?$/);
  await expect(open).toHaveAttribute("target", "_blank");
  const directions = scope.getByRole("link", { name: "Get directions" }).first();
  await expect(directions).toHaveAttribute("href", /^https:\/\/www\.google\.com\/maps\/dir\/\?api=1&destination=-?\d+(\.\d+)?,-?\d+(\.\d+)?$/);
  await expect(directions).toHaveAttribute("target", "_blank");
  await expect(directions).toHaveAttribute("rel", /noopener/);
}

test("no Leaflet / OpenStreetMap left", async ({ page }) => {
  const osm: string[] = [];
  page.on("request", (r) => /openstreetmap|leaflet/i.test(r.url()) && osm.push(r.url()));
  await page.goto("/en/contact");
  await mapRegion(page, "Map of our office in Naama Bay");
  await expect(page.locator(".leaflet-container")).toHaveCount(0);
  await expect(page.getByText(/OpenStreetMap/)).toHaveCount(0);
  expect(osm).toEqual([]);
});

test("contact page: office embed behind consent, Get directions and Open in Google Maps", async ({ page }) => {
  const watch = watchPage(page);
  await page.goto("/en/contact");
  const map = await mapRegion(page, "Map of our office in Naama Bay");
  const card = page.getByRole("main").locator("section", { has: map });
  await expectLinks(card);
  await expectPlaceholderThenLoad(map);
  await expectGoogleMap(map, { iframe: "always" });
  expect(await overflowPx(page)).toBeLessThanOrEqual(1);
  expect(watch.console).toEqual([]);
});

test("consent 'Accept all': the embed loads without a click", async ({ context, page }) => {
  await context.addInitScript(() => window.localStorage.setItem("ssv_cookie_consent", "all"));
  await page.goto("/en/contact");
  const map = await mapRegion(page, "Map of our office in Naama Bay");
  await expect(map.getByRole("button", { name: SHOW_MAP })).toHaveCount(0);
  await expectGoogleMap(map, { iframe: "always" });
});

test("trip page: meeting point on a Google embed with directions", async ({ page }) => {
  const watch = watchPage(page);
  await page.goto(`/en/trips/${BUGGY.slug}`);
  const map = await mapRegion(page, /^Meeting point: /);
  const card = page.locator("#meeting");
  await expect(card.getByRole("heading", { name: "Meeting point" }).first()).toBeVisible();
  await expectLinks(card);
  await expectPlaceholderThenLoad(map);
  await expectGoogleMap(map, { iframe: "always" });
  expect(watch.console).toEqual([]);
});

test("destination page: multi-place map behind consent and the text list with Google links", async ({ page }) => {
  const watch = watchPage(page);
  await page.goto("/en/destinations/sharm-el-sheikh");
  const map = await mapRegion(page, /^Map of Sharm El Sheikh/);
  // The same places as text (visible without a key; at least for screen readers with one).
  await expect(page.getByRole("heading", { name: "Places on this map" })).toBeAttached();
  const section = page.locator("#map");
  expect(await section.getByRole("link", { name: /^Open in Google Maps: / }).count()).toBeGreaterThan(0);
  await expect(section.getByRole("link", { name: /^Open in Google Maps: / }).first()).toHaveAttribute("href", /google\.com\/maps\/search\/\?api=1&query=/);
  await expectPlaceholderThenLoad(map);
  await expectGoogleMap(map, { iframe: "either" });
  expect(watch.console).toEqual([]);
});

test("accommodation: List / Map toggle, ?view=map is shareable, map + room cards + places list", async ({ page }) => {
  const watch = watchPage(page);
  await page.goto("/en/accommodation");
  await page.getByRole("radio", { name: "Map" }).or(page.getByRole("tab", { name: "Map" })).or(page.getByRole("button", { name: "Map", exact: true })).first().click();
  await expect(page).toHaveURL(/view=map/);
  let map = await mapRegion(page, "Map of the accommodation results");
  await expectPlaceholderThenLoad(map);
  await expectGoogleMap(map, { iframe: "either" });
  await expect(page.getByRole("heading", { name: "Places on this map" })).toBeAttached();

  await page.goto("/en/accommodation?view=map");
  map = await mapRegion(page, "Map of the accommodation results");
  await expect(page.getByRole("main").getByRole("article").first()).toBeVisible();
  await expect(page.getByRole("main").getByRole("link", { name: /^Open in Google Maps: / }).first()).toBeAttached();
  expect(watch.console).toEqual([]);
});

test("room page: building address map with Google links", async ({ page }) => {
  await page.goto("/en/accommodation");
  const href = await page.getByRole("main").locator('a[href*="/rooms/"]').first().getAttribute("href");
  await page.goto(href!);
  const card = page.locator("#building");
  const map = await mapRegion(page, /^Map of /);
  await expectLinks(card);
  await expectPlaceholderThenLoad(map);
  await expectGoogleMap(map, { iframe: "always" });
});

test("transfers: picking a fixed place shows the route link (hub -> drop-off)", async ({ page }) => {
  const watch = watchPage(page);
  await page.goto("/en/transfers");
  const to = page.getByRole("combobox", { name: "To" });
  await to.click();
  await to.fill("Naama");
  const option = page.getByRole("option", { name: /Naama Bay/ }).first();
  await expect(option).toBeVisible();
  await option.click();
  const route = page.getByRole("link", { name: "View route on Google Maps" });
  await expect(route).toBeVisible();
  await expect(route).toHaveAttribute("href", /^https:\/\/www\.google\.com\/maps\/dir\/\?api=1&destination=-?\d+(\.\d+)?,-?\d+(\.\d+)?&origin=[^&]+&travelmode=driving$/);
  await expect(route).toHaveAttribute("target", "_blank");
  expect(watch.console).toEqual([]);
});
