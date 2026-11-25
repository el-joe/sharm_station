import type { Page } from "@playwright/test";
import { expect, test } from "./fixtures";
import { BUGGY, apiContext, authFile, money, readToken, setNationality } from "./helpers";

/** Trip checkout at customer prices with the visitor's nationality (CR-10, SRS 3.9). */

async function reserveBuggy(page: Page) {
  await page.goto(`/en/trips/${BUGGY.slug}`);
  const widget = page.getByRole("complementary", { name: "Booking" });
  await expect(widget.getByRole("group", { name: `Quantity of ${BUGGY.single}` })).toBeVisible();
  const total = money((await widget.getByText(/^Total\s*€/).first().textContent()) ?? "");
  await widget.getByRole("button", { name: "Reserve now" }).click();
  await expect(page).toHaveURL(/\/en\/checkout/);
  await expect(page.getByRole("heading", { name: "Checkout", level: 1 })).toBeVisible();
  return total;
}

async function fillCheckout(page: Page, email?: string) {
  const lead = page.getByRole("group", { name: "Lead traveller" });
  await lead.getByRole("textbox", { name: "First name" }).fill("E2E");
  await lead.getByRole("textbox", { name: "Last name" }).fill("Tester");
  const emailBox = lead.getByRole("textbox", { name: "Email" });
  if (email) await emailBox.fill(email);
  else if (!(await emailBox.inputValue())) await emailBox.fill("e2e.tester@example.test");
  await lead.getByRole("textbox", { name: "Mobile phone" }).fill("+39 333 123 4567");
  await lead.getByRole("combobox", { name: "Country of residence" }).click();
  await page.getByRole("option", { name: "Italy" }).click();
  const others = page.getByRole("group", { name: "Other travellers" });
  if (await others.isVisible().catch(() => false)) {
    const items = others.getByRole("listitem");
    for (let i = 0; i < (await items.count()); i++) {
      await items.nth(i).getByRole("textbox", { name: "First name" }).fill(`E2E${i + 2}`);
      await items.nth(i).getByRole("textbox", { name: "Last name" }).fill("Traveller");
    }
  }
  await page.getByRole("textbox", { name: "Special requests" }).fill("E2E automated test booking, please ignore.");
  await page.getByRole("checkbox", { name: /I accept the terms/ }).check();
}

async function submitCheckout(page: Page): Promise<{ reference: string; accessToken?: string; body: Record<string, unknown> }> {
  // Guests post to the API directly, signed-in visitors through the session proxy (/api/proxy/bookings).
  const created = page.waitForResponse((r) => /\/api\/(v1|proxy)\/bookings(\?|$)/.test(r.url()) && r.request().method() === "POST");
  await page.getByRole("button", { name: "Continue to payment" }).click();
  const res = await created;
  expect(res.status(), await res.text()).toBe(201);
  const request = res.request().postDataJSON();
  const json = await res.json();
  const reference: string = json.data.reference;
  expect(reference).toBeTruthy();
  return { reference, accessToken: json.data.access_token, body: request };
}

test("guest trip checkout stores the visitor's nationality", async ({ page, context }) => {
  await setNationality(context, "EG");
  const widgetTotal = await reserveBuggy(page);
  const summary = page.getByRole("complementary", { name: "Your booking" });
  expect(money((await summary.getByText(/^Total\s*€/).first().textContent()) ?? "")).toBe(widgetTotal);
  await fillCheckout(page);
  const { reference, accessToken, body } = await submitCheckout(page);
  expect(body.nationality_code).toBe("EG");
  expect(accessToken, "guest bookings return an access token").toBeTruthy();

  await expect(page).toHaveURL(new RegExp(`/en/booking/${reference}`));
  await expect(page.getByRole("heading", { level: 1 })).toHaveText(/Booking (received|confirmed)/);
  await expect(page.getByText(reference).first()).toBeVisible();

  const api = await apiContext();
  const res = await api.get(`bookings/${reference}`, { params: { token: accessToken! } });
  expect(res.status()).toBe(200);
  const booking = (await res.json()).data;
  expect(booking.nationality.code).toBe("EG");
  expect(booking.nationality.group?.code).toBe("egyptian");
  expect(booking.totals.total).toBeCloseTo(widgetTotal, 2);
  expect(booking.totals.retail_total).toBeCloseTo(widgetTotal, 2);
  await api.dispose();
});

test.describe("signed-in customer", () => {
  test.use({ storageState: authFile("customer") });

  test("booking appears in My bookings", async ({ page }) => {
    const api = await apiContext(readToken("customer"));
    // Signed in, the account nationality wins over the cookie (IT in the saved state).
    const account = ((await (await api.get("auth/me")).json()).data.nationality_code as string | null) ?? "IT";
    await reserveBuggy(page);
    await fillCheckout(page);
    const { reference, body } = await submitCheckout(page);
    expect(body.nationality_code).toBe(account);
    await expect(page).toHaveURL(new RegExp(`/en/booking/${reference}`));

    await page.goto("/en/account/bookings");
    await expect(page.getByText(reference).first()).toBeVisible();

    const mine = await api.get("me/bookings");
    expect(mine.status()).toBe(200);
    const list = (await mine.json()).data as { reference: string; nationality?: { code: string } }[];
    const found = list.find((b) => b.reference === reference);
    expect(found, "booking listed by /me/bookings").toBeTruthy();
    if (found?.nationality) expect(found.nationality.code).toBe(account);
    await api.dispose();
  });
});
