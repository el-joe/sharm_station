import type { Locator, Page } from "@playwright/test";
import { expect, test } from "./fixtures";
import { ACCOUNTS, BUGGY, apiContext, authFile, isoDate, money, password, readToken, setNationality } from "./helpers";

/** Partner panel (CR-11, CR-12, CR-13, CR-15): commission per type, partner booking flow, documents. */

test.use({ storageState: authFile("partner") });

const round2 = (n: number) => Math.round(n * 100) / 100;

async function pickCombo(page: Page, box: Locator, name: string) {
  await box.click();
  await box.fill(name);
  await page.getByRole("option", { name: new RegExp(`${name}$`) }).first().click();
  await expect(box).toHaveValue(name);
}

/** Read "Term → definition" pairs of a <dl>-style summary. */
async function definition(scope: Locator, term: string | RegExp): Promise<number> {
  const dt = scope.getByRole("term").filter({ hasText: term }).first();
  const dd = dt.locator("xpath=following-sibling::*[1]");
  return money((await dd.textContent()) ?? "");
}

test("partner signs in through the form and lands on the panel", async ({ browser }) => {
  const context = await browser.newContext({ storageState: { cookies: [], origins: [] } });
  await context.addInitScript(() => window.localStorage.setItem("ssv_cookie_consent", "essential"));
  const page = await context.newPage();
  await page.goto("/en/sign-in?returnTo=/partner");
  // /sign-in is exempt from the nationality gate (partners never need it).
  await expect(page.getByRole("heading", { name: "Sign in", level: 1 })).toBeVisible();
  await page.waitForTimeout(1000);
  await expect(page.getByRole("dialog", { name: "Where are you from?" })).toBeHidden();
  await page.getByRole("main").getByRole("textbox", { name: "Email" }).fill(ACCOUNTS.partner);
  await page.getByRole("main").getByRole("textbox", { name: "Password" }).fill(password());
  await page.getByRole("main").getByRole("button", { name: "Sign in" }).click();
  await expect(page).toHaveURL(/\/en\/partner/);
  await expect(page.getByRole("navigation", { name: "Partner panel sections" })).toBeVisible();
  await context.close();
});

test("dashboard shows the commission per type and no accommodation commission", async ({ page }) => {
  await page.goto("/en/partner");
  await expect(page.getByRole("heading", { level: 1 })).toHaveText(/^Hello/);
  const commission = page.getByRole("region", { name: "Your commission" });
  for (const type of ["Single-day trips", "Multi-day trips", "Nile trips", "Packages", "Transfers"]) {
    await expect(commission.getByRole("listitem").filter({ hasText: type })).toContainText(/\d+(\.\d+)?%/);
  }
  await expect(commission.getByRole("listitem").filter({ hasText: "Accommodation" })).toContainText("No commission");
});

test("new booking: EG client, buggy units, live quote arithmetic, confirmation, detail and vouchers", async ({ page }) => {
  await page.goto("/en/partner/new");
  await expect(page.getByRole("heading", { name: "New booking", level: 1 })).toBeVisible();
  await pickCombo(page, page.getByRole("combobox", { name: "Client nationality" }), "Egypt");
  await expect(page.getByText(/Group: Egyptian/).first()).toBeVisible();

  const card = page.getByRole("listitem").filter({ has: page.getByRole("heading", { name: BUGGY.title }) });
  // No date chosen: "From" price, no season name; one clear label per price.
  await expect(card.getByText(/· From$/)).toBeVisible();
  await expect(card.getByRole("paragraph").filter({ hasText: /^Customer price \D{0,2}[\d.,]+$/ })).toBeVisible();
  await expect(card.getByRole("paragraph").filter({ hasText: /^Partner price \D{0,2}[\d.,]+$/ })).toBeVisible();
  await expect(card).not.toContainText("Customer price: Customer");
  const catalogueCustomer = money((await card.getByRole("paragraph").filter({ hasText: /^Customer price/ }).textContent()) ?? "");
  const cataloguePartner = money((await card.getByRole("paragraph").filter({ hasText: /^Partner price/ }).textContent()) ?? "");
  const pct = Number(((await card.getByText(/% commission/).textContent()) ?? "").match(/[\d.]+/)?.[0]);
  expect(cataloguePartner).toBeCloseTo(round2(catalogueCustomer * (1 - pct / 100)), 2);
  await card.getByRole("button", { name: `Select ${BUGGY.title}` }).click();

  // Configure: 2 adults on 2 single buggies.
  await expect(page.getByRole("region", { name: "Client nationality" }).getByRole("combobox")).toHaveValue("Egypt");
  const units = page.getByRole("region", { name: "Choose buggy" });
  await units.getByRole("button", { name: `Add one: ${BUGGY.single}` }).click();
  await units.getByRole("button", { name: `Add one: ${BUGGY.single}` }).click();
  const shownUnitPrice = money((await units.getByText(/^\D{0,4}[\d.,]+\s?\D{0,4} per buggy$/).first().textContent()) ?? "");

  const summary = page.getByRole("region", { name: "Price summary" });
  await expect(summary.getByRole("term").filter({ hasText: "Partner price" })).toBeVisible();
  await expect(summary.getByRole("listitem").filter({ hasText: BUGGY.single })).toContainText("× 2");
  const customer = await definition(summary, "Customer price");
  const commissionLine = summary.getByRole("term").filter({ hasText: /^Commission/ });
  const percent = Number(((await commissionLine.textContent()) ?? "").match(/Commission\s+([\d.]+)%/)?.[1]);
  const commission = Math.abs(await definition(summary, /^Commission/));
  const partner = await definition(summary, "Partner price");
  expect(percent).toBe(pct);
  expect(commission).toBeCloseTo(round2(customer * percent / 100), 2);
  expect(partner).toBeCloseTo(round2(customer - commission), 2);
  // Unit price shown in the configure step is the client's (EG) price used by the quote.
  expect.soft(customer, "configure-step unit price must be the client nationality price").toBeCloseTo(shownUnitPrice * 2, 2);

  await summary.getByRole("button", { name: "Continue" }).click();

  // Client & payment.
  await expect(page.getByRole("heading", { name: "Client & payment", level: 1 })).toBeVisible();
  const lead = page.getByRole("region", { name: "Lead client" });
  await lead.getByRole("textbox", { name: "First name" }).fill("E2E");
  await lead.getByRole("textbox", { name: "Last name" }).fill("Client");
  await lead.getByRole("textbox", { name: "Email" }).fill("e2e.client@example.test");
  await lead.getByRole("textbox", { name: "Phone (WhatsApp)" }).fill("+20 100 000 0000");
  await expect(lead).toContainText("Egypt · Egyptian");
  const others = page.getByRole("region", { name: "Other travellers" });
  await others.getByRole("textbox", { name: "First name" }).fill("E2E");
  await others.getByRole("textbox", { name: "Last name" }).fill("Second");
  await page.getByRole("radio", { name: /^Bank transfer or cash deposit/ }).check();
  await page.getByRole("radio", { name: /^Voucher without prices/ }).check();
  await page.getByRole("textbox", { name: "Notes for our team (optional)" }).fill("E2E automated partner booking, please ignore.");
  await page.getByRole("checkbox", { name: /I confirm the client details/ }).check();

  const created = page.waitForResponse((r) => /\/partner\/bookings(\?|$)/.test(r.url()) && r.request().method() === "POST");
  await page.getByRole("button", { name: "Confirm booking" }).click();
  const res = await created;
  expect(res.status(), await res.text()).toBe(201);
  const booking = (await res.json()).data;
  const reference: string = booking.reference;
  expect(booking.nationality.code).toBe("EG");
  expect(booking.totals.retail_total).toBeCloseTo(customer, 2);
  expect(booking.totals.total).toBeCloseTo(partner, 2);

  // Confirmation shows the reference.
  await expect(page.getByText(reference).first()).toBeVisible();

  // Booking detail.
  await page.goto(`/en/partner/bookings/${reference}`);
  await expect(page.getByRole("heading", { name: `Booking ${reference}`, level: 1 })).toBeVisible();
  await expect(page.getByRole("region", { name: "Client (lead traveller)" })).toContainText("Egypt");
  const totals = page.getByRole("region", { name: "Totals" });
  expect(await definition(totals, "Customer total")).toBeCloseTo(customer, 2);
  expect(await definition(totals, /^Partner total/)).toBeCloseTo(partner, 2);

  // Vouchers with and without prices.
  await page.goto(`/en/partner/bookings/${reference}/voucher?prices=1`);
  const withPrices = page.getByRole("article", { name: reference });
  await expect(withPrices).toBeVisible();
  await expect(withPrices).toContainText(/€\s?[\d.,]+/);
  await page.goto(`/en/partner/bookings/${reference}/voucher?prices=0`);
  const withoutPrices = page.getByRole("article", { name: reference });
  await expect(withoutPrices).toBeVisible();
  await expect(page.getByText("Client voucher without prices")).toBeVisible();
  await expect(withoutPrices).not.toContainText(/€\s?[\d.,]+/);
  await expect(withoutPrices).toContainText("Egypt");

  // Listed in the bookings page.
  await page.goto("/en/partner/bookings");
  await expect(page.getByRole("link", { name: reference }).first()).toBeVisible();
});

test("statements page", async ({ page }) => {
  await page.goto("/en/partner/statements");
  await expect(page.getByRole("heading", { name: "Statements", level: 1 })).toBeVisible();
  await expect(page.getByText("Commission earned").first()).toBeVisible();
  await expect(page.getByText("Outstanding").first()).toBeVisible();
});

test("price list: base row plus one row per season; switching the client nationality changes prices", async ({ page }) => {
  await page.goto("/en/partner/price-list");
  const box = page.getByRole("combobox", { name: "Client nationality" });
  const buggyRows = (table: Locator) => table.getByRole("row").filter({ has: page.getByRole("rowheader", { name: new RegExp(BUGGY.title) }) });
  const cells = async (row: Locator) => {
    const c = row.getByRole("cell");
    return {
      season: ((await c.nth(0).textContent()) ?? "").trim(),
      customer: money((await c.nth(1).textContent()) ?? ""),
      pct: Number(((await c.nth(2).textContent()) ?? "").match(/[\d.]+/)?.[0]),
      partner: money((await c.nth(3).textContent()) ?? ""),
    };
  };

  await pickCombo(page, box, "Egypt");
  const egTable = page.getByRole("table", { name: "Prices for nationality group Egyptian" });
  await expect(buggyRows(egTable).first()).toBeVisible();
  const eg = await Promise.all((await buggyRows(egTable).all()).map(cells));
  expect(eg[0].season).toBe("Base price");
  expect(eg.length, "base row + season rows").toBeGreaterThan(1);
  for (const r of eg) expect(r.partner).toBeCloseTo(round2(r.customer * (1 - r.pct / 100)), 2);
  // Season rows carry a name and a date range, e.g. "High season Nov 1 – Dec 19, 2026".
  for (const r of eg.slice(1)) expect(r.season).toMatch(/\d{4}$/);

  await pickCombo(page, box, "Italy");
  const itTable = page.getByRole("table", { name: "Prices for nationality group Europe" });
  await expect(buggyRows(itTable).first()).toBeVisible();
  const itBase = await cells(buggyRows(itTable).first());
  expect(itBase.season).toBe("Base price");
  expect(eg[0].customer).toBeLessThan(itBase.customer);
});

test("company profile shows read-only commissions", async ({ page }) => {
  await page.goto("/en/partner/profile");
  await expect(page.getByRole("heading", { name: "Company profile", level: 1 })).toBeVisible();
  const commissions = page.getByRole("region", { name: "Commissions" });
  await expect(commissions).toContainText("Read-only");
  await expect(commissions.getByRole("textbox")).toHaveCount(0);
  await expect(commissions.getByRole("spinbutton")).toHaveCount(0);
  await expect(commissions.getByRole("listitem").filter({ hasText: "Accommodation" })).toContainText("No commission");
  await expect(page.getByRole("textbox", { name: "Company name" })).not.toBeEditable();
});

test("public site while signed in as partner shows customer prices and no partner UI (CR-15)", async ({ page, context, browser }) => {
  await setNationality(context, "EG");
  const unitPrice = async (p: Page) => {
    await p.goto(`/en/trips/${BUGGY.slug}`);
    const widget = p.getByRole("complementary", { name: "Booking" });
    await expect(widget.getByRole("group", { name: `Quantity of ${BUGGY.single}` })).toBeVisible();
    await p.waitForLoadState("networkidle");
    return money((await widget.getByText(/^\D{0,4}[\d.,]+\s?\D{0,4} per buggy$/).first().textContent()) ?? "");
  };

  const partnerSees = await unitPrice(page);
  await expect(page.getByRole("banner").getByRole("button", { name: /Account menu for/ })).toBeVisible();

  // Same page for an anonymous EG visitor.
  const anon = await browser.newContext();
  await setNationality(anon, "EG");
  await anon.addInitScript(() => window.localStorage.setItem("ssv_cookie_consent", "essential"));
  const anonPage = await anon.newPage();
  const customerSees = await unitPrice(anonPage);
  await anon.close();
  expect(partnerSees).toBe(customerSees);

  const main = page.getByRole("main");
  await expect(main).not.toContainText(/partner price/i);
  await expect(main).not.toContainText(/commission/i);
  await expect(page.getByText(/partner mode/i)).toHaveCount(0);

  // Public quote with the partner's token still returns customer prices only.
  const api = await apiContext();
  const trip = (await (await api.get(`trips/${BUGGY.slug}`, { params: { nationality: "EG" } })).json()).data;
  await api.dispose();
  const single = trip.unit_options.find((u: { name: string }) => u.name === BUGGY.single);
  const partnerApi = await apiContext(readToken("partner"));
  const anonApi = await apiContext();
  const body = { booking_type: "private", travel_date: isoDate(3), adults: 1, children: 0, child_ages: [], units: [{ unit_option_id: single.id, quantity: 1 }], addons: [] };
  const asPartner = await partnerApi.post(`trips/${BUGGY.slug}/quote`, { params: { nationality: "EG" }, data: body });
  const asAnon = await anonApi.post(`trips/${BUGGY.slug}/quote`, { params: { nationality: "EG" }, data: body });
  expect(asPartner.status()).toBe(200);
  const qp = (await asPartner.json()).data;
  const qa = (await asAnon.json()).data;
  expect(qp.total).toBe(qa.total);
  expect(qp.partner_total ?? null).toBeNull();
  await partnerApi.dispose();
  await anonApi.dispose();

  // The account menu offers the partner panel.
  await page.getByRole("banner").getByRole("button", { name: /Account menu for/ }).click();
  await expect(page.getByRole("menuitem", { name: "Partner panel" }).or(page.getByRole("link", { name: "Partner panel" })).first()).toBeVisible();
});
