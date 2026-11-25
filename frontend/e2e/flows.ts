import type { Browser, Locator, Page } from "@playwright/test";
import { execFileSync } from "node:child_process";
import path from "node:path";
import { expect } from "./fixtures";
import { ADMIN_URL, BUGGY, apiContext, authFile, isoDate } from "./helpers";

/** Flows shared by the v2.3 feature specs: API bookings, admin lookups, the partner booking flow. */

export const BACKEND_DIR = process.env.E2E_BACKEND_DIR ?? path.resolve(__dirname, "../../backend");

/** Runs PHP through `artisan tinker` for test set-up that has no admin screen (dev DB only). */
export function tinker(php: string): string {
  return execFileSync("php", ["artisan", "tinker", "--execute", php], { cwd: BACKEND_DIR, encoding: "utf8", timeout: 60_000 }).trim();
}

export async function singleBuggyId(): Promise<number> {
  const api = await apiContext();
  const trip = (await (await api.get(`trips/${BUGGY.slug}`)).json()).data;
  await api.dispose();
  return trip.unit_options.find((u: { name: string }) => u.name === BUGGY.single).id;
}

/** A fresh E2E trip booking through POST /bookings (guest, or the customer when a token is given). */
export async function createTripBooking(opts: { token?: string; email?: string; days?: number; coupon?: string } = {}) {
  const api = await apiContext(opts.token);
  const res = await api.post("bookings", {
    data: {
      trip_slug: BUGGY.slug,
      nationality_code: "IT",
      booking_type: "private",
      travel_date: isoDate(opts.days ?? 25),
      adults: 1,
      children: 0,
      child_ages: [],
      units: [{ unit_option_id: await singleBuggyId(), quantity: 1 }],
      addons: [],
      lead: { name: "E2E Cancellation", email: opts.email ?? "e2e.cancel@example.test", phone: "+39 333 765 4321" },
      travelers: [{ first_name: "E2E", last_name: "Cancellation", type: "adult" }],
      notes: "E2E automated test booking, please ignore.",
      ...(opts.coupon ? { coupon_code: opts.coupon } : {}),
    },
  });
  expect(res.status(), await res.text()).toBe(201);
  const data = (await res.json()).data as { reference: string; access_token?: string };
  await api.dispose();
  return data;
}

/** Admin booking id for a reference (the admin routes use ids). */
export async function adminBookingId(page: Page, reference: string): Promise<number> {
  const res = await page.request.get(`${ADMIN_URL}/bookings/data`, {
    params: { draw: 1, start: 0, length: 5, "search[value]": reference },
    headers: { "X-Requested-With": "XMLHttpRequest", Accept: "application/json" },
  });
  expect(res.status()).toBe(200);
  const row = ((await res.json()).data as { id: number; reference: string }[]).find((r) => r.reference.includes(`>${reference}<`) || r.reference === reference);
  expect(row, `admin booking row for ${reference}`).toBeTruthy();
  return row!.id;
}

/** Confirmation dialog of data-confirm forms / confirmAction(). */
export async function confirmDialog(page: Page, ok: string | RegExp) {
  const dialog = page.getByRole("dialog").filter({ has: page.getByRole("button", { name: ok }) }).last();
  await expect(dialog).toBeVisible();
  await dialog.getByRole("button", { name: ok }).click();
}

export async function adminFlash(page: Page, text: RegExp = /saved|updated|created|approved|rejected|declined|blocked|freed|unblocked|deactivated/i) {
  await expect(page.getByRole("status").or(page.getByRole("alert")).filter({ hasText: text }).first()).toBeVisible();
}

async function pickCombo(page: Page, box: Locator, name: string) {
  await box.click();
  await box.fill(name);
  await page.getByRole("option", { name: new RegExp(`${name}$`) }).first().click();
  await expect(box).toHaveValue(name);
}

/** Partner panel: EG client, 2 adults on 2 single buggies, up to the "Client & payment" step with the lead filled in. */
export async function partnerToClientStep(page: Page) {
  await page.goto("/en/partner/new");
  await expect(page.getByRole("heading", { name: "New booking", level: 1 })).toBeVisible();
  await pickCombo(page, page.getByRole("combobox", { name: "Client nationality" }), "Egypt");
  const card = page.getByRole("listitem").filter({ has: page.getByRole("heading", { name: BUGGY.title }) });
  await card.getByRole("button", { name: `Select ${BUGGY.title}` }).click();
  const units = page.getByRole("region", { name: "Choose buggy" });
  // Default party: 2 adults -> two single buggies.
  await units.getByRole("button", { name: `Add one: ${BUGGY.single}` }).click();
  await units.getByRole("button", { name: `Add one: ${BUGGY.single}` }).click();
  const summary = page.getByRole("region", { name: "Price summary" });
  await expect(summary.getByRole("listitem").filter({ hasText: BUGGY.single })).toContainText("× 2");
  await summary.getByRole("button", { name: "Continue" }).click();
  await expect(page.getByRole("heading", { name: "Client & payment", level: 1 })).toBeVisible();
  const lead = page.getByRole("region", { name: "Lead client" });
  await lead.getByRole("textbox", { name: "First name" }).fill("E2E");
  await lead.getByRole("textbox", { name: "Last name" }).fill("Coupon");
  await lead.getByRole("textbox", { name: "Email" }).fill("e2e.partner.coupon@example.test");
  await lead.getByRole("textbox", { name: "Phone (WhatsApp)" }).fill("+20 100 000 0000");
  const others = page.getByRole("region", { name: "Other travellers" });
  if (await others.isVisible().catch(() => false)) {
    await others.getByRole("textbox", { name: "First name" }).fill("E2E");
    await others.getByRole("textbox", { name: "Last name" }).fill("Second");
  }
  await page.getByRole("radio", { name: /^Bank transfer or cash deposit/ }).check();
  await page.getByRole("radio", { name: /^Voucher without prices/ }).check();
  await page.getByRole("textbox", { name: "Notes for our team (optional)" }).fill("E2E automated partner booking, please ignore.");
  await page.getByRole("checkbox", { name: /I confirm the client details/ }).check();
}

export async function partnerConfirm(page: Page) {
  const created = page.waitForResponse((r) => /\/partner\/bookings(\?|$)/.test(r.url()) && r.request().method() === "POST");
  await page.getByRole("button", { name: "Confirm booking" }).click();
  return created;
}

/** Runs `fn` in a fresh admin browser context (saved admin session). */
export async function asAdmin<T>(browser: Browser, fn: (page: Page) => Promise<T>): Promise<T> {
  const context = await browser.newContext({ storageState: authFile("admin") });
  const page = await context.newPage();
  try {
    return await fn(page);
  } finally {
    await context.close();
  }
}

/** Admin booking page -> "Record payment" with the prefilled deposit amount; returns the amount (EUR). */
export async function recordDeposit(browser: Browser, reference: string): Promise<number> {
  return asAdmin(browser, async (admin) => {
    const id = await adminBookingId(admin, reference);
    await admin.goto(`${ADMIN_URL}/bookings/${id}`);
    const amount = Number(await admin.getByRole("spinbutton", { name: "Amount (EUR)" }).inputValue());
    await admin.getByRole("textbox", { name: "Note" }).first().fill("E2E deposit");
    await admin.getByRole("button", { name: "Record payment" }).click();
    await adminFlash(admin, /recorded|saved|payment/i);
    return amount;
  });
}
