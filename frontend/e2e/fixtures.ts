import { test as base, expect } from "@playwright/test";
import { API_ORIGIN, SITE_URL } from "./helpers";

/**
 * Shared test fixture: every page starts with the cookie banner already answered ("Essential only"),
 * so the fixed banner never covers buttons. The banner itself is checked in mobile.spec.
 * Contexts created by hand (browser.newContext) are not affected.
 *
 * E2E_CORS_PATCH=1: answers the API's CORS for the site origin under test, for runs against a site port the
 * API's CORS does not allow (e.g. a production build checked on :3101 while the real site runs on :3100).
 */
export const test = base.extend({
  context: async ({ context }, provide) => {
    await context.addInitScript(() => {
      try {
        if (!window.localStorage.getItem("ssv_cookie_consent")) window.localStorage.setItem("ssv_cookie_consent", "essential");
      } catch {
        /* storage blocked */
      }
    });
    if (process.env.E2E_CORS_PATCH) {
      const origin = new URL(SITE_URL).origin;
      await context.route(`${API_ORIGIN}/**`, async (route) => {
        const req = route.request();
        const cors = {
          "access-control-allow-origin": origin,
          "access-control-allow-credentials": "true",
          "access-control-allow-methods": "GET,POST,PUT,PATCH,DELETE,OPTIONS",
          "access-control-allow-headers": req.headers()["access-control-request-headers"] ?? "*",
          vary: "Origin",
        };
        if (req.method() === "OPTIONS") return route.fulfill({ status: 204, headers: cors });
        const response = await route.fetch();
        await route.fulfill({ response, headers: { ...response.headers(), ...cors } });
      });
    }
    await provide(context);
  },
});

export { expect };
