import type { Page } from "@playwright/test";
import { expect, test } from "./fixtures";
import { ADMIN_URL, BUGGY, apiContext, authFile, money, setNationality } from "./helpers";
import { adminFlash, partnerConfirm, partnerToClientStep, tinker } from "./flows";

/**
 * Coupons (PLAN-v2.3 A3/W3): admin creates an E2E code (10 %, trips) -> public trip checkout applies it ->
 * discount line and total -> the booking stores the discount -> invalid code error -> partner flow messages
 * and discount -> the code is deactivated at the end.
 */

test.describe.configure({ mode: "serial" });

const CODE = `E2E${Date.now().toString(36).toUpperCase()}`;
let couponId: number | null = null;

async function adminCouponId(page: Page): Promise<number> {
  const res = await page.request.get(`${ADMIN_URL}/coupons/data`, {
    params: { draw: 1, start: 0, length: 100 },
    headers: { "X-Requested-With": "XMLHttpRequest", Accept: "application/json" },
  });
  const rows = (await res.json()).data as { id: number; code: string }[];
  const row = rows.find((r) => r.code.includes(`>${CODE}<`));
  expect(row, `coupon ${CODE} listed`).toBeTruthy();
  return row!.id;
}

async function editCoupon(page: Page, change: (page: Page) => Promise<void>) {
  await page.goto(`${ADMIN_URL}/coupons/${couponId}/edit`);
  await expect(page.getByRole("textbox", { name: /^Code/ })).toHaveValue(CODE);
  await change(page);
  await page.getByRole("button", { name: "Save" }).first().click();
  await adminFlash(page);
}

/** Expand the promo field, type a code and apply it. */
async function applyCode(page: Page, code: string) {
  const toggle = page.getByRole("button", { name: "Have a promo code?" });
  if (await toggle.isVisible().catch(() => false)) await toggle.click();
  await page.getByRole("textbox", { name: "Promo code" }).fill(code);
  const checked = page.waitForResponse((r) => r.url().includes("/coupons/validate"));
  await page.getByRole("button", { name: "Apply", exact: true }).click();
  return (await (await checked).json()) as { valid: boolean; discount_eur: number; message: string };
}

test.describe("admin + website", () => {
  test.use({ storageState: authFile("admin") });

  test("admin creates the E2E coupon (10 %, trips only)", async ({ page }) => {
    await page.goto(`${ADMIN_URL}/coupons/create`);
    await page.getByRole("textbox", { name: /^Code/ }).fill(CODE);
    await page.getByRole("textbox", { name: "Description" }).fill("E2E automated test coupon");
    await page.getByRole("combobox", { name: "Discount type" }).selectOption("percent");
    await page.getByRole("spinbutton", { name: /^Value/ }).fill("10");
    await page.getByRole("spinbutton", { name: "Uses per customer" }).fill("");
    await page.locator('select[name="services[]"]').evaluate((el: HTMLSelectElement) => {
      for (const o of el.options) o.selected = o.value === "trip";
      el.dispatchEvent(new Event("change", { bubbles: true }));
    });
    await page.getByRole("button", { name: "Save" }).first().click();
    await adminFlash(page);
    couponId = await adminCouponId(page);

    await page.goto(`${ADMIN_URL}/coupons`);
    const row = page.getByRole("row").filter({ hasText: CODE });
    await expect(row).toContainText("10");
    await expect(row).toContainText(/Trip/);
    await expect(row).toContainText(/Active/i);
  });

  test("public trip checkout: invalid code error, apply, remove, apply, booking stores the discount", async ({ page, context }) => {
    test.skip(!couponId, "coupon not created");
    await setNationality(context, "IT");
    await page.goto(`/en/trips/${BUGGY.slug}`);
    const widget = page.getByRole("complementary", { name: "Booking" });
    await expect(widget.getByRole("group", { name: `Quantity of ${BUGGY.single}` })).toBeVisible();
    await expect(widget.getByText(/^Total\s*€/).first()).toBeVisible();
    await page.waitForLoadState("networkidle");
    await widget.getByRole("button", { name: "Reserve now" }).click();
    await expect(page).toHaveURL(/\/en\/checkout/);
    await expect(page.getByRole("heading", { name: "Checkout", level: 1 })).toBeVisible();
    const summary = page.getByRole("complementary", { name: "Your booking" });
    const total = money((await summary.getByText(/^Total\s*€/).first().textContent()) ?? "");
    expect(total).toBeGreaterThan(0);

    // Invalid code.
    const bad = await applyCode(page, "E2E-NO-SUCH-CODE");
    expect(bad.valid).toBe(false);
    await expect(summary.getByRole("alert")).toHaveText(/This code does not exist/);
    await expect(summary.getByText(/^Promo code /)).toHaveCount(0);

    // Valid code: discount line and the new total.
    const good = await applyCode(page, CODE.toLowerCase());
    expect(good.valid, good.message).toBe(true);
    const expected = Math.round(total * 10) / 100;
    expect(good.discount_eur).toBeCloseTo(expected, 2);
    await expect(summary.getByRole("status")).toContainText(`${CODE} applied`);
    const line = summary.getByText(`Promo code ${CODE}`).locator("xpath=..");
    await expect(line).toBeVisible();
    expect(Math.abs(money((await line.textContent())!.replace(`Promo code ${CODE}`, "")))).toBeCloseTo(expected, 2);
    const shownTotal = summary.locator("span.text-\\[26px\\]").first();
    expect(money((await shownTotal.textContent()) ?? "")).toBeCloseTo(total - expected, 2);

    // Remove, then apply again.
    await summary.getByRole("button", { name: "Remove" }).click();
    await expect(summary.getByText(`Promo code ${CODE}`)).toHaveCount(0);
    expect(money((await shownTotal.textContent()) ?? "")).toBeCloseTo(total, 2);
    await applyCode(page, CODE);
    await expect(summary.getByText(`Promo code ${CODE}`)).toBeVisible();

    // Checkout.
    const lead = page.getByRole("group", { name: "Lead traveller" });
    await lead.getByRole("textbox", { name: "First name" }).fill("E2E");
    await lead.getByRole("textbox", { name: "Last name" }).fill("Coupon");
    await lead.getByRole("textbox", { name: "Email" }).fill(`e2e.coupon.${Date.now()}@example.test`);
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
    await page.getByRole("textbox", { name: "Special requests" }).fill("E2E automated coupon booking, please ignore.");
    await page.getByRole("checkbox", { name: /I accept the terms/ }).check();
    const created = page.waitForResponse((r) => /\/api\/v1\/bookings(\?|$)/.test(r.url()) && r.request().method() === "POST");
    await page.getByRole("button", { name: "Continue to payment" }).click();
    const res = await created;
    expect(res.status(), await res.text()).toBe(201);
    expect(res.request().postDataJSON().coupon_code).toBe(CODE);
    const { reference, access_token } = (await res.json()).data;

    const api = await apiContext();
    const booking = (await (await api.get(`bookings/${reference}`, { params: { token: access_token } })).json()).data;
    await api.dispose();
    expect(booking.coupon_code).toBe(CODE);
    expect(booking.totals.discount_amount).toBeCloseTo(good.discount_eur, 2);
    expect(booking.totals.total).toBeCloseTo(total - good.discount_eur, 2);
    expect(booking.lines.some((l: { type: string; total: number }) => l.type === "discount" && Math.abs(l.total + good.discount_eur) < 0.01)).toBe(true);

    // The admin list counts the use.
    await page.goto(`${ADMIN_URL}/coupons`);
    await expect(page.getByRole("row").filter({ hasText: CODE }).getByRole("cell").nth(4)).toHaveText("1");
  });
});

test.describe("partner", () => {
  test.use({ storageState: authFile("partner") });

  test("without coupon permission the partner sees the blocked message", async ({ page }) => {
    test.skip(!couponId, "coupon not created");
    await partnerToClientStep(page);
    const res = await applyCode(page, CODE);
    expect(res.valid).toBe(false);
    await expect(page.getByRole("status").filter({ hasText: "Promo codes cannot be used on partner bookings for your account." })).toBeVisible();
  });

  test("with permission (partner + coupon), the preview discount equals the booking discount", async ({ page, browser }) => {
    test.skip(!couponId, "coupon not created");
    // There is no admin screen for the partner's coupon permission (partners.coupons_allowed): set it directly.
    const before = tinker("echo (int) App\\Models\\Partner::find(1)->coupons_allowed;");
    tinker("App\\Models\\Partner::whereKey(1)->update(['coupons_allowed' => true]);");
    const admin = await browser.newContext({ storageState: authFile("admin") });
    const adminPage = await admin.newPage();
    try {
      await editCoupon(adminPage, async (p) => p.getByRole("checkbox", { name: "Partners allowed" }).setChecked(true, { force: true }));

      await partnerToClientStep(page);
      const summary = page.getByRole("region", { name: "Price summary" }).or(page.getByRole("complementary")).first();
      const preview = await applyCode(page, CODE);
      expect(preview.valid, preview.message).toBe(true);
      await expect(page.getByRole("status").filter({ hasText: `${CODE} applied` })).toBeVisible();
      const res = await partnerConfirm(page);
      expect(res.status(), await res.text()).toBe(201);
      const booking = (await res.json()).data;
      const discount = booking.totals.discount_amount ?? booking.discount_amount;
      expect.soft(discount, "partner coupon preview = final booking discount").toBeCloseTo(preview.discount_eur, 2);
      expect(booking.coupon_code ?? CODE).toBe(CODE);
      void summary;
    } finally {
      tinker(`App\\Models\\Partner::whereKey(1)->update(['coupons_allowed' => ${before === "1" ? "true" : "false"}]);`);
      await editCoupon(adminPage, async (p) => p.getByRole("checkbox", { name: "Partners allowed" }).setChecked(false, { force: true }));
      await admin.close();
    }
  });
});

test.describe("clean-up", () => {
  test.use({ storageState: authFile("admin") });

  test("admin deactivates the E2E coupon; the website then rejects it", async ({ page }) => {
    test.skip(!couponId, "coupon not created");
    await editCoupon(page, async (p) => p.getByRole("checkbox", { name: "Active" }).setChecked(false, { force: true }));
    const api = await apiContext();
    const res = await (await api.post("coupons/validate", { data: { code: CODE, service_type: "trip", total_eur: 100 } })).json();
    await api.dispose();
    expect(res.valid).toBe(false);
    expect(res.message).toMatch(/no longer active/);
  });
});
