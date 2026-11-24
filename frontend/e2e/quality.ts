import type { Page } from "@playwright/test";
import { API_ORIGIN, ADMIN_URL, SITE_URL } from "./helpers";

/**
 * Page quality probes shared by the crawl and the feature specs (PLAN-v2.3 §5.3):
 * first-party HTTP errors, console errors, hydration errors, JS dialogs (DataTables "alert" errors),
 * placeholder text and horizontal overflow.
 */

/** Placeholder copy that must never ship. Word boundaries keep e.g. Italian "metodo" or "ipsum" in names out. */
export const PLACEHOLDER = /\b(coming soon|on its way|not available yet|lorem ipsum|pending backend|todo:|tbd\b)/i;

const FIRST_PARTY = [new URL(SITE_URL).origin, API_ORIGIN, new URL(ADMIN_URL).origin];
const isFirstParty = (url: string) => !url || FIRST_PARTY.some((o) => url.startsWith(o)) || url.startsWith("/");

/** Third-party noise: Google Maps (embeds, Maps JavaScript API, Places), fonts, analytics, devtools hints.
 * The crawl makes no assertion about map containers, tiles or markers. */
const IGNORED_CONSOLE = [/maps\.googleapis|maps\.google\.|maps\.gstatic/i, /google/i, /gstatic/i, /favicon/i, /Download the React DevTools/i, /third-party cookie/i, /ERR_BLOCKED_BY_CLIENT/i];

export const HYDRATION = /hydrat|Minified React error #(418|419|421|422|423|425)\b|did not match/i;

export interface PageWatch {
  console: string[];
  hydration: string[];
  http: string[];
  dialogs: string[];
  /** Drop collected entries matching a pattern (e.g. the intended 404). */
  allow(pattern: RegExp): void;
  reset(): void;
}

export function watchPage(page: Page, opts: { allowHttp?: RegExp[] } = {}): PageWatch {
  const w: PageWatch = {
    console: [],
    hydration: [],
    http: [],
    dialogs: [],
    allow(pattern) {
      for (const key of ["console", "hydration", "http", "dialogs"] as const) w[key] = w[key].filter((e) => !pattern.test(e));
    },
    reset() {
      w.console = [];
      w.hydration = [];
      w.http = [];
      w.dialogs = [];
    },
  };
  page.on("console", (msg) => {
    if (msg.type() !== "error") return;
    const text = msg.text();
    const url = msg.location()?.url ?? "";
    if (IGNORED_CONSOLE.some((r) => r.test(text) || r.test(url))) return;
    if (!isFirstParty(url)) return;
    (HYDRATION.test(text) ? w.hydration : w.console).push(`${text.slice(0, 300)} @ ${url}`);
  });
  page.on("pageerror", (err) => (HYDRATION.test(err.message) ? w.hydration : w.console).push(`pageerror: ${err.message.slice(0, 300)}`));
  page.on("response", (res) => {
    const url = res.url();
    if (res.status() < 400 || !isFirstParty(url)) return;
    if (opts.allowHttp?.some((r) => r.test(url))) return;
    w.http.push(`${res.status()} ${res.request().method()} ${url}`);
  });
  page.on("dialog", (d) => {
    w.dialogs.push(`${d.type()}: ${d.message().slice(0, 200)}`);
    void d.dismiss().catch(() => undefined);
  });
  return w;
}

/** Visible text of the page body (hidden templates and sr-only included in innerText are fine). */
export async function placeholderText(page: Page): Promise<string | null> {
  const text = await page.locator("body").innerText().catch(() => "");
  return text.match(PLACEHOLDER)?.[0] ?? null;
}

export async function overflowPx(page: Page): Promise<number> {
  return page.evaluate(() => document.documentElement.scrollWidth - document.documentElement.clientWidth);
}

/** Wait for the network to settle without failing on long-polling / map tiles. */
export async function settle(page: Page, timeout = 8_000) {
  await page.waitForLoadState("networkidle", { timeout }).catch(() => undefined);
}

/** Pattern used to dedupe dynamic URLs: numbers, references and slugs of detail routes become placeholders. */
export function urlPattern(pathname: string): string {
  return pathname
    .replace(/^\/(en|it)\/(trips|nile|packages|destinations|blog|buildings|rooms|booking)\/[^/]+/, "/$1/$2/:slug")
    .replace(/\/BK-\d+/gi, "/:ref")
    .replace(/\/\d+(?=\/|$)/g, "/:id");
}
