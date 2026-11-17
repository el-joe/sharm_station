import { expect, test } from "./fixtures";
import { money, setNationality } from "./helpers";

/** Hero type-ahead search, header SelectMenus (language, currency) and listing sort menus. */

test.beforeEach(async ({ context }) => {
  await setNationality(context, "IT");
});

test("hero search type-ahead finds Hotel Nile View for 'luxor nile'", async ({ page }) => {
  await page.goto("/en");
  const hero = page.getByRole("region", { name: "Discover Egypt, your way" });
  await hero.getByRole("tablist", { name: "What are you looking for?" }).getByRole("tab", { name: "Accommodation", exact: true }).click();
  const box = hero.getByRole("tabpanel").getByRole("combobox", { name: "Destination" });
  await box.click();
  await box.pressSequentially("luxor nile", { delay: 30 });
  const option = page.getByRole("listbox", { name: "Destination" }).getByRole("option", { name: /^Hotel Nile View/ });
  await expect(option).toBeVisible();
  await option.click();
  await expect(box).toHaveValue(/Hotel Nile View/);
  await hero.getByRole("tabpanel").getByRole("button", { name: "Search" }).click();
  await expect(page).toHaveURL(/\/en\/(accommodation|buildings)/);
  await expect(page.getByRole("main")).toContainText("Hotel Nile View");
});

test("hero trips search: destination type-ahead and search", async ({ page }) => {
  await page.goto("/en");
  const hero = page.getByRole("region", { name: "Discover Egypt, your way" });
  const box = hero.getByRole("tabpanel").getByRole("combobox", { name: "Destination" });
  await box.click();
  await box.pressSequentially("lux", { delay: 30 });
  await page.getByRole("listbox", { name: "Destination" }).getByRole("option", { name: /^Luxor/ }).first().click();
  await hero.getByRole("tabpanel").getByRole("button", { name: "Search" }).click();
  await expect(page).toHaveURL(/\/en\/trips\?.*destination=luxor/);
});

test("language menu switches to Italian", async ({ page, isMobile }) => {
  test.skip(isMobile, "header menus are in the drawer on mobile");
  await page.goto("/en/trips");
  const language = page.getByRole("banner").getByRole("combobox", { name: "Language" });
  await expect(language).toHaveText(/EN/);
  await language.click();
  await page.getByRole("listbox", { name: "Language" }).getByRole("option", { name: /Italiano/ }).click();
  await expect(page).toHaveURL(/\/it\/trips/);
  await expect(page.locator("html")).toHaveAttribute("lang", "it");
});

test("currency menu: USD updates prices", async ({ page, isMobile }) => {
  test.skip(isMobile, "header menus are in the drawer on mobile");
  await page.goto("/en/trips");
  const firstPrice = page.getByRole("main").getByRole("article").first().getByText(/€\s?[\d.,]+/);
  await expect(firstPrice).toBeVisible();
  const eur = money((await firstPrice.textContent()) ?? "");
  const currency = page.getByRole("banner").getByRole("combobox", { name: "Currency" });
  await currency.click();
  await page.getByRole("listbox", { name: "Currency" }).getByRole("option", { name: /USD/ }).click();
  await expect(currency).toHaveText(/USD/);
  const usdPrice = page.getByRole("main").getByRole("article").first().getByText(/(US)?\$\s?[\d.,]+/);
  await expect(usdPrice).toBeVisible();
  const usd = money((await usdPrice.textContent()) ?? "");
  expect(usd).toBeGreaterThan(0);
  expect(usd).not.toBe(eur);
  // Keyboard: the menu opens with Enter and closes with Escape.
  await currency.focus();
  await page.keyboard.press("Enter");
  await expect(page.getByRole("listbox", { name: "Currency" })).toBeVisible();
  await page.keyboard.press("Escape");
  await expect(page.getByRole("listbox", { name: "Currency" })).toBeHidden();
});

for (const path of ["/en/trips", "/en/accommodation"]) {
  test(`sort menu on ${path}: price low to high`, async ({ page }) => {
    await page.goto(path);
    const sort = page.getByRole("combobox", { name: "Sort by" });
    await sort.click();
    await page.getByRole("listbox", { name: "Sort by" }).getByRole("option", { name: "Price: low to high" }).click();
    await expect(sort).toHaveText(/Price: low to high/);
    await expect(page).toHaveURL(/sort=/);
    const cards = page.getByRole("main").getByRole("article");
    await expect(cards.first()).toBeVisible();
    await page.waitForLoadState("networkidle");
    const prices: number[] = [];
    for (const card of await cards.all()) {
      const text = await card.getByText(/€\s?[\d.,]+/).first().textContent();
      prices.push(money(text ?? ""));
    }
    expect(prices.length).toBeGreaterThan(1);
    expect(prices).toEqual([...prices].sort((a, b) => a - b));
  });
}
