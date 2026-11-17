import { expect, test as teardown } from "@playwright/test";
import fs from "node:fs";
import { ADMIN_URL, authFile } from "./helpers";

/**
 * Safety net after the run (also when a test failed half-way): rejects pending AI suggestions created by
 * the suite (note "E2E automated test") and revokes every still-active MCP token whose name starts with "E2E".
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
});
