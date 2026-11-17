import { test as base, expect } from "@playwright/test";

/**
 * Shared test fixture: every page starts with the cookie banner already answered ("Essential only"),
 * so the fixed banner never covers buttons. The banner itself is checked in mobile.spec.
 * Contexts created by hand (browser.newContext) are not affected.
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
    await provide(context);
  },
});

export { expect };
