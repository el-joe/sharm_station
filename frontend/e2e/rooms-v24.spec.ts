import type { Locator, Page } from "@playwright/test";
import { expect, test } from "./fixtures";
import { adminBookingId, adminFlash, asAdmin, createTripBooking } from "./flows";
import { ADMIN_URL } from "./helpers";
import { apiContext, authFile, isoDate, money, setNationality } from "./helpers";

/**
 * v2.4 phase 2 (F1–F3): unavailable rooms in the listing, group by building, multi-room booking with the
 * RoomAllocationBoard (combinations, tap allocation, validation, extra beds), optional rooms on trips (customer
 * and partner panel).
 */

const BUILDING = { slug: "hotel-nile-view", name: "Hotel Nile View" };
const MULTI_DAY = "luxor-valley-of-the-kings-3-days";
const OPTION = "Standard double";

/** A stay far enough ahead and different on every run (bookings hold their rooms' nights). */
function stay(nights: number) {
  const start = 150 + Math.floor(Math.random() * 400);
  return { check_in: isoDate(start), check_out: isoDate(start + nights) };
}

async function totalOf(scope: Locator) {
  return money((await scope.getByText(/^Total\s*€/).first().textContent()) ?? "");
}

async function fillCheckout(page: Page) {
  const lead = page.getByRole("group", { name: "Lead traveller" });
  await lead.getByRole("textbox", { name: "First name" }).fill("E2E");
  await lead.getByRole("textbox", { name: "Last name" }).fill("Rooms");
  await lead.getByRole("textbox", { name: "Email" }).fill("e2e.rooms@example.test");
  await lead.getByRole("textbox", { name: "Mobile phone" }).fill("+39 333 123 4567");
  await lead.getByRole("combobox", { name: "Country of residence" }).click();
  await page.getByRole("option", { name: "Italy" }).click();
  const others = page.getByRole("group", { name: "Other travellers" });
  if (await others.isVisible().catch(() => false)) {
    const items = others.getByRole("listitem");
    for (let i = 0; i < (await items.count()); i++) {
      await items.nth(i).getByRole("textbox", { name: "First name" }).fill(`E2E${i + 2}`);
      await items.nth(i).getByRole("textbox", { name: "Last name" }).fill("Rooms");
    }
  }
  await page.getByRole("textbox", { name: "Special requests" }).fill("E2E automated test booking, please ignore.");
  await page.getByRole("checkbox", { name: /I accept the terms/ }).check();
}

async function submitCheckout(page: Page) {
  const created = page.waitForResponse((r) => /\/api\/(v1|proxy)\/bookings(\?|$)/.test(r.url()) && r.request().method() === "POST");
  await page.getByRole("button", { name: "Continue to payment" }).click();
  const res = await created;
  expect(res.status(), await res.text()).toBe(201);
  return { body: res.request().postDataJSON() as Record<string, unknown>, booking: (await res.json()).data as Record<string, unknown> };
}

test.beforeEach(async ({ context }) => {
  await setNationality(context, "IT");
});

test("unavailable rooms are listed greyed out with the reason and cannot be booked", async ({ page }) => {
  // One night: the Coral Bay rooms need two (min_stay); four guests do not fit the single room (capacity).
  const { check_in, check_out } = stay(1);
  await page.goto(`/en/accommodation?check_in=${check_in}&check_out=${check_out}&adults=4`);
  const section = page.getByRole("region", { name: "Not available for your search" });
  await expect(section).toBeVisible();
  const unavailable = page.locator('article[data-available="false"]').filter({ visible: true });
  await expect(unavailable.first()).toBeVisible();
  expect(await unavailable.count()).toBeGreaterThan(0);
  await expect(unavailable.filter({ hasText: "Stay too short or too long" }).first()).toBeVisible();
  await expect(unavailable.filter({ hasText: "Too small for your party" }).first()).toBeVisible();
  // No booking call to action on unavailable cards; they are all in the section after the bookable rooms.
  for (const card of await unavailable.all()) await expect(card).not.toContainText("Book now");
  await expect(section.locator('article[data-available="true"]').filter({ visible: true })).toHaveCount(0);
  await expect(section.locator('article[data-available="false"]').filter({ visible: true })).toHaveCount(await unavailable.count());
  const order = await page
    .locator('article[data-available="true"], section[aria-labelledby="rooms-unavailable-title"]')
    .filter({ visible: true })
    .evaluateAll((els) => els.map((e) => e.tagName));
  expect(order.at(-1)).toBe("SECTION");

  // Opening one keeps the stay: the room page refuses it.
  const room = unavailable.filter({ hasText: "Stay too short or too long" }).first();
  await room.getByRole("link").first().click();
  await expect(page).toHaveURL(/\/en\/rooms\/\d+/);
  const widget = page.getByRole("complementary", { name: "Book this room" });
  await expect(widget.getByRole("alert")).toBeVisible();
  await expect(widget.getByRole("button", { name: "Reserve now" })).toBeDisabled();
});

test("group by building toggle is kept in the URL", async ({ page }) => {
  const { check_in, check_out } = stay(2);
  await page.goto(`/en/accommodation?check_in=${check_in}&check_out=${check_out}&adults=2`);
  const toggle = page.getByRole("switch", { name: "Group by building" });
  await expect(toggle).toHaveAttribute("aria-checked", "false");
  await toggle.click();
  await expect(page).toHaveURL(/[?&]group=building/);
  await expect(page.getByTestId("building-group").first()).toBeVisible();
  await expect(page.getByRole("region", { name: new RegExp(BUILDING.name) })).toContainText(/rooms? available/);
  await page.reload();
  await expect(page.getByRole("switch", { name: "Group by building" })).toHaveAttribute("aria-checked", "true");
  await expect(page.getByTestId("building-group")).toHaveCount(2);
  await page.getByRole("switch", { name: "Group by building" }).click();
  await expect(page).not.toHaveURL(/group=building/);
  await expect(page.getByTestId("building-group")).toHaveCount(0);
});

test("multi-room booking: combinations, tap allocation, validation, extra bed and a 2-room booking", async ({ page }) => {
  const { check_in, check_out } = stay(2);
  await page.goto(`/en/buildings/${BUILDING.slug}?check_in=${check_in}&check_out=${check_out}&adults=3&children=1`);
  const section = page.getByRole("region", { name: "Book several rooms" }).first();
  await section.scrollIntoViewIfNeeded();
  await section.getByRole("combobox", { name: "Age of child 1" }).click();
  await page.getByRole("option", { name: "9 years", exact: true }).click();

  // Best combinations: apply the first one with two rooms.
  const combo = section.getByTestId("combination").filter({ hasText: "2 rooms" }).first();
  await expect(combo).toBeVisible();
  const comboTotal = money((await combo.locator(".text-xl").textContent()) ?? "");
  await combo.getByRole("button", { name: /^Choose combination/ }).click();
  const board = section.getByTestId("allocation-board");
  await expect(board.getByTestId("allocation-status")).toContainText("All 4 guests are in a room and every room is valid");
  const aside = section.getByRole("complementary");
  await expect(aside.getByTestId("quote-room")).toHaveCount(2);
  await expect.poll(() => totalOf(aside)).toBeCloseTo(comboTotal, 2);

  // Tap to select, then "Remove from the room": the server-mirrored rule is shown at once.
  await board.getByRole("button", { name: /^Adult 1, in Room 1/ }).click();
  await expect(board.getByRole("button", { name: /^Adult 1 selected/ })).toHaveAttribute("aria-pressed", "true");
  await board.getByRole("button", { name: "Remove Adult 1 from the room" }).click();
  await expect(board.getByTestId("allocation-status")).toContainText("Every guest must be in a room.");
  await expect(section.getByTestId("multi-room-reserve")).toBeDisabled();
  await expect(page.getByRole("status").filter({ hasText: "Adult 1 is not in a room." })).toBeAttached();

  // Tap again and place the adult in Room 1.
  await board.getByRole("button", { name: /^Adult 1, not in a room/ }).click();
  await board.getByRole("button", { name: "Move Adult 1 to Room 1" }).click();
  await expect(page.getByRole("status").filter({ hasText: "Adult 1 moved to Room 1." })).toBeAttached();
  await expect(board.getByTestId("allocation-status")).toContainText("every room is valid");

  // Extra bed: +price per night × nights.
  await expect.poll(() => totalOf(aside)).toBeCloseTo(comboTotal, 2);
  const before = await totalOf(aside);
  const room1 = board.locator("li[data-room]").first();
  await room1.getByRole("button", { name: "Add one: Extra beds · Room 1" }).click();
  await expect.poll(() => totalOf(aside)).toBeGreaterThan(before);
  await expect(aside.getByText(/^Extra bed · 2 × €/)).toBeVisible();
  const withBed = await totalOf(aside);
  expect(withBed - before).toBeCloseTo(50, 2);

  await expect(section.getByTestId("multi-room-reserve")).toBeEnabled();
  await section.getByTestId("multi-room-reserve").click();
  await expect(page).toHaveURL(/\/en\/checkout/);
  await expect(page.getByRole("region", { name: "Rooms" })).toBeVisible();
  await fillCheckout(page);
  const { body, booking } = await submitCheckout(page);
  const rooms = body.rooms as { room_id: number; adults: number; children_ages: number[]; extra_beds: number }[];
  expect(rooms).toHaveLength(2);
  expect(rooms[0].extra_beds).toBe(1);
  expect(rooms.reduce((s, r) => s + r.adults, 0)).toBe(3);
  expect(rooms.flatMap((r) => r.children_ages)).toEqual([9]);
  expect(booking.rooms as unknown[]).toHaveLength(2);
  expect(Number((booking.totals as { total: number }).total)).toBeCloseTo(withBed, 2);

  await expect(page).toHaveURL(new RegExp(`/en/booking/${booking.reference}`));
  await expect(page.getByTestId("booking-rooms").filter({ visible: true }).getByRole("listitem")).toHaveCount(2);
  await expect(page.getByTestId("quote-room").filter({ visible: true })).toHaveCount(2);
});

/**
 * HTML5 drag and drop with real mouse moves (Locator.dragTo's single step is not reliable for drag events), retried
 * until `done` holds: the board's height changes while quotes load, which can move the drop target mid-drag.
 */
async function drag(page: Page, source: Locator, target: Locator, done: Locator) {
  await expect(async () => {
    await target.scrollIntoViewIfNeeded();
    const a = (await source.boundingBox())!;
    await page.mouse.move(a.x + a.width / 2, a.y + a.height / 2);
    await page.mouse.down();
    await page.mouse.move(a.x + a.width / 2 + 10, a.y + a.height / 2 + 10, { steps: 3 });
    const b = (await target.boundingBox())!;
    await page.mouse.move(b.x + b.width / 2, b.y + 30, { steps: 10 });
    await page.mouse.up();
    await expect(done).toBeVisible({ timeout: 2000 });
  }).toPass({ timeout: 15_000 });
}

/** A 2-night stay on which the given rooms of the building are free (GET /rooms reports availability). */
async function freeStay(roomNames: string[]) {
  const api = await apiContext();
  try {
    for (let i = 0; i < 15; i++) {
      const s = stay(2);
      const res = await api.get("rooms", { params: { building: BUILDING.slug, check_in: s.check_in, check_out: s.check_out, per_page: 48 } });
      const rooms = (await res.json()).data as { name: string; available: boolean }[];
      if (roomNames.every((n) => rooms.find((r) => r.name === n)?.available)) return s;
    }
    throw new Error(`no free stay for ${roomNames.join(", ")}`);
  } finally {
    await api.dispose();
  }
}

test("allocation board: rooms added by hand, drag and drop, empty-room rule", async ({ page }) => {
  const { check_in, check_out } = await freeStay(["Twin room", "Triple room"]);
  // Mouse drags do not auto-scroll: keep the pool and both rooms on screen.
  await page.setViewportSize({ width: 1440, height: 2200 });
  await page.goto(`/en/buildings/${BUILDING.slug}?check_in=${check_in}&check_out=${check_out}&adults=2`);
  const section = page.getByRole("region", { name: "Book several rooms" }).first();
  await section.getByRole("combobox", { name: "Add a room" }).click();
  await page.getByRole("option", { name: /^Twin room/ }).click();
  await section.getByRole("combobox", { name: "Add a room" }).click();
  await page.getByRole("option", { name: /^Triple room/ }).click();
  const board = section.getByTestId("allocation-board");
  const rooms = board.locator("li[data-room]");
  await expect(rooms).toHaveCount(2);
  await expect(board.getByTestId("allocation-status")).toContainText("Every guest must be in a room.");

  await drag(page, board.getByRole("button", { name: /^Adult 1,/ }), rooms.nth(0), rooms.nth(0).getByRole("button", { name: /^Adult 1, in Room 1/ }));
  await drag(page, board.getByRole("button", { name: /^Adult 2,/ }), rooms.nth(1), rooms.nth(1).getByRole("button", { name: /^Adult 2, in Room 2/ }));
  await expect(board.getByTestId("allocation-status")).toContainText("All 2 guests are in a room and every room is valid");

  // Both adults in Room 1: Room 2 is empty (SRS 7.2), booking blocked.
  await drag(page, board.getByRole("button", { name: /^Adult 2,/ }), rooms.nth(0), rooms.nth(0).getByRole("button", { name: /^Adult 2, in Room 1/ }));
  await expect(rooms.nth(1).getByTestId("room-errors")).toContainText("Room 2 is empty: every booked room needs at least one guest.");
  await expect(section.getByTestId("multi-room-reserve")).toBeDisabled();

  // Removing the empty room makes the stay valid again.
  await rooms.nth(1).getByRole("button", { name: /^Remove Room 2/ }).click();
  await expect(board.getByTestId("allocation-status")).toContainText("every room is valid");
  await expect(section.getByTestId("multi-room-reserve")).toBeEnabled();
});

test("trip with an optional room: the room line is quoted and booked", async ({ page }) => {
  await page.goto(`/en/trips/${MULTI_DAY}`);
  const widget = page.getByRole("complementary", { name: "Booking" });
  const rooms = widget.getByTestId("room-options").filter({ visible: true });
  await expect(rooms).toBeVisible();
  await expect.poll(() => totalOf(widget)).toBeGreaterThan(0);
  const before = await totalOf(widget);
  await rooms.getByRole("button", { name: `Add ${OPTION}` }).click();
  // Two adults go into the new room automatically.
  await expect(rooms.getByTestId("picked-room")).toHaveCount(1);
  await expect(rooms.getByText("Every traveller is in a room.")).toBeVisible();
  await expect(widget.getByRole("listitem").filter({ hasText: OPTION }).filter({ visible: true }).first()).toBeVisible();
  await expect.poll(() => totalOf(widget)).toBeGreaterThan(before);

  await widget.getByRole("button", { name: "Reserve now" }).click();
  await expect(page).toHaveURL(/\/en\/checkout/);
  await fillCheckout(page);
  const { body, booking } = await submitCheckout(page);
  const options = body.room_options as { room_option_id: number; adults: number }[];
  expect(options).toHaveLength(1);
  expect(options[0].adults).toBe(2);
  const booked = booking.rooms as { room_option_id: number | null }[];
  expect(booked.some((r) => r.room_option_id === options[0].room_option_id)).toBe(true);
});

test.describe("partner panel", () => {
  test.use({ storageState: authFile("partner") });

  test("configure step with an optional room", async ({ page }) => {
    const api = await apiContext();
    const trip = (await (await api.get(`trips/${MULTI_DAY}`)).json()).data as { title: string };
    await api.dispose();

    await page.goto("/en/partner/new");
    const nationality = page.getByRole("combobox", { name: "Client nationality" });
    await nationality.click();
    await nationality.fill("Italy");
    await page.getByRole("option", { name: /Italy$/ }).first().click();
    await page.getByRole("tab", { name: "Multi-day trips" }).click();
    const card = page.getByRole("listitem").filter({ has: page.getByRole("heading", { name: trip.title }) });
    await card.getByRole("button", { name: `Select ${trip.title}` }).click();

    const roomsCard = page.getByRole("region", { name: "Rooms (optional)" });
    await expect(roomsCard).toBeVisible();
    const summary = page.getByRole("region", { name: "Price summary" });
    await expect(summary.getByRole("button", { name: "Continue" })).toBeEnabled();
    const quoted = page.waitForResponse((r) => /\/partner\/quotes/.test(r.url()) && r.request().method() === "POST" && Boolean(r.request().postDataJSON()?.room_options));
    await roomsCard.getByRole("button", { name: `Add ${OPTION}` }).click();
    await expect(roomsCard.getByText("Every traveller is in a room.")).toBeVisible();
    const res = await quoted;
    await expect(summary.getByRole("listitem").filter({ hasText: OPTION })).toBeVisible();
    expect(res.status()).toBe(200);
    const quote = (await res.json()).data as { lines: { type: string; partner_total: number; total: number }[] };
    const line = quote.lines.find((l) => l.type === "room");
    expect(line, "room line in the partner quote").toBeTruthy();
    await expect(summary.getByRole("button", { name: "Continue" })).toBeEnabled();
  });
});

test("balance: once the deposit is paid the booking page offers the online balance payment", async ({ page, browser }) => {
  const { reference, access_token } = await createTripBooking({ email: "e2e.balance@example.test", days: 200 });
  const api = await apiContext();
  const created = (await (await api.get(`bookings/${reference}`, { params: { token: access_token! } })).json()).data;
  // Record only the deposit in the admin (the form is prefilled with the whole amount due).
  await asAdmin(browser, async (admin) => {
    await admin.goto(`${ADMIN_URL}/bookings/${await adminBookingId(admin, reference)}`);
    await admin.getByRole("spinbutton", { name: "Amount (EUR)" }).fill(String(created.totals.deposit_amount));
    await admin.getByRole("textbox", { name: "Note" }).first().fill("E2E deposit");
    await admin.getByRole("button", { name: "Record payment" }).click();
    await adminFlash(admin, /recorded|saved|payment/i);
  });
  const booking = (await (await api.get(`bookings/${reference}`, { params: { token: access_token! } })).json()).data;
  const methods = (await (await api.get(`bookings/${reference}/payment-methods`, { params: { token: access_token! } })).json()).data as { type: string; amount_due?: { amount_eur: number } }[];
  await api.dispose();
  expect(booking.payment_status).toBe("deposit_paid");

  await page.goto(`/en/booking/${reference}?token=${access_token}`);
  const section = page.getByRole("region", { name: "Pay the balance" });
  await expect(section).toBeVisible();
  await expect(section).toContainText("Your deposit is paid.");
  const online = methods.filter((m) => m.type === "online");
  if (online.length) {
    // The amount is the balance computed by the server (incl. the method's surcharge).
    await expect(section.getByRole("button", { name: /^Pay .+ securely$/ })).toBeVisible();
    const shown = money((await section.getByRole("button", { name: /^Pay .+ securely$/ }).textContent()) ?? "");
    expect(shown).toBeCloseTo(online[0].amount_due?.amount_eur ?? shown, 2);
  } else {
    await expect(section).toContainText("Online payment is not available");
  }
  // The deposit step is gone.
  await expect(page.getByRole("region", { name: "Pay now" })).toHaveCount(0);
});
