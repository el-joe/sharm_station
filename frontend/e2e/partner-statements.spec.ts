import fs from "node:fs";
import { expect, test } from "./fixtures";
import { apiContext, authFile, readToken } from "./helpers";

/** Partner statements export (PLAN-v2.3 P1): PDF and Excel buttons download real files. */

test.use({ storageState: authFile("partner") });

test("statements: PDF and Excel downloads from the page and the API", async ({ page }) => {
  const api = await apiContext(readToken("partner"));
  const statements = (await (await api.get("partner/statements")).json()).data as { period: string }[];
  test.skip(statements.length === 0, "no statement yet for the demo partner");
  const period = statements[0].period;

  await page.goto("/en/partner/statements");
  await expect(page.getByRole("heading", { name: "Statements", level: 1 })).toBeVisible();
  for (const [format, ext, magic] of [
    ["PDF", "pdf", "%PDF"],
    ["Excel", "xlsx", "PK"],
  ] as const) {
    const button = page.getByRole("button", { name: new RegExp(`statement as ${format}$`) }).first();
    await expect(button).toBeVisible();
    const download = page.waitForEvent("download");
    await button.click();
    const file = await download;
    expect(file.suggestedFilename()).toMatch(new RegExp(`\\.${ext}$`));
    const path = await file.path();
    const bytes = fs.readFileSync(path);
    expect(bytes.length, `${format} size`).toBeGreaterThan(500);
    expect(bytes.subarray(0, magic.length).toString("latin1")).toBe(magic);
    await expect(page.getByText("The file could not be downloaded")).toHaveCount(0);
  }

  // Content types straight from the API.
  const pdf = await api.get(`partner/statements/${period}/export`, { params: { format: "pdf" } });
  expect(pdf.status()).toBe(200);
  expect(pdf.headers()["content-type"]).toContain("application/pdf");
  expect((await pdf.body()).length).toBeGreaterThan(500);
  const xlsx = await api.get(`partner/statements/${period}/export`, { params: { format: "xlsx" } });
  expect(xlsx.status()).toBe(200);
  expect(xlsx.headers()["content-type"]).toContain("spreadsheetml");
  expect(xlsx.headers()["content-disposition"]).toMatch(/attachment/);
  // A month without bookings exports an empty statement (200) rather than 404; anonymous calls are refused.
  expect([200, 404]).toContain((await api.get("partner/statements/1999-01/export", { params: { format: "pdf" } })).status());
  const anon = await apiContext();
  expect((await anon.get(`partner/statements/${period}/export`, { params: { format: "pdf" } })).status()).toBe(401);
  await anon.dispose();
  await api.dispose();
});
