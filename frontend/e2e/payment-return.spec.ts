import type { Page } from "@playwright/test";
import { expect, test } from "./fixtures";
import { createTripBooking, recordDeposit } from "./flows";
import { setNationality } from "./helpers";

/**
 * Return from the payment provider (F4): /{locale}/booking/{reference}?token=…&payment=success|cancel|pending|failed.
 * success/pending poll the booking while the webhook lands and hide "Pay now"; cancel/failed keep the booking payable.
 */

test.beforeEach(async ({ context }) => setNationality(context, "IT"));

const banner = (page: Page, kind: string) => page.locator(`[data-payment-return="${kind}"]`);

async function guestBooking() {
  const { reference, access_token } = await createTripBooking({ email: `e2e.payreturn.${Date.now()}@example.test` });
  return { reference, token: access_token! };
}

test("cancel: banner, booking still payable, dismiss clears ?payment", async ({ page }) => {
  const { reference, token } = await guestBooking();
  await page.goto(`/en/booking/${reference}?token=${token}&payment=cancel`);
  await expect(banner(page, "cancel")).toContainText("Payment cancelled");
  await expect(page.getByRole("heading", { name: "Pay now" })).toBeVisible();
  await banner(page, "cancel").getByRole("button", { name: "Dismiss" }).click();
  await expect(banner(page, "cancel")).toHaveCount(0);
  expect(new URL(page.url()).searchParams.get("payment")).toBeNull();
  expect(new URL(page.url()).searchParams.get("token")).toBe(token);
});

test("failed: alert and the payment methods stay available", async ({ page }) => {
  const { reference, token } = await guestBooking();
  await page.goto(`/it/booking/${reference}?token=${token}&payment=failed`);
  await expect(page.getByRole("alert").filter({ hasText: "Pagamento non completato" })).toBeVisible();
  await expect(page.getByRole("heading", { name: "Paga ora" })).toBeVisible();
});

test("success before the webhook: confirming + polling, then 'being processed' without a second Pay now", async ({ page }) => {
  test.setTimeout(90_000);
  const { reference, token } = await guestBooking();
  let loads = 0;
  page.on("request", (r) => {
    if (r.method() === "GET" && new RegExp(`/bookings/${reference}\\?`).test(r.url())) loads += 1;
  });
  await page.goto(`/en/booking/${reference}?token=${token}&payment=success`);
  await expect(banner(page, "confirming")).toContainText("Confirming your payment");
  await expect(page.getByRole("heading", { name: "Pay now" })).toHaveCount(0);
  // The page polls a few times, then explains the payment is still being processed.
  await expect(banner(page, "pending")).toContainText("Payment being processed", { timeout: 40_000 });
  expect(loads, "booking polled while waiting for the webhook").toBeGreaterThanOrEqual(3);
  await expect(page.getByRole("heading", { name: "Pay now" })).toHaveCount(0);
  const before = loads;
  await banner(page, "pending").getByRole("button", { name: "Check again" }).click();
  await expect.poll(() => loads).toBeGreaterThan(before);
});

test("success after the payment landed: 'Payment received' and the confirmed booking", async ({ page, browser }) => {
  test.setTimeout(120_000);
  const { reference, token } = await guestBooking();
  await recordDeposit(browser, reference);
  await page.goto(`/en/booking/${reference}?token=${token}&payment=success`);
  await expect(banner(page, "success")).toContainText("Payment received");
  await expect(page.getByRole("heading", { name: "Booking confirmed", level: 1 })).toBeVisible();
  await expect(page.getByRole("heading", { name: "Pay now" })).toHaveCount(0);
  await expect(page.getByRole("region", { name: "Payments", exact: true }).locator('[data-payment-type="deposit"]')).toBeVisible();
});
