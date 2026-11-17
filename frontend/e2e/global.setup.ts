import { expect, test as setup } from "@playwright/test";
import fs from "node:fs";
import { ADMIN_URL, ACCOUNTS, AUTH_DIR, SITE_URL, apiContext, apiLogin, authFile, nationalityCookie, password, tokenFile, type Role } from "./helpers";

/**
 * Signs the demo accounts in once and saves Playwright storage states in e2e/.auth (git-ignored):
 *  - customer / partner: Sanctum token in localStorage (`ssv_token`) on the site origin + nationality cookie IT;
 *  - admin / editor: Laravel session cookie for the admin host.
 * The API allows 10 logins/min and the admin 5/min per email, so a still-valid saved token or session is reused.
 */
setup.beforeAll(() => {
  fs.mkdirSync(AUTH_DIR, { recursive: true });
  password(); // fail fast without E2E_PASSWORD
});

async function validToken(role: Role): Promise<string | null> {
  if (!fs.existsSync(tokenFile(role)) || !fs.existsSync(authFile(role))) return null;
  const token = fs.readFileSync(tokenFile(role), "utf8").trim();
  const api = await apiContext(token);
  const ok = (await api.get("auth/me")).ok();
  await api.dispose();
  return ok ? token : null;
}

for (const role of ["customer", "partner"] as Role[]) {
  setup(`API sign-in: ${role}`, async ({ browser }) => {
    const token = (await validToken(role)) ?? (await apiLogin(role)).token;
    fs.writeFileSync(tokenFile(role), token);
    const context = await browser.newContext();
    await context.addCookies([nationalityCookie("IT")]);
    const page = await context.newPage();
    await page.goto(`${SITE_URL}/en/privacy`);
    await page.evaluate((t) => {
      window.localStorage.setItem("ssv_token", t);
      window.localStorage.setItem("ssv_cookie_consent", "essential");
      window.localStorage.removeItem("ssv_favourites");
    }, token);
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
