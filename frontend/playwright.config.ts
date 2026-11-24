import { defineConfig, devices } from "@playwright/test";

/**
 * End-to-end suite (PLAN-v2.2 §10.5). Runs against the already running local servers:
 *   site  E2E_BASE_URL, default http://sharm-station.loc (Next.js, `next start`), e.g. http://localhost:3100
 *   API   http://api.sharm-station.loc    (Laravel)
 *   admin http://admin.sharm-station.loc  (Laravel Blade admin)
 *
 * Demo accounts share one password, read from E2E_PASSWORD.
 * One worker by default: the Laravel dev server (`php artisan serve`) handles one request at a time, and the
 * admin specs temporarily change commission settings that the partner specs assert on.
 */
export const SITE_URL = (process.env.E2E_BASE_URL ?? process.env.E2E_SITE_URL ?? "http://sharm-station.loc").replace(/\/$/, "");

export default defineConfig({
  testDir: "./e2e",
  outputDir: "./test-results",
  fullyParallel: false,
  workers: Number(process.env.E2E_WORKERS ?? 1),
  retries: 1,
  timeout: 60_000,
  expect: { timeout: 10_000 },
  reporter: [["list"], ["html", { outputFolder: "playwright-report", open: "never" }]],
  use: {
    baseURL: SITE_URL,
    trace: "on-first-retry",
    screenshot: "only-on-failure",
    video: "off",
    actionTimeout: 15_000,
    navigationTimeout: 30_000,
    locale: "en-GB",
  },
  projects: [
    { name: "setup", testMatch: /global\.setup\.ts/, teardown: "cleanup" },
    { name: "cleanup", testMatch: /global\.teardown\.ts/ },
    {
      name: "desktop-chromium",
      dependencies: ["setup"],
      testIgnore: /mobile\.spec\.ts/,
      use: { ...devices["Desktop Chrome"], viewport: { width: 1440, height: 900 } },
    },
    {
      name: "mobile-chromium",
      dependencies: ["setup"],
      // Mobile-specific checks, the public smoke pages, the crawl and the maps (390 px overflow check).
      testMatch: /(mobile|public-smoke|crawl|maps)\.spec\.ts/,
      use: { ...devices["Pixel 7"], viewport: { width: 390, height: 844 } },
    },
  ],
});
