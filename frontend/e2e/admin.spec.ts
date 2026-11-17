import type { APIRequestContext, Page } from "@playwright/test";
import { request as playwrightRequest } from "@playwright/test";
import { expect, test } from "./fixtures";
import { ADMIN_URL, ACCOUNTS, BUGGY, MCP_URL, apiContext, authFile, isoDate, readToken } from "./helpers";

/**
 * Admin panel (Laravel Blade): commissions (CR-11), partner override, nationality groups, price matrix,
 * AI suggestions and MCP tokens (CR-14), role restrictions. Tests that change settings restore them.
 */

const admin = (path: string) => `${ADMIN_URL}/${path}`;

async function flashOk(page: Page) {
  await expect(page.getByRole("status").or(page.getByRole("alert")).filter({ hasText: /saved|updated|created|revoked|rejected/i }).first()).toBeVisible();
}

/** Partner quote for one Single buggy (EG) through the partner API. */
async function partnerBuggyQuote(api: APIRequestContext) {
  const trip = (await (await (await apiContext()).get(`trips/${BUGGY.slug}`, { params: { nationality: "EG" } })).json()).data;
  const single = trip.unit_options.find((u: { name: string }) => u.name === BUGGY.single);
  const res = await api.post("partner/quotes", {
    data: {
      product_type: "trip",
      product_id: trip.id,
      slug: trip.slug,
      nationality_code: "EG",
      booking_type: "private",
      travel_date: isoDate(3),
      adults: 1,
      children: 0,
      child_ages: [],
      units: [{ unit_option_id: single.id, quantity: 1 }],
      addons: [],
    },
  });
  expect(res.status(), await res.text()).toBe(200);
  return (await res.json()).data as { commission_percent: number; retail_total: number; total: number; commission_source: string };
}

test.describe("super admin", () => {
  test.use({ storageState: authFile("admin") });

  test("sign-in page rejects a wrong password", async ({ browser }) => {
    const context = await browser.newContext({ storageState: { cookies: [], origins: [] } });
    const page = await context.newPage();
    await page.goto(admin("login"));
    await page.getByLabel("Email").fill(ACCOUNTS.agent);
    await page.getByLabel("Password").fill("E2E-wrong-password");
    await page.getByRole("button", { name: "Sign in" }).click();
    await expect(page).toHaveURL(/\/admin\/login/);
    await expect(page.locator("body")).toContainText(/credentials|incorrect|invalid/i);
    await context.close();
  });

  test("dashboard", async ({ page }) => {
    await page.goto(admin("dashboard"));
    await expect(page.getByRole("heading", { name: "Overview", level: 1 })).toBeVisible();
    await expect(page.getByRole("heading", { name: "Recent bookings" })).toBeVisible();
    await expect(page.getByRole("complementary", { name: "Main navigation" })).toBeVisible();
  });

  test("Settings → Commissions: change one rate and restore it", async ({ page }) => {
    await page.goto(admin("settings/commissions"));
    const transfers = page.getByRole("spinbutton", { name: "Transfers" });
    const original = await transfers.inputValue();
    const changed = String(Number(original) === 9 ? 7 : 9);
    await transfers.fill(changed);
    await page.getByRole("button", { name: "Save", exact: true }).click();
    await flashOk(page);
    await expect(page.getByRole("spinbutton", { name: "Transfers" })).toHaveValue(changed);

    // Invalid value is rejected.
    await page.getByRole("spinbutton", { name: "Transfers" }).fill("150");
    await page.getByRole("button", { name: "Save", exact: true }).click();
    await expect(page.locator("body")).toContainText(/100/);

    await page.goto(admin("settings/commissions"));
    await page.getByRole("spinbutton", { name: "Transfers" }).fill(original);
    await page.getByRole("button", { name: "Save", exact: true }).click();
    await flashOk(page);
    await expect(page.getByRole("spinbutton", { name: "Transfers" })).toHaveValue(original);
    await expect(page.getByRole("listitem").filter({ hasText: "Accommodation" }).filter({ hasText: "No commission" })).toBeVisible();
  });

  test("partner profile → Commissions override set and clear; partner quote follows", async ({ page }) => {
    const partnerApi = await apiContext(readToken("partner"));
    const before = await partnerBuggyQuote(partnerApi);

    await page.goto(admin("partners/1"));
    await page.getByRole("tab", { name: "Commissions" }).click();
    const panel = page.getByRole("tabpanel", { name: "Commissions" });
    const row = panel.getByRole("row").filter({ has: page.getByRole("rowheader", { name: "Single-day trips" }) });
    const input = row.getByRole("spinbutton", { name: "Single-day trips override" });
    const original = await input.inputValue();
    const override = before.commission_percent === 20 ? "19" : "20";

    await input.fill(override);
    await panel.getByRole("button", { name: "Save commissions" }).click();
    await flashOk(page);
    await page.getByRole("tab", { name: "Commissions" }).click();
    await expect(row.getByRole("cell").last()).toContainText(`${override}%`);
    await expect(row.getByRole("cell").last()).toContainText("Override");

    const during = await partnerBuggyQuote(partnerApi);
    expect(during.commission_percent).toBe(Number(override));
    expect(during.total).toBeCloseTo(Math.round(during.retail_total * (1 - Number(override) / 100) * 100) / 100, 2);
    expect(during.retail_total).toBe(before.retail_total);

    // Clear (empty = standard rate) or restore the original override.
    await page.getByRole("tab", { name: "Commissions" }).click();
    await input.fill(original);
    await panel.getByRole("button", { name: "Save commissions" }).click();
    await flashOk(page);
    await page.getByRole("tab", { name: "Commissions" }).click();
    if (original === "") await expect(row.getByRole("cell").last()).toContainText("Standard");

    const after = await partnerBuggyQuote(partnerApi);
    expect(after.commission_percent).toBe(before.commission_percent);
    expect(after.total).toBe(before.total);
    await partnerApi.dispose();
  });

  test("nationality groups page and group edit form with countries", async ({ page }) => {
    await page.goto(admin("nationality-groups"));
    await expect(page.getByRole("heading", { name: "Nationality groups", level: 1 })).toBeVisible();
    const table = page.getByRole("table").first();
    for (const group of ["Egyptian", "Arab countries", "Europe", "Rest of world"]) await expect(table.getByRole("link", { name: group })).toBeVisible();
    await expect(table.getByRole("row").filter({ hasText: "Rest of world" })).toContainText("Default");

    await table.getByRole("link", { name: "Europe" }).click();
    await expect(page.getByRole("heading", { name: "Edit group: Europe" })).toBeVisible();
    await expect(page.getByRole("textbox", { name: /^Name/ }).first()).toHaveValue("Europe");
    await expect(page.getByRole("listitem", { name: "Italy (IT)" })).toBeVisible();
    await expect(page.getByRole("listitem", { name: "Germany (DE)" })).toBeVisible();
  });

  test("trip editor → Price matrix saves, and an overlapping season of the same group is rejected", async ({ page }) => {
    const trip = (await (await (await apiContext()).get(`trips/${BUGGY.slug}`)).json()).data;
    await page.goto(admin(`trips/${trip.id}/edit`));
    await page.getByRole("tab", { name: "Price matrix" }).click();
    const panel = page.getByRole("tabpanel", { name: "Price matrix" });
    await expect(panel.getByRole("spinbutton", { name: "Egyptian Single buggy (EUR)" })).toHaveValue(/\d/);
    const seasonsBefore = await panel.getByRole("textbox", { name: "Season name", exact: true }).count();

    // Save unchanged.
    await panel.getByRole("button", { name: "Save price matrix" }).click();
    await flashOk(page);

    // Add an Egyptian season inside the Egyptian high season (1 Nov – 19 Dec).
    await page.getByRole("tab", { name: "Price matrix" }).click();
    await panel.getByRole("button", { name: "Add season" }).click();
    const rows = panel.locator("[data-repeater-list] > *");
    const row = rows.last();
    await row.getByRole("textbox", { name: "Season name", exact: true }).fill("E2E overlap");
    await row.getByRole("combobox", { name: "Nationality group" }).selectOption({ label: "Egyptian" });
    for (const [selector, value] of [["[data-season-from]", "2026-12-01"], ["[data-season-to]", "2026-12-10"]] as const) {
      await row.locator(selector).evaluate((el, v) => {
        const fp = (el as HTMLInputElement & { _flatpickr?: { setDate: (d: string, t: boolean) => void } })._flatpickr;
        if (fp) fp.setDate(v, true);
        else (el as HTMLInputElement).value = v;
      }, value);
    }
    for (const field of await row.getByRole("spinbutton").all()) await field.fill("1");
    await panel.getByRole("button", { name: "Save price matrix" }).click();

    await page.getByRole("tab", { name: "Price matrix" }).click();
    await expect(page.getByRole("alert").filter({ hasText: /overlaps/i }).first()).toBeVisible();

    // Nothing was saved: reload without the old input.
    await page.goto(admin(`trips/${trip.id}/edit`));
    await page.getByRole("tab", { name: "Price matrix" }).click();
    await expect(panel.getByRole("textbox", { name: "Season name", exact: true })).toHaveCount(seasonsBefore);
    const names = await panel.getByRole("textbox", { name: "Season name", exact: true }).evaluateAll((els) => els.map((e) => (e as HTMLInputElement).value));
    expect(names).not.toContain("E2E overlap");
  });

  test("AI suggestions inbox loads", async ({ page }) => {
    await page.goto(admin("ai-suggestions"));
    await expect(page.getByRole("heading", { name: "AI suggestions", level: 1 })).toBeVisible();
    await expect(page.getByRole("navigation", { name: "Suggestion status" })).toBeVisible();
    await expect(page.getByRole("table")).toBeVisible();
  });

  test("MCP: create token, call the server, suggestion in the inbox, reject, revoke", async ({ page }) => {
    const name = `E2E token ${Date.now()}`;
    await page.goto(admin("settings/mcp"));
    await page.getByRole("textbox", { name: "New token name" }).fill(name);
    await page.getByRole("button", { name: "Create token" }).click();
    const dialog = page.getByRole("dialog", { name: "Token created" });
    await expect(dialog).toBeVisible();
    const token = await dialog.getByRole("textbox", { name: "Your token" }).inputValue();
    expect(token.length).toBeGreaterThan(20);
    await dialog.getByRole("button", { name: /close|done|ok/i }).first().click().catch(() => page.keyboard.press("Escape"));

    // Shown once: a reload does not reveal it again.
    await page.reload();
    await expect(page.getByRole("dialog", { name: "Token created" })).toBeHidden();
    expect(await page.content()).not.toContain(token);
    const tokenRow = page.getByRole("row").filter({ hasText: name });
    await expect(tokenRow).toContainText("Active");

    let suggestionId: number | null = null;
    const mcp = await playwrightRequest.newContext({
      extraHTTPHeaders: { Authorization: `Bearer ${token}`, Accept: "application/json, text/event-stream", "Content-Type": "application/json" },
    });
    try {
      let session: string | undefined;
      let id = 0;
      const rpc = async (method: string, params: Record<string, unknown> = {}, notification = false) => {
        const res = await mcp.post(MCP_URL, {
          headers: session ? { "Mcp-Session-Id": session } : {},
          data: notification ? { jsonrpc: "2.0", method, params } : { jsonrpc: "2.0", id: ++id, method, params },
        });
        session = res.headers()["mcp-session-id"] ?? session;
        const text = await res.text();
        expect(res.status(), `${method}: ${text}`).toBeLessThan(300);
        if (notification || !text) return null;
        const json = text.startsWith("{") ? JSON.parse(text) : JSON.parse(text.split("\n").find((l) => l.startsWith("data:"))!.slice(5));
        expect(json.error, `${method} error`).toBeUndefined();
        return json.result;
      };
      const toolJson = (result: { content: { type: string; text: string }[]; isError?: boolean }) => {
        expect(result.isError ?? false, result.content?.[0]?.text).toBe(false);
        const text = result.content.find((c) => c.type === "text")!.text;
        try {
          return JSON.parse(text);
        } catch {
          return text;
        }
      };

      const init = await rpc("initialize", { protocolVersion: "2025-06-18", capabilities: {}, clientInfo: { name: "e2e", version: "1.0" } });
      expect(init.serverInfo.name).toMatch(/sharm/i);
      await rpc("notifications/initialized", {}, true);

      const tools = await rpc("tools/list");
      const names = tools.tools.map((t: { name: string }) => t.name);
      for (const tool of ["list_trips", "get_trip", "suggest_seo", "create_accommodation_suggestion", "list_transfers"]) expect(names).toContain(tool);

      const trips = toolJson(await rpc("tools/call", { name: "list_trips", arguments: {} }));
      const serialized = JSON.stringify(trips);
      expect(serialized).toContain(BUGGY.title);
      expect(serialized).not.toMatch(/partner_price|lead_email|@sharmstation\.test/);
      const tripId = (await (await (await apiContext()).get(`trips/${BUGGY.slug}`)).json()).data.id;

      const suggestion = toolJson(
        await rpc("tools/call", {
          name: "suggest_seo",
          arguments: { subject_type: "trip", subject_id: tripId, en: { meta_title: "E2E Desert buggy safari in Sharm", meta_description: "E2E automated suggestion, reject me." }, notes: "E2E automated test" },
        }),
      );
      suggestionId = Number(suggestion.id ?? suggestion.suggestion_id ?? String(suggestion).match(/\d+/)?.[0]);
      expect(suggestionId).toBeGreaterThan(0);

      // The website is not changed.
      const seo = (await (await (await apiContext()).get(`trips/${BUGGY.slug}`)).json()).data.seo;
      expect(JSON.stringify(seo)).not.toContain("E2E Desert buggy safari");

      // The MCP token does not open the website API.
      const viaApi = await (await apiContext(token)).get("auth/me");
      expect(viaApi.status()).toBe(401);
    } finally {
      await mcp.dispose();
    }

    // Inbox → detail → reject with reason.
    await page.goto(admin("ai-suggestions"));
    await expect(page.getByRole("table").getByRole("row").filter({ hasText: "Desert buggy safari" }).first()).toBeVisible();
    await page.goto(admin(`ai-suggestions/${suggestionId}`));
    await expect(page.getByRole("heading", { level: 1 })).toHaveText(/SEO suggestion · Trip: Desert buggy safari/);
    await expect(page.getByRole("textbox", { name: "Meta title" }).first()).toHaveValue("E2E Desert buggy safari in Sharm");
    await page.getByRole("textbox", { name: "Rejection reason (required)" }).fill("E2E");
    await page.getByRole("button", { name: "Reject suggestion" }).click();
    await expect(page.locator("body")).toContainText(/rejected/i);
    await page.goto(admin(`ai-suggestions/${suggestionId}`));
    await expect(page.getByRole("textbox", { name: "Rejection reason (required)" })).toHaveCount(0);
    await expect(page.getByText("E2E", { exact: true })).toBeVisible();
    const seoAfter = (await (await (await apiContext()).get(`trips/${BUGGY.slug}`)).json()).data.seo;
    expect(JSON.stringify(seoAfter)).not.toContain("E2E Desert buggy safari");

    // Revoke the token.
    await page.goto(admin("settings/mcp"));
    const row = page.getByRole("row").filter({ hasText: name });
    await row.getByRole("button", { name: /revoke/i }).click();
    const confirm = page.getByRole("dialog").filter({ hasText: /Revoke token/i });
    if (await confirm.isVisible().catch(() => false)) await confirm.getByRole("button", { name: "Revoke" }).click();
    await expect(page.getByRole("row").filter({ hasText: name })).toContainText("Revoked");
    const revoked = await playwrightRequest.newContext();
    const res = await revoked.post(MCP_URL, {
      headers: { Authorization: `Bearer ${token}`, Accept: "application/json, text/event-stream", "Content-Type": "application/json" },
      data: { jsonrpc: "2.0", id: 1, method: "tools/list", params: {} },
    });
    expect(res.status()).toBe(401);
    await revoked.dispose();
  });
});

test.describe("editor role", () => {
  test.use({ storageState: authFile("editor") });

  test("editor cannot see or open Commissions settings", async ({ page }) => {
    await page.goto(admin("dashboard"));
    await expect(page.getByRole("heading", { level: 1 })).toBeVisible();
    const nav = page.getByRole("complementary", { name: "Main navigation" });
    for (const button of await nav.getByRole("button").filter({ hasNotText: /collapse/i }).all()) {
      if ((await button.getAttribute("aria-expanded")) === "false") await button.click();
    }
    await expect(nav.getByRole("link", { name: "Commissions" })).toHaveCount(0);
    await expect(nav.getByRole("link", { name: "MCP access" })).toHaveCount(0);
    const res = await page.goto(admin("settings/commissions"));
    expect(res?.status()).toBe(403);
  });
});
