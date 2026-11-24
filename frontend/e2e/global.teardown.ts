import { expect, test as teardown } from "@playwright/test";
import fs from "node:fs";
import { ADMIN_URL, authFile } from "./helpers";

/**
 * Safety net after the run (also when a test failed half-way): rejects pending AI suggestions created by
 * the suite (note "E2E automated test"), revokes every still-active MCP token whose name starts with "E2E" and
 * deactivates every still-active coupon whose code starts with "E2E", E2E staff users (e2e.*@example.test)
 * and the customer accounts created by E2E partner applications.
 */
teardown.use({ storageState: fs.existsSync(authFile("admin")) ? authFile("admin") : { cookies: [], origins: [] } });

teardown("clean up E2E suggestions and MCP tokens", async ({ page }) => {
  await page.goto(`${ADMIN_URL}/dashboard`);
  teardown.skip(!/\/admin\/dashboard/.test(page.url()), "admin session not available");

  const res = await page.request.get(`${ADMIN_URL}/ai-suggestions/data?draw=1&start=0&length=100&status=pending`, {
    headers: { "X-Requested-With": "XMLHttpRequest", Accept: "application/json" },
  });
  const rows = res.ok() ? ((await res.json()).data as { payload?: { notes?: string }; created_by_token_id?: number; token?: { name?: string }; subject: string }[]) : [];
  for (const row of rows) {
    if (row.payload?.notes !== "E2E automated test" && !row.token?.name?.startsWith("E2E")) continue;
    const id = row.subject.match(/ai-suggestions\/(\d+)/)?.[1];
    if (!id) continue;
    await page.goto(`${ADMIN_URL}/ai-suggestions/${id}`);
    await page.getByRole("textbox", { name: "Rejection reason (required)" }).fill("E2E");
    await page.getByRole("button", { name: "Reject suggestion" }).click();
    await expect(page.locator("body")).toContainText(/rejected/i);
  }

  await page.goto(`${ADMIN_URL}/settings/mcp`);
  for (;;) {
    const row = page.getByRole("row").filter({ hasText: /^E2E/ }).filter({ has: page.getByRole("button", { name: /revoke/i }) }).first();
    if (!(await row.isVisible().catch(() => false))) break;
    await row.getByRole("button", { name: /revoke/i }).click();
    const confirm = page.getByRole("dialog").filter({ hasText: /Revoke token/i });
    if (await confirm.isVisible().catch(() => false)) await confirm.getByRole("button", { name: "Revoke" }).click();
    await page.waitForLoadState("networkidle");
  }

  const coupons = await page.request.get(`${ADMIN_URL}/coupons/data?draw=1&start=0&length=100`, { headers: { "X-Requested-With": "XMLHttpRequest", Accept: "application/json" } });
  const couponRows = coupons.ok() ? ((await coupons.json()).data as { id: number; code: string; is_active: string }[]) : [];
  for (const row of couponRows) {
    if (!/>E2E[A-Z0-9-]*</.test(row.code) || !/^\s*Active\s*$/i.test(row.is_active.replace(/<[^>]+>/g, ""))) continue;
    await page.goto(`${ADMIN_URL}/coupons/${row.id}/edit`);
    await page.getByRole("checkbox", { name: "Active" }).setChecked(false, { force: true });
    await page.getByRole("button", { name: "Save" }).first().click();
    await expect(page.getByRole("status").or(page.getByRole("alert")).filter({ hasText: /saved|updated/i }).first()).toBeVisible();
  }

  const active = (html: string) => /^\s*Active\s*$/i.test(html.replace(/<[^>]+>/g, ""));
  const dataRows = async (path: string) => {
    const res = await page.request.get(`${ADMIN_URL}/${path}/data?draw=1&start=0&length=100`, { headers: { "X-Requested-With": "XMLHttpRequest", Accept: "application/json" } });
    return res.ok() ? ((await res.json()).data as { id: string | number; email: string; is_active: string }[]) : [];
  };
  for (const user of await dataRows("users")) {
    if (!/^e2e\./.test(user.email) || !active(user.is_active)) continue;
    await page.goto(`${ADMIN_URL}/users/${user.id}/edit`);
    await page.getByRole("checkbox", { name: "Active" }).setChecked(false, { force: true });
    await page.getByRole("button", { name: "Save" }).first().click();
    await expect(page.getByRole("status").or(page.getByRole("alert")).filter({ hasText: /saved/i }).first()).toBeVisible();
  }
  for (const customer of await dataRows("customers")) {
    if (!/^e2e\.partner\./.test(customer.email) || !active(customer.is_active)) continue;
    await page.goto(`${ADMIN_URL}/customers/${customer.id}`);
    await page.getByRole("button", { name: "Deactivate" }).click();
    const confirm = page.getByRole("dialog").filter({ has: page.getByRole("button", { name: /Deactivate/ }) });
    if (await confirm.isVisible().catch(() => false)) await confirm.getByRole("button", { name: /Deactivate/ }).click();
    await expect(page.getByRole("button", { name: "Activate" })).toBeVisible();
  }
});
