import { request as playwrightRequest, type Locator, type Page } from "@playwright/test";
import fs from "node:fs";
import { expect, test } from "./fixtures";
import { createTripBooking, partnerConfirm, partnerToClientStep, recordDeposit } from "./flows";
import { SITE_URL, apiContext, authFile, readToken, setNationality } from "./helpers";

/**
 * Voucher PDF (F4, GET /bookings/{reference}/voucher.pdf?prices=0|1&locale=&token=): 409 before the deposit,
 * then a download button on the booking page (guest token), the account detail (session through /api/proxy) and
 * the partner detail (with prices + client copy without prices). The end client of a partner booking never gets prices.
 */

test.beforeEach(async ({ context }) => setNationality(context, "IT"));

/** Clicks a voucher link, waits for the download and checks it is a PDF; returns the suggested file name. */
async function downloadPdf(page: Page, link: Locator): Promise<string> {
  const [download] = await Promise.all([page.waitForEvent("download"), link.click()]);
  const file = await download.path();
  expect(fs.readFileSync(file).subarray(0, 4).toString(), "a PDF file").toBe("%PDF");
  return download.suggestedFilename();
}

const voucherRegion = (page: Page) => page.getByRole("region", { name: "Voucher", exact: true });

test("guest booking link: no voucher before the deposit, PDF with prices once it is paid", async ({ page, browser }) => {
  test.setTimeout(120_000);
  const { reference, access_token } = await createTripBooking({ email: `e2e.voucher.${Date.now()}@example.test` });
  const api = await apiContext();
  expect((await api.get(`bookings/${reference}/voucher.pdf`, { params: { token: access_token!, prices: "1" } })).status(), "409 before the deposit").toBe(409);

  await page.goto(`/en/booking/${reference}?token=${access_token}`);
  await expect(page.getByText(reference).first()).toBeVisible();
  await expect(page.getByRole("heading", { name: "Pay now" })).toBeVisible();
  await expect(voucherRegion(page)).toHaveCount(0);

  await recordDeposit(browser, reference);
  const booking = (await (await api.get(`bookings/${reference}`, { params: { token: access_token! } })).json()).data;
  await api.dispose();
  expect(booking.voucher_available).toBe(true);

  await page.reload();
  const link = voucherRegion(page).getByRole("link", { name: "Download voucher (PDF)" });
  await expect(link).toBeVisible();
  const href = (await link.getAttribute("href"))!;
  expect(href).toContain(`/bookings/${reference}/voucher.pdf?`);
  expect(new URL(href).searchParams.get("prices")).toBe("1");
  expect(new URL(href).searchParams.get("token")).toBe(access_token);
  expect(await downloadPdf(page, link)).toBe(`voucher-${reference}.pdf`);

  // The deposit appears in the payment history with the paid amount.
  const payments = page.getByRole("region", { name: "Payments", exact: true });
  await expect(payments.locator('[data-payment-type="deposit"]')).toContainText("Deposit");
  await expect(payments.locator('[data-payment-type="deposit"]')).toContainText("Paid");
});

test.describe("customer", () => {
  test.use({ storageState: authFile("customer") });

  test("account booking detail: voucher with prices through the session proxy", async ({ page, browser }) => {
    test.setTimeout(120_000);
    const { reference } = await createTripBooking({ token: readToken("customer"), email: "customer@sharmstation.test" });
    await recordDeposit(browser, reference);

    await page.goto(`/en/account/bookings/${reference}`);
    await expect(page.getByText(reference).first()).toBeVisible();
    const link = voucherRegion(page).getByRole("link", { name: "Download voucher (PDF)" });
    await expect(link).toHaveAttribute("href", new RegExp(`^/api/proxy/bookings/${reference}/voucher\\.pdf\\?prices=1&locale=en`));
    expect(await downloadPdf(page, link)).toBe(`voucher-${reference}.pdf`);

    // Without the session cookie the proxy gets no access (the API hides the booking).
    const anonymous = await playwrightRequest.newContext({ storageState: { cookies: [], origins: [] } });
    const refused = await anonymous.get(`${SITE_URL}${await link.getAttribute("href")}`);
    expect(refused.status()).toBe(404);
    await anonymous.dispose();
  });
});

test.describe("partner", () => {
  test.use({ storageState: authFile("partner") });

  test("partner booking detail: voucher with prices and client copy without prices", async ({ page, browser }) => {
    test.setTimeout(150_000);
    await partnerToClientStep(page);
    const res = await partnerConfirm(page);
    expect(res.status(), await res.text()).toBe(201);
    const created = (await res.json()).data as { reference: string; access_token?: string };
    const reference = created.reference;

    await page.goto(`/en/partner/bookings/${reference}`);
    await expect(page.getByRole("heading", { name: `Booking ${reference}`, level: 1 })).toBeVisible();
    await expect(page.getByText("The PDF voucher is available once the deposit has been paid.")).toBeVisible();

    await recordDeposit(browser, reference);
    await page.reload();
    const voucher = voucherRegion(page);
    const withPrices = voucher.getByRole("link", { name: "Voucher with prices (PDF)" });
    const clientCopy = voucher.getByRole("link", { name: "Client copy without prices (PDF)" });
    await expect(withPrices).toHaveAttribute("href", new RegExp(`^/api/proxy/bookings/${reference}/voucher\\.pdf\\?prices=1`));
    await expect(clientCopy).toHaveAttribute("href", new RegExp(`^/api/proxy/bookings/${reference}/voucher\\.pdf\\?prices=0`));
    expect(await downloadPdf(page, withPrices)).toBe(`voucher-${reference}.pdf`);
    expect(await downloadPdf(page, clientCopy)).toBe(`voucher-${reference}-no-prices.pdf`);
    // The printable versions stay available.
    await expect(voucher.getByRole("link", { name: "Voucher without prices" })).toHaveAttribute("href", /\/voucher\?prices=0$/);

    // The end client (booking link holder) never gets prices, even when asking for them.
    const token = created.access_token ?? (await (await (await apiContext(readToken("partner"))).get(`bookings/${reference}`)).json()).data.access_token;
    if (token) {
      const api = await apiContext();
      const client = await api.get(`bookings/${reference}/voucher.pdf`, { params: { token, prices: "1" } });
      expect(client.status()).toBe(200);
      expect(client.headers()["content-disposition"]).toContain(`voucher-${reference}-no-prices.pdf`);
      await api.dispose();
    }
  });
});
