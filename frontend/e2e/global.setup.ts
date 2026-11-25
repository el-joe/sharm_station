import { expect, test as setup, type Browser } from "@playwright/test";
import fs from "node:fs";
import { ADMIN_URL, ACCOUNTS, AUTH_DIR, SESSION_COOKIE, SITE_URL, authFile, nationalityCookie, password, siteLogin, tokenFile, type Role } from "./helpers";

/**
 * Signs the demo accounts in once and saves Playwright storage states in e2e/.auth (git-ignored):
 *  - customer / partner: signed in through the site's POST /api/auth/login route handler, so the state holds the
 *    httpOnly `ssv_session` cookie (+ `ssv_signed_in` hint) on the site origin, plus the nationality cookie IT.
 *    The token is also written to e2e/.auth/<role>.token for specs that call the API directly (readToken);
 *  - admin / editor: Laravel session cookie for the admin host.
 * The API allows 10 logins/min and the admin 5/min per email, so a still-valid saved session is reused
 * (checked with GET /api/auth/me) and an invalid or expired one is replaced by a fresh sign-in.
 */
setup.beforeAll(() => {
  fs.mkdirSync(AUTH_DIR, { recursive: true });
  password(); // fail fast without E2E_PASSWORD
});

async function reuseSiteSession(browser: Browser, role: Role): Promise<boolean> {
  if (!fs.existsSync(authFile(role))) return false;
  const context = await browser.newContext({ storageState: authFile(role) });
  try {
    const token = (await context.cookies(SITE_URL)).find((c) => c.name === SESSION_COOKIE)?.value;
    if (!token) return false;
    const ok = (await context.request.get(`${SITE_URL}/api/auth/me`)).ok();
    if (ok) fs.writeFileSync(tokenFile(role), decodeURIComponent(token));
    return ok;
  } finally {
    await context.close();
  }
}

for (const role of ["customer", "partner"] as Role[]) {
  setup(`Site sign-in (cookie session): ${role}`, async ({ browser }) => {
    if (await reuseSiteSession(browser, role)) return;
    const context = await browser.newContext();
    await context.addCookies([nationalityCookie("IT")]);
    await siteLogin(context, role);
    const token = (await context.cookies(SITE_URL)).find((c) => c.name === SESSION_COOKIE);
    expect(token?.httpOnly, "ssv_session is httpOnly").toBe(true);
    fs.writeFileSync(tokenFile(role), decodeURIComponent(token!.value));
    const page = await context.newPage();
    await page.goto(`${SITE_URL}/en/privacy`);
    await page.evaluate(() => {
      window.localStorage.setItem("ssv_cookie_consent", "essential");
      window.localStorage.removeItem("ssv_favourites");
      window.localStorage.removeItem("ssv_token");
    });
    await context.storageState({ path: authFile(role) });
    await context.close();
  });
}

for (const role of ["admin", "editor"] as Role[]) {
  setup(`Admin sign-in: ${role}`, async ({ browser }) => {
    if (fs.existsSync(authFile(role))) {
      const saved = await browser.newContext({ storageState: authFile(role) });
      const page = await saved.newPage();
      await page.goto(`${ADMIN_URL}/dashboard`);
      const ok = /\/admin\/dashboard/.test(page.url());
      // Save again: the response refreshed the session cookie's expiry (SESSION_LIFETIME). Without this the
      // browser drops the old cookie part-way through a long run and later admin tests land on the login page.
      if (ok) await saved.storageState({ path: authFile(role) });
      await saved.close();
      if (ok) return;
    }
    const context = await browser.newContext();
    const page = await context.newPage();
    await page.goto(`${ADMIN_URL}/login`);
    await page.getByLabel("Email").fill(ACCOUNTS[role]);
    await page.getByLabel("Password").fill(password());
    await page.getByRole("button", { name: "Sign in" }).click();
    await expect(page).toHaveURL(/\/admin\/dashboard/);
    await context.storageState({ path: authFile(role) });
    await context.close();
  });
}
