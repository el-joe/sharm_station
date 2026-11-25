import { randomBytes } from "node:crypto";
import { expect, test } from "./fixtures";
import { tinker } from "./flows";
import { apiContext } from "./helpers";

/**
 * Password links (API §5): "Forgot password?" from sign-in, the neutral answer of /forgot-password, an invalid
 * /reset-password link, and a real reset for a throw-away customer (token from the password broker through tinker),
 * then sign-in with the new password. The shared demo accounts are never touched.
 */

test.use({ storageState: { cookies: [], origins: [{ origin: process.env.E2E_BASE_URL ?? "http://sharm-station.loc", localStorage: [{ name: "ssv_cookie_consent", value: "essential" }] }] } });

test("forgot password: linked from sign-in, same neutral message for any e-mail", async ({ page }) => {
  await page.goto("/en/sign-in");
  await page.getByRole("main").getByRole("link", { name: "Forgot password?" }).click();
  await expect(page).toHaveURL(/\/en\/forgot-password$/);
  await expect(page.getByRole("heading", { name: "Forgot your password?", level: 1 })).toBeVisible();
  // Excluded from the nationality gate, like sign-in.
  await page.waitForTimeout(800);
  await expect(page.getByRole("dialog", { name: "Where are you from?" })).toBeHidden();

  const form = page.getByRole("main");
  await form.getByRole("button", { name: "Send the link" }).click();
  await expect(form.getByText("Enter a valid email address")).toBeVisible();

  await form.getByRole("textbox", { name: "Email" }).fill(`e2e.nobody.${Date.now()}@example.test`);
  const answered = page.waitForResponse((r) => r.url().includes("/auth/forgot-password") && r.request().method() === "POST");
  await form.getByRole("button", { name: "Send the link" }).click();
  expect((await answered).status()).toBe(200);
  await expect(page.getByTestId("forgot-sent")).toHaveText("If an account exists for this e-mail, we have sent a link to choose a new password.");
  await expect(form.getByRole("link", { name: "Back to sign in" })).toBeVisible();
});

test("reset password: an invalid link shows the expired-link error (EN and IT)", async ({ page }) => {
  const email = `e2e.nobody.${Date.now()}@example.test`;
  await page.goto(`/en/reset-password?token=not-a-real-token&email=${encodeURIComponent(email)}`);
  await expect(page.getByRole("heading", { name: "Choose a new password", level: 1 })).toBeVisible();
  await expect(page.getByRole("dialog", { name: "Where are you from?" })).toBeHidden();
  const form = page.getByRole("main");

  // Client rules first: length and confirmation, shown as hints.
  await form.locator("#reset-password").fill("short");
  await form.locator("#reset-password-confirmation").fill("short");
  await expect(form.getByRole("list", { name: "Password requirements" }).getByRole("listitem").first()).toHaveAttribute("data-ok", "false");
  await form.getByRole("button", { name: "Save the new password" }).click();
  await expect(form.getByText("Use at least 8 characters")).toBeVisible();

  await form.locator("#reset-password").fill("a-long-enough-password");
  await form.locator("#reset-password-confirmation").fill("a-long-enough-password");
  await expect(form.getByRole("list", { name: "Password requirements" }).locator('li[data-ok="true"]')).toHaveCount(2);
  const answered = page.waitForResponse((r) => r.url().includes("/auth/reset-password"));
  await form.getByRole("button", { name: "Save the new password" }).click();
  expect((await answered).status()).toBe(422);
  const error = page.getByTestId("reset-link-error");
  await expect(error).toContainText("This password link is invalid or has expired.");
  await error.getByRole("link", { name: "Ask for a new link" }).click();
  await expect(page).toHaveURL(/\/en\/forgot-password\?email=/);
  await expect(page.getByRole("main").getByRole("textbox", { name: "Email" })).toHaveValue(email);

  await page.goto(`/it/reset-password?token=not-a-real-token&email=${encodeURIComponent(email)}`);
  await page.getByRole("main").locator("#reset-password").fill("a-long-enough-password");
  await page.getByRole("main").locator("#reset-password-confirmation").fill("a-long-enough-password");
  await page.getByRole("main").getByRole("button", { name: "Salva la nuova password" }).click();
  await expect(page.getByTestId("reset-link-error")).toContainText("Questo link per la password non è valido o è scaduto.");

  // A link without token / e-mail.
  await page.goto("/en/reset-password");
  await expect(page.getByRole("main").getByRole("alert")).toContainText("This password link is incomplete.");
});

test("reset password end to end: throw-away customer, new password, sign in with it", async ({ page }) => {
  const email = `e2e.reset.${Date.now()}@example.test`;
  const first = `Old-${randomBytes(6).toString("hex")}`;
  const next = `New-${randomBytes(6).toString("hex")}`;

  // Throw-away customer through the public API.
  const api = await apiContext();
  const registered = await api.post("auth/register", { data: { name: "E2E Reset", email, password: first, password_confirmation: first, locale: "en" } });
  expect(registered.status()).toBe(201);
  await api.dispose();

  // A real reset token from the password broker (what the e-mail link carries).
  const token = tinker(`echo Illuminate\\Support\\Facades\\Password::broker()->createToken(App\\Models\\User::where('email', '${email}')->firstOrFail());`).split("\n").pop()!.trim();
  expect(token).toMatch(/^[A-Za-z0-9]{20,}$/);

  await page.goto(`/en/reset-password?token=${token}&email=${encodeURIComponent(email)}`);
  const form = page.getByRole("main");
  await expect(form.getByText(`Account: ${email}`)).toBeVisible();
  await form.locator("#reset-password").fill(next);
  await form.locator("#reset-password-confirmation").fill(next);
  const answered = page.waitForResponse((r) => r.url().includes("/auth/reset-password"));
  await form.getByRole("button", { name: "Save the new password" }).click();
  expect((await answered).status()).toBe(200);

  // Sign-in with the notice; the reset itself signs nobody in.
  await expect(page).toHaveURL(/\/en\/sign-in\?reset=1$/);
  await expect(page.getByTestId("reset-done")).toHaveText("Your new password is saved. Sign in with it.");
  expect((await page.context().cookies()).some((c) => c.name === "ssv_session" && c.value)).toBe(false);

  // The old password no longer works, the new one does.
  const check = await apiContext();
  expect((await check.post("auth/login", { data: { email, password: first } })).status()).not.toBe(200);
  await check.dispose();
  await form.getByRole("textbox", { name: "Email" }).fill(email);
  await form.getByRole("textbox", { name: "Password" }).fill(next);
  await form.getByRole("button", { name: "Sign in" }).click();
  await expect(page).toHaveURL(/\/en\/account\/bookings/);

  // The link works once.
  await page.goto(`/en/reset-password?token=${token}&email=${encodeURIComponent(email)}`);
  await page.getByRole("main").locator("#reset-password").fill(`${next}-2`);
  await page.getByRole("main").locator("#reset-password-confirmation").fill(`${next}-2`);
  await page.getByRole("main").getByRole("button", { name: "Save the new password" }).click();
  await expect(page.getByTestId("reset-link-error")).toBeVisible();
});
