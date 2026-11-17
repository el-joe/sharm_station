import { expect, type APIRequestContext, type BrowserContext, type Page, request as playwrightRequest } from "@playwright/test";
import fs from "node:fs";
import path from "node:path";

export const SITE_URL = process.env.E2E_SITE_URL ?? "http://sharm-station.loc";
export const API_URL = process.env.E2E_API_URL ?? "http://api.sharm-station.loc/api/v1";
export const API_ORIGIN = new URL(API_URL).origin;
export const ADMIN_URL = process.env.E2E_ADMIN_URL ?? "http://admin.sharm-station.loc/admin";
export const MCP_URL = process.env.E2E_MCP_URL ?? `${API_ORIGIN}/mcp`;
export const SITE_HOST = new URL(SITE_URL).hostname;

export const ACCOUNTS = {
  admin: "admin@sharmstation.test",
  agent: "agent@sharmstation.test",
  editor: "editor@sharmstation.test",
  customer: "customer@sharmstation.test",
  partner: "partner@sharmstation.test",
} as const;
export type Role = keyof typeof ACCOUNTS;

export function password(): string {
  const value = process.env.E2E_PASSWORD;
  if (!value) throw new Error("Set E2E_PASSWORD to the demo accounts' password (UserSeeder::DEMO_PASSWORD).");
  return value;
}

export const AUTH_DIR = path.join(__dirname, ".auth");
export const authFile = (role: Role) => path.join(AUTH_DIR, `${role}.json`);
export const tokenFile = (role: Role) => path.join(AUTH_DIR, `${role}.token`);
export const readToken = (role: Role) => fs.readFileSync(tokenFile(role), "utf8").trim();

/** Known product used by several specs (PLAN §1 D2: Egyptian group is cheaper). */
export const BUGGY = { slug: "desert-buggy-safari", title: "Desert buggy safari", single: "Single buggy" };

/** Cookie that skips the nationality gate. */
export function nationalityCookie(code: string) {
  return { name: "ssv_nationality", value: code, domain: SITE_HOST, path: "/", sameSite: "Lax" as const, expires: Math.floor(Date.now() / 1000) + 3600 * 24 * 30 };
}

export async function setNationality(context: BrowserContext, code: string) {
  await context.addCookies([nationalityCookie(code)]);
}

/** Close the cookie banner if it is shown (decline non-essential). */
export async function dismissCookieBanner(page: Page) {
  const banner = page.getByRole("region", { name: /cookie/i }).or(page.getByRole("dialog", { name: /cookie/i }));
  const decline = banner.getByRole("button", { name: /reject|decline|only necessary|essential/i }).first();
  if (await decline.isVisible().catch(() => false)) await decline.click();
}

/** API client for the Laravel JSON API. */
export async function apiContext(token?: string): Promise<APIRequestContext> {
  return playwrightRequest.newContext({
    baseURL: `${API_URL}/`,
    extraHTTPHeaders: { Accept: "application/json", ...(token ? { Authorization: `Bearer ${token}` } : {}) },
  });
}

export async function apiLogin(role: Role): Promise<{ token: string; user: Record<string, unknown> }> {
  const api = await apiContext();
  const res = await api.post("auth/login", { data: { email: ACCOUNTS[role], password: password() } });
  expect(res.status(), `API login for ${role}`).toBe(200);
  const body = await res.json();
  await api.dispose();
  return { token: body.token, user: body.data };
}

/** Collect console errors from first-party code (third-party noise ignored). */
export function trackConsoleErrors(page: Page): string[] {
  const errors: string[] = [];
  const ignore = [/google/i, /gstatic/i, /maps\./i, /favicon/i, /Download the React DevTools/i, /third-party cookie/i, /ERR_BLOCKED_BY_CLIENT/i];
  page.on("console", (msg) => {
    if (msg.type() !== "error") return;
    const text = msg.text();
    const url = msg.location()?.url ?? "";
    if (ignore.some((r) => r.test(text) || r.test(url))) return;
    errors.push(`${text} @ ${url}`);
  });
  page.on("pageerror", (err) => errors.push(`pageerror: ${err.message}`));
  return errors;
}

/** True when the document is wider than the viewport (horizontal scroll). */
export async function horizontalOverflow(page: Page): Promise<number> {
  return page.evaluate(() => document.documentElement.scrollWidth - document.documentElement.clientWidth);
}

/** Parse "€ 1,234.50" / "1.234,50 €" / "US$35" into a number. */
export function money(text: string): number {
  const m = text.replace(/ /g, " ").match(/-?\d[\d.,\s]*/);
  if (!m) return NaN;
  let s = m[0].replace(/\s/g, "");
  if (/,\d{1,2}$/.test(s)) s = s.replace(/\./g, "").replace(",", ".");
  else s = s.replace(/,/g, "");
  return Number(s);
}

/** A date N days from today as YYYY-MM-DD. */
export function isoDate(daysAhead: number): string {
  const d = new Date();
  d.setDate(d.getDate() + daysAhead);
  return d.toISOString().slice(0, 10);
}
