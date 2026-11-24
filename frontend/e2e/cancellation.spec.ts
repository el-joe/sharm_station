import type { Browser, Page } from "@playwright/test";
import { expect, test } from "./fixtures";
import { ADMIN_URL, apiContext, authFile, money, readToken, setNationality } from "./helpers";
import { adminBookingId, adminFlash, confirmDialog, createTripBooking, partnerConfirm, partnerToClientStep } from "./flows";

/**
 * Cancellation requests (PLAN-v2.3 W4): website confirmation page (guest link), account booking detail
 * (customer) and partner booking detail -> pending -> admin decides (approve with the suggested refund /
 * decline with a note) -> the requester sees the decision and the button is gone.
 */

const REASON = "E2E automated cancellation request, please ignore.";

async function requestCancellation(page: Page) {
  const section = page.getByRole("region", { name: "Cancellation" });
  await expect(section).toBeVisible();
  const button = section.getByRole("button", { name: "Request cancellation" });
  await button.click();
  const dialog = page.getByRole("dialog", { name: /^Request cancellation of BK-/ });
  await expect(dialog).toBeVisible();
  // Validation: too short.
  await dialog.getByRole("textbox", { name: "Why do you want to cancel?" }).fill("E2E");
  await dialog.getByRole("button", { name: "Send request" }).click();
  await expect(dialog.getByText("Please write at least 5 characters.")).toBeVisible();
  await dialog.getByRole("textbox", { name: "Why do you want to cancel?" }).fill(REASON);
  const sent = page.waitForResponse((r) => r.url().includes("/cancellation-request") && r.request().method() === "POST");
  await dialog.getByRole("button", { name: "Send request" }).click();
  expect((await sent).status()).toBe(201);
  await expect(dialog).toBeHidden();
  await expect(section.getByRole("status")).toContainText("Your cancellation request has been sent");
  await expect(section.getByText("Cancellation requested")).toBeVisible();
  await expect(section.getByText(`Your reason: ${REASON}`)).toBeVisible();
  await expect(section.getByRole("button", { name: "Request cancellation" })).toHaveCount(0);
  return section;
}

async function asAdmin<T>(browser: Browser, fn: (page: Page) => Promise<T>): Promise<T> {
  const context = await browser.newContext({ storageState: authFile("admin") });
  const page = await context.newPage();
  try {
    return await fn(page);
  } finally {
    await context.close();
  }
}

/** Admin booking page: the request card shows the reason and the suggested refund; returns that amount. */
async function adminRequestCard(page: Page, reference: string) {
  const id = await adminBookingId(page, reference);
  await page.goto(`${ADMIN_URL}/bookings/${id}`);
  const card = page.locator("#cancellation");
  await expect(card).toBeVisible();
  await expect(card).toContainText("Cancellation request");
  await expect(card).toContainText(REASON);
  await expect(card).toContainText("Suggested refund");
  const suggested = money((await card.getByRole("definition").filter({ hasText: /€/ }).last().textContent()) ?? "");
  return { id, card, suggested };
}

test.beforeEach(async ({ context }) => setNationality(context, "IT"));

test("guest: confirmation page -> request -> admin approves with the suggested refund -> approved + refund", async ({ page, browser }) => {
  test.setTimeout(120_000);
  const { reference, access_token } = await createTripBooking({ email: `e2e.cancel.${Date.now()}@example.test` });

  // Record the deposit so the policy suggests a refund.
  const deposit = await asAdmin(browser, async (admin) => {
    const id = await adminBookingId(admin, reference);
    await admin.goto(`${ADMIN_URL}/bookings/${id}`);
    const amount = Number(await admin.getByRole("spinbutton", { name: "Amount (EUR)" }).inputValue());
    await admin.getByRole("textbox", { name: "Note" }).first().fill("E2E deposit");
    await admin.getByRole("button", { name: "Record payment" }).click();
    await adminFlash(admin, /recorded|saved|payment/i);
    return amount;
  });
  expect(deposit).toBeGreaterThan(0);

  await page.goto(`/en/booking/${reference}?token=${access_token}`);
  await expect(page.getByText(reference).first()).toBeVisible();
  await requestCancellation(page);
  const api = await apiContext();
  const pending = (await (await api.get(`bookings/${reference}`, { params: { token: access_token! } })).json()).data;
  expect(pending.cancellation.status).toBe("pending");
  expect(pending.cancellation.can_request).toBe(false);

  const refund = await asAdmin(browser, async (admin) => {
    const { card, suggested } = await adminRequestCard(admin, reference);
    expect(suggested, "suggested refund (25 days before, deposit paid)").toBeGreaterThan(0);
    expect(suggested).toBeLessThanOrEqual(deposit);
    expect(Number(await card.getByRole("spinbutton", { name: "Refund to pay (EUR)" }).inputValue()), "refund field prefilled with the suggestion").toBeCloseTo(suggested, 2);
    await card.getByRole("button", { name: "Approve and cancel" }).click();
    await confirmDialog(admin, "Approve");
    await adminFlash(admin, /approved|cancelled/i);
    await expect(admin.locator("#cancellation")).toContainText(/approved/i);
    return suggested;
  });

  await page.reload();
  const section = page.getByRole("region", { name: "Cancellation" });
  await expect(section.getByText("Cancellation approved")).toBeVisible();
  await expect(section).toContainText(/Refund: €\s?[\d.,]+/);
  expect(money((await section.getByText(/^Refund:/).textContent()) ?? "")).toBeCloseTo(refund, 2);
  await expect(section.getByRole("button", { name: "Request cancellation" })).toHaveCount(0);
  const approved = (await (await api.get(`bookings/${reference}`, { params: { token: access_token! } })).json()).data;
  await api.dispose();
  expect(approved.cancellation.status).toBe("approved");
  expect(approved.cancellation.refund_amount).toBeCloseTo(refund, 2);
  expect(approved.status).toMatch(/cancel/);
});

test.describe("customer", () => {
  test.use({ storageState: authFile("customer") });

  test("account booking detail -> request -> admin declines with a note -> declined", async ({ page, browser }) => {
    const { reference } = await createTripBooking({ token: readToken("customer"), email: "customer@sharmstation.test" });
    await page.goto(`/en/account/bookings/${reference}`);
    await expect(page.getByText(reference).first()).toBeVisible();
    await requestCancellation(page);

    await asAdmin(browser, async (admin) => {
      const { card } = await adminRequestCard(admin, reference);
      await card.getByRole("textbox", { name: "Decline reason" }).fill("E2E declined: please ignore.");
      await card.getByRole("button", { name: "Decline request" }).click();
      await adminFlash(admin, /declined/i);
    });

    await page.reload();
    const section = page.getByRole("region", { name: "Cancellation" });
    await expect(section.getByText("Cancellation request declined")).toBeVisible();
    await expect(section).toContainText("E2E declined: please ignore.");
    await expect(section.getByRole("button", { name: "Request cancellation" })).toHaveCount(0);
  });
});

test.describe("partner", () => {
  test.use({ storageState: authFile("partner") });

  test("partner booking detail -> request -> admin approves -> approved", async ({ page, browser }) => {
    test.setTimeout(120_000);
    await partnerToClientStep(page);
    const res = await partnerConfirm(page);
    expect(res.status(), await res.text()).toBe(201);
    const reference = (await res.json()).data.reference as string;

    await page.goto(`/en/partner/bookings/${reference}`);
    await expect(page.getByRole("heading", { name: `Booking ${reference}`, level: 1 })).toBeVisible();
    await requestCancellation(page);

    await asAdmin(browser, async (admin) => {
      const { card } = await adminRequestCard(admin, reference);
      await expect(card).toContainText(/Requested .* by /);
      await card.getByRole("button", { name: "Approve and cancel" }).click();
      await confirmDialog(admin, "Approve");
      await adminFlash(admin, /approved|cancelled/i);
    });

    await page.reload();
    const section = page.getByRole("region", { name: "Cancellation" });
    await expect(section.getByText("Cancellation approved")).toBeVisible();
    await expect(section.getByRole("button", { name: "Request cancellation" })).toHaveCount(0);
    const api = await apiContext(readToken("partner"));
    const list = (await (await api.get("partner/bookings")).json()).data as { reference: string; status: string }[];
    await api.dispose();
    expect(list.find((b) => b.reference === reference)?.status).toMatch(/cancel/);
  });
});
