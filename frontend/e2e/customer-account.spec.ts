import { expect, test } from "./fixtures";
import { ACCOUNTS, apiContext, authFile, password, readToken, setNationality } from "./helpers";

/** Customer account: sign-in, profile nationality, favourites, bookings (SRS 13.1, CR-10). */

test("customer signs in through the form", async ({ page, context }) => {
  await setNationality(context, "IT");
  await page.goto("/en/sign-in");
  await page.getByRole("main").getByRole("textbox", { name: "Email" }).fill(ACCOUNTS.customer);
  await page.getByRole("main").getByRole("textbox", { name: "Password" }).fill(password());
  await page.getByRole("button", { name: "Sign in" }).click();
  await expect(page).toHaveURL(/\/en\/account/);
  await expect(page.getByRole("banner").getByRole("button", { name: /Account menu for/ })).toBeVisible();
});

test("wrong password shows an error and stays on sign-in", async ({ page, context }) => {
  await setNationality(context, "IT");
  await page.goto("/en/sign-in");
  await page.getByRole("main").getByRole("textbox", { name: "Email" }).fill(ACCOUNTS.customer);
  await page.getByRole("main").getByRole("textbox", { name: "Password" }).fill("E2E-wrong-password");
  await page.getByRole("button", { name: "Sign in" }).click();
  await expect(page.getByRole("alert").first()).toBeVisible();
  await expect(page).toHaveURL(/\/en\/sign-in/);
});

test.describe("signed in", () => {
  test.use({ storageState: authFile("customer") });

  test("profile nationality change is saved and the header chip follows", async ({ page, context }) => {
    const api = await apiContext(readToken("customer"));
    const original = (await (await api.get("auth/me")).json()).data.nationality_code as string | null;

    await page.goto("/en/account/profile");
    const details = page.getByRole("region", { name: "Personal details" });
    const nationality = details.getByRole("combobox", { name: "Nationality" });
    await expect(nationality).not.toHaveValue("");
    await nationality.click();
    await nationality.fill("Egypt");
    await page.getByRole("option", { name: /Egypt$/ }).first().click();
    await expect(nationality).toHaveValue("Egypt");
    await expect(details.getByText("Prices for nationality group Egyptian.")).toBeVisible();

    const saved = page.waitForResponse((r) => r.url().includes("/me/profile") && r.request().method() === "PATCH");
    await details.getByRole("button", { name: "Save changes" }).click();
    expect((await saved).status()).toBe(200);

    await expect(page.getByRole("banner").getByRole("button", { name: /^Nationality: Egypt/ })).toBeVisible();
    expect((await context.cookies()).find((c) => c.name === "ssv_nationality")?.value).toBe("EG");
    const me = (await (await api.get("auth/me")).json()).data;
    expect(me.nationality_code).toBe("EG");

    // Restore the demo account.
    await api.patch("me/profile", { data: { nationality_code: original ?? "IT" } });
    await api.dispose();
  });

  test("the account nationality wins over a different cookie when the session loads", async ({ page, context }) => {
    const api = await apiContext(readToken("customer"));
    const original = (await (await api.get("auth/me")).json()).data.nationality_code as string | null;
    await api.patch("me/profile", { data: { nationality_code: "DE" } });
    await setNationality(context, "IT");
    try {
      await page.goto("/en/trips");
      await expect(page.getByRole("banner").getByRole("button", { name: /^Nationality: Germany/ })).toBeVisible();
      await expect.poll(async () => (await context.cookies()).find((c) => c.name === "ssv_nationality")?.value).toBe("DE");
    } finally {
      await api.patch("me/profile", { data: { nationality_code: original ?? "DE" } });
      await api.dispose();
    }
  });

  test("favourites toggle from a listing card", async ({ page }) => {
    await page.goto("/en/trips");
    const card = page.getByRole("main").getByRole("article").filter({ hasText: "Desert buggy safari" });
    const save = card.getByRole("button", { name: "Save Desert buggy safari to favourites" });
    await save.click();
    const remove = card.getByRole("button", { name: "Remove Desert buggy safari from favourites" });
    await expect(remove).toBeVisible();
    await expect(page.getByRole("banner").getByRole("link", { name: /Favourites \(1\)/ })).toBeVisible();

    await page.goto("/en/account/favourites");
    await expect(page.getByRole("main").getByRole("link", { name: "Desert buggy safari" }).first()).toBeVisible();
    await page.getByRole("main").getByRole("button", { name: /Remove .*Desert buggy safari|Remove from favourites/ }).first().click();
    await expect(page.getByText("No favourites yet")).toBeVisible();
  });

  test("bookings list and detail", async ({ page }) => {
    await page.goto("/en/account/bookings");
    await expect(page.getByRole("heading", { level: 1 })).toBeVisible();
    const api = await apiContext(readToken("customer"));
    const list = (await (await api.get("me/bookings")).json()).data as { reference: string }[];
    await api.dispose();
    test.skip(list.length === 0, "no customer bookings yet (booking.spec creates one)");
    const reference = list[0].reference;
    const card = page.getByRole("article").filter({ hasText: reference });
    const link = card.getByRole("link", { name: "Booking details" });
    await expect(link).toBeVisible();
    await link.click();
    await expect(page).toHaveURL(new RegExp(`/en/account/bookings/${reference}`));
    await expect(page.getByText(reference).first()).toBeVisible();
  });
});
