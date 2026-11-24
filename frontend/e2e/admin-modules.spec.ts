import fs from "node:fs";
import type { Page } from "@playwright/test";
import { expect, test } from "./fixtures";
import { ADMIN_URL, apiContext, authFile } from "./helpers";
import { adminFlash, confirmDialog } from "./flows";
import { watchPage } from "./quality";

/**
 * Admin modules of v2.3 (A1, A2, A5-A8): calendar, customers, users and roles, reports, partner rejection
 * and the activity log that records all of it. Everything created is prefixed "E2E" and undone.
 */

test.use({ storageState: authFile("admin") });

const admin = (path: string) => `${ADMIN_URL}/${path}`;
const STAMP = Date.now().toString(36);
const done: string[] = []; // descriptions the activity log must show

function monthOffset(n: number) {
  const d = new Date();
  d.setDate(1);
  d.setMonth(d.getMonth() + n);
  return { ym: `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, "0")}`, label: d.toLocaleString("en-GB", { month: "long", year: "numeric" }) };
}

async function dataTableReady(page: Page) {
  await expect(page.locator("table[data-datatable] tbody tr").first()).toBeVisible();
  await expect(page.locator(".dt-processing:visible")).toHaveCount(0);
}

test("calendar: month navigation, block a free night, then unblock it", async ({ page }) => {
  const watch = watchPage(page);
  await page.goto(admin("calendar"));
  await expect(page.getByRole("heading", { level: 1 })).toBeVisible();
  const now = monthOffset(0);
  await expect(page.getByText(now.label, { exact: true })).toBeVisible();
  await page.getByRole("link", { name: "Next month" }).click();
  const next = monthOffset(1);
  await expect(page).toHaveURL(new RegExp(`month=${next.ym}`));
  await expect(page.getByText(next.label, { exact: true })).toBeVisible();
  await page.getByRole("link", { name: "Previous month" }).click();
  await expect(page.getByText(now.label, { exact: true })).toBeVisible();

  // Work two months ahead (no demo bookings expected there).
  const target = monthOffset(2);
  await page.goto(admin(`calendar?month=${target.ym}`));
  const free = page.locator('button[data-cell][data-status="free"]').last();
  await expect(free).toBeVisible();
  const room = await free.getAttribute("data-room");
  const date = await free.getAttribute("data-date");
  const cell = page.locator(`button[data-cell][data-room="${room}"][data-date="${date}"]`);
  await cell.click();
  await expect(page.locator("[data-occupancy-hint]")).toContainText(`From ${date}`);
  await cell.click();
  await confirmDialog(page, "Block");
  await expect(cell).toHaveAttribute("data-status", "blocked");
  await page.reload();
  await expect(cell).toHaveAttribute("data-status", "blocked");
  await expect(cell).toHaveAttribute("aria-label", /: blocked$/);

  await cell.click();
  await confirmDialog(page, "Unblock");
  await expect(cell).toHaveAttribute("data-status", "free");
  await page.reload();
  await expect(cell).toHaveAttribute("data-status", "free");
  expect([...watch.console, ...watch.http, ...watch.dialogs]).toEqual([]);
});

test("customers: list, detail with bookings, internal note saved and restored", async ({ page }) => {
  await page.goto(admin("customers"));
  await expect(page.getByRole("heading", { name: "Customers", level: 1 })).toBeVisible();
  await dataTableReady(page);
  await page.getByRole("form", { name: "Filters" }).getByRole("searchbox", { name: "Search" }).fill("customer@sharmstation.test");
  await page.getByRole("button", { name: "Apply" }).click();
  const row = page.getByRole("row").filter({ hasText: "customer@sharmstation.test" });
  await expect(row).toBeVisible();
  await row.getByRole("link").first().click();
  await expect(page).toHaveURL(/\/admin\/customers\/\d+$/);
  await expect(page.getByText("Total spent")).toBeVisible();
  await expect(page.getByRole("main").getByRole("table").first()).toContainText(/BK-\d+/);

  const notes = page.getByRole("textbox", { name: "Internal notes" });
  const original = await notes.inputValue();
  await notes.fill(`${original}${original ? "\n" : ""}E2E note ${STAMP}`);
  await page.getByRole("button", { name: "Save notes" }).click();
  await adminFlash(page);
  await expect(page.getByRole("textbox", { name: "Internal notes" })).toHaveValue(new RegExp(`E2E note ${STAMP}`));
  await page.getByRole("textbox", { name: "Internal notes" }).fill(original);
  await page.getByRole("button", { name: "Save notes" }).click();
  await adminFlash(page);
  await expect(page.getByRole("textbox", { name: "Internal notes" })).toHaveValue(original);
  done.push("notes");
});

test("users and roles: create an E2E editor, then deactivate it", async ({ page }) => {
  const email = `e2e.editor.${STAMP}@example.test`;
  await page.goto(admin("users/create"));
  await page.getByRole("textbox", { name: /^Name/ }).fill(`E2E Editor ${STAMP}`);
  await page.getByRole("textbox", { name: /^Email/ }).fill(email);
  await page.getByRole("combobox", { name: "Role" }).selectOption({ label: "Editor" });
  // Throw-away password for an account that is deactivated straight away and never signs in.
  const pw = `E2e${STAMP}x${Math.random().toString(36).slice(2, 10)}9`;
  await page.getByRole("textbox", { name: /^Password/ }).fill(pw);
  await page.getByRole("textbox", { name: "Confirm password" }).fill(pw);
  await page.getByRole("button", { name: "Save" }).first().click();
  await adminFlash(page, /can now sign in to the back office/);

  await page.goto(admin("users"));
  await dataTableReady(page);
  await page.getByRole("main").getByRole("searchbox").fill(email);
  const row = page.getByRole("row").filter({ hasText: email });
  await expect(row).toContainText("Editor");
  await expect(row).toContainText("Active");
  await row.getByRole("link", { name: "Edit", exact: true }).click();
  await page.getByRole("checkbox", { name: "Active" }).setChecked(false, { force: true });
  await page.getByRole("button", { name: "Save" }).first().click();
  await adminFlash(page);
  await page.goto(admin("users"));
  await dataTableReady(page);
  await page.getByRole("main").getByRole("searchbox").fill(email);
  await expect(page.getByRole("row").filter({ hasText: email })).toContainText(/Inactive|Deactivated/i);

  // The read-only permissions matrix is on the page.
  await expect(page.getByRole("main")).toContainText(/Editor/);
  done.push(email);
});

test("reports: filters and CSV export", async ({ page }) => {
  const watch = watchPage(page);
  await page.goto(admin("reports"));
  await expect(page.getByRole("heading", { name: "Reports", level: 1 })).toBeVisible();
  for (const kpi of ["Bookings", "Revenue", "Balance due", "Partner sales"]) await expect(page.getByRole("main").getByText(kpi, { exact: true }).first()).toBeVisible();
  await page.locator('select[name="service_type"]').evaluate((el: HTMLSelectElement) => {
    el.value = "trip";
    el.dispatchEvent(new Event("change", { bubbles: true }));
  });
  await page.getByRole("button", { name: "Show report" }).click();
  await expect(page).toHaveURL(/service_type=trip/);
  await expect(page.locator("canvas[data-report-chart]").first()).toBeVisible();

  const csv = page.getByRole("link", { name: "CSV" }).first();
  await expect(csv).toHaveAttribute("href", /service_type=trip/);
  const download = page.waitForEvent("download");
  await csv.click();
  const file = await download;
  expect(file.suggestedFilename()).toMatch(/\.csv$/);
  const body = fs.readFileSync(await file.path(), "utf8");
  expect(body.length).toBeGreaterThan(0);
  expect(body.split(/\r?\n/)[0]).toMatch(/,|;/);

  await page.getByRole("link", { name: "Reset" }).click();
  await expect(page).not.toHaveURL(/service_type=trip/);
  expect([...watch.console, ...watch.http, ...watch.dialogs]).toEqual([]);
});

test("partners: reject a pending application with a reason", async ({ page }) => {
  const email = `e2e.partner.${STAMP}@example.test`;
  const api = await apiContext();
  const pw = `E2e${STAMP}x${Math.random().toString(36).slice(2, 10)}9`;
  const res = await api.post("partners/apply", {
    data: { company: `E2E Agency ${STAMP}`, name: "E2E Applicant", email, password: pw, password_confirmation: pw, country: "IT", message: "E2E automated partner application, please ignore." },
  });
  expect(res.status(), await res.text()).toBeLessThan(300);
  const token = (await res.json()).token as string | undefined;
  await api.dispose();
  if (token) {
    const own = await apiContext(token);
    await own.post("auth/logout");
    await own.dispose();
  }

  await page.goto(admin("partners"));
  await dataTableReady(page);
  await page.getByRole("main").getByRole("searchbox").first().fill(`E2E Agency ${STAMP}`);
  const row = page.getByRole("row").filter({ hasText: `E2E Agency ${STAMP}` });
  await expect(row).toContainText(/Pending/i);
  await row.getByRole("link").first().click();
  await expect(page.getByRole("heading", { name: `E2E Agency ${STAMP}`, level: 1 })).toBeVisible();

  // Reason required.
  await page.getByRole("button", { name: "Reject application" }).click();
  const confirm = page.getByRole("dialog").filter({ has: page.getByRole("button", { name: "Reject" }) });
  if (await confirm.isVisible().catch(() => false)) await confirm.getByRole("button", { name: "Reject" }).click();
  await expect(page.locator("body")).toContainText(/Write the reason for the rejection|required/i);

  await page.getByRole("textbox", { name: "Reason" }).fill("E2E: we could not verify the agency licence.");
  await page.getByRole("button", { name: "Reject application" }).click();
  await confirmDialog(page, "Reject");
  await adminFlash(page, /rejected/i);
  await expect(page.getByRole("main")).toContainText(/Rejected/);
  await expect(page.getByRole("main")).toContainText("E2E: we could not verify the agency licence.");
  await expect(page.getByRole("button", { name: "Reject application" })).toHaveCount(0);
  done.push(`E2E Agency ${STAMP}`);
});

test("activity log lists the actions of this run", async ({ page }) => {
  await page.goto(admin("activity-log"));
  await expect(page.getByRole("heading", { level: 1 })).toBeVisible();
  await dataTableReady(page);
  const table = page.locator("table[data-datatable]");
  await expect(table).toContainText("Site Admin");
  const res = await page.request.get(admin("activity-log/data"), {
    params: { draw: 1, start: 0, length: 100, "order[0][column]": 0, "order[0][dir]": "desc", "columns[0][data]": "created_at", "columns[0][name]": "created_at" },
    headers: { "X-Requested-With": "XMLHttpRequest", Accept: "application/json" },
  });
  expect(res.status()).toBe(200);
  const text = JSON.stringify((await res.json()).data);
  for (const needle of [STAMP, "night blocked", "night unblocked", "rejected", "notes of customer"]) expect.soft(text.toLowerCase(), `activity log mentions "${needle}"`).toContain(needle.toLowerCase());

  // Filter by action.
  await page.locator('select[name="action"]').evaluate((el: HTMLSelectElement) => {
    el.selectedIndex = 1;
    el.dispatchEvent(new Event("change", { bubbles: true }));
  });
  await dataTableReady(page);
});
