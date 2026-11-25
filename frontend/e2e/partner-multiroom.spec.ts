import type { Page } from "@playwright/test";
import { expect, test } from "./fixtures";
import { apiContext, authFile, isoDate, money, setNationality } from "./helpers";

/**
 * Partner panel, multi-room accommodation (B2.1 in the new-booking wizard): building, stay and party, best
 * combinations, the RoomAllocationBoard with an extra bed, a live POST /partner/quotes with rooms[] for the client's
 * nationality (no commission on accommodation), POST /partner/bookings and the room lines in the confirmation, the
 * bookings list and the booking detail.
 */

test.use({ storageState: authFile("partner") });

const BUILDING = { name: "Hotel Nile View" };
const ROOM = "Hotel Nile View · Standard double room";

/** A stay far enough ahead and different on every run (bookings hold their rooms' nights). */
function stay(nights: number) {
  const start = 160 + Math.floor(Math.random() * 400);
  return { check_in: isoDate(start), check_out: isoDate(start + nights) };
}

async function pickCombo(page: Page, name: string) {
  const box = page.getByRole("combobox", { name: "Client nationality" }).first();
  await box.click();
  await box.fill(name);
  await page.getByRole("option", { name: new RegExp(`${name}$`) }).first().click();
  await expect(box).toHaveValue(name);
}

test("partner books two rooms of one building with the allocation board and sees the room lines", async ({ page }) => {
  const { check_in, check_out } = stay(2);
  await page.goto("/en/partner/new");
  await expect(page.getByRole("heading", { name: "New booking", level: 1 })).toBeVisible();
  await pickCombo(page, "Egypt");
  await page.getByRole("tab", { name: "Accommodation" }).click();
  await page.getByRole("button", { name: `Select ${ROOM}` }).click();

  // Configure: building of the chosen room, stay, 3 adults + 1 child (6).
  await expect(page.getByRole("heading", { name: "Configure booking", level: 1 })).toBeVisible();
  const stayCard = page.getByRole("region", { name: "Stay" });
  await expect(stayCard.getByRole("combobox", { name: /^Building/ })).toContainText(BUILDING.name);
  await stayCard.locator("#cfg-in").fill(check_in);
  await stayCard.locator("#cfg-out").fill(check_out);
  await stayCard.getByRole("button", { name: "Add one: Adults" }).click();
  await stayCard.getByRole("button", { name: "Add one: Children" }).click();
  await stayCard.getByRole("combobox", { name: "Age of child 1" }).click();
  await page.getByRole("option", { name: "6 years", exact: true }).click();

  // Best combinations: apply the first one with two rooms.
  const rooms = page.getByRole("region", { name: "Rooms & allocation" });
  const combo = rooms.getByTestId("combination").filter({ hasText: "2 rooms" }).first();
  await expect(combo).toBeVisible();
  const comboTotal = money((await combo.locator(".text-xl").textContent()) ?? "");
  await combo.getByRole("button", { name: /^Choose combination/ }).click();
  const board = rooms.getByTestId("allocation-board");
  await expect(board.getByTestId("allocation-status")).toContainText("All 4 guests are in a room and every room is valid");
  await expect(board.locator("li[data-room]")).toHaveCount(2);

  // Live partner quote: one block per room, partner price = customer price (no accommodation commission).
  const summary = page.getByRole("region", { name: "Price summary" });
  await expect(summary.getByTestId("partner-quote-room")).toHaveCount(2);
  await expect(summary.getByText("No commission")).toBeVisible();
  const partnerTotal = async () => money((await summary.getByRole("term").filter({ hasText: "Partner price" }).locator("xpath=following-sibling::*[1]").textContent()) ?? "");
  await expect.poll(partnerTotal).toBeCloseTo(comboTotal, 2);

  // An extra bed in the first room (when the room offers one) is priced per night.
  const room1 = board.locator("li[data-room]").first();
  const addBed = room1.getByRole("button", { name: "Add one: Extra beds · Room 1" });
  let beds = 0;
  if (await addBed.isVisible().catch(() => false)) {
    const before = await partnerTotal();
    await addBed.click();
    beds = 1;
    await expect.poll(partnerTotal).toBeGreaterThan(before);
    await expect(summary.getByTestId("partner-quote-room").first()).toContainText("1 extra bed");
  }

  const continueButton = summary.getByRole("button", { name: "Continue" });
  await expect(continueButton).toBeEnabled();
  const quoted = await partnerTotal();
  await continueButton.click();

  // Client & payment: the whole party (lead + 2 adults + 1 child).
  await expect(page.getByRole("heading", { name: "Client & payment", level: 1 })).toBeVisible();
  const lead = page.getByRole("region", { name: "Lead client" });
  await lead.getByRole("textbox", { name: "First name" }).fill("E2E");
  await lead.getByRole("textbox", { name: "Last name" }).fill("Multiroom");
  await lead.getByRole("textbox", { name: "Email" }).fill("e2e.partner.multiroom@example.test");
  await lead.getByRole("textbox", { name: "Phone (WhatsApp)" }).fill("+20 100 000 0000");
  const others = page.getByRole("region", { name: "Other travellers" }).getByRole("listitem");
  await expect(others).toHaveCount(3);
  for (let i = 0; i < 3; i++) {
    await others.nth(i).getByRole("textbox", { name: "First name" }).fill(`E2E${i + 2}`);
    await others.nth(i).getByRole("textbox", { name: "Last name" }).fill("Multiroom");
  }
  await page.getByRole("radio", { name: /^Bank transfer or cash deposit/ }).check();
  await page.getByRole("textbox", { name: "Notes for our team (optional)" }).fill("E2E automated partner booking, please ignore.");
  await page.getByRole("checkbox", { name: /I confirm the client details/ }).check();

  const created = page.waitForResponse((r) => /\/partner\/bookings(\?|$)/.test(r.url()) && r.request().method() === "POST");
  await page.getByRole("button", { name: "Confirm booking" }).click();
  const response = await created;
  expect(response.status()).toBe(201);
  const sent = response.request().postDataJSON();
  expect(sent.product_type).toBe("room");
  expect(sent.rooms).toHaveLength(2);
  expect(sent.travelers).toHaveLength(4);
  expect(sent.rooms.reduce((n: number, r: { extra_beds: number }) => n + r.extra_beds, 0)).toBe(beds);
  const booking = (await response.json()).data;
  const reference: string = booking.reference;
  expect(booking.rooms).toHaveLength(2);

  // Confirmation: one line per room and the partner total that was quoted.
  await expect(page.getByTestId("booking-reference")).toHaveText(reference);
  const confirmationRooms = page.getByTestId("booking-rooms");
  await expect(confirmationRooms.getByRole("listitem")).toHaveCount(2);
  await expect(confirmationRooms.getByRole("listitem").first()).toContainText(/^Room 1 · /);
  await expect(page.getByRole("term").filter({ hasText: "Partner total" }).locator("xpath=following-sibling::*[1]")).toHaveText(new RegExp(String(Math.round(quoted))));

  // Bookings list, then the detail with the room lines.
  await page.goto("/en/partner/bookings");
  const link = page.getByRole("link", { name: reference }).first();
  await expect(link).toBeVisible();
  await link.click();
  await expect(page.getByRole("heading", { name: `Booking ${reference}`, level: 1 })).toBeVisible();
  const detailRooms = page.getByTestId("booking-rooms");
  await expect(detailRooms.getByRole("listitem")).toHaveCount(2);
  await expect(detailRooms).toContainText("Room 2 · ");
  await expect(detailRooms).toContainText(/1 child/);
  if (beds) await expect(detailRooms).toContainText("1 extra bed");
});

/**
 * GET /rooms/combinations suggests extra beds when they make a combination possible or cheaper. Which party gets one
 * in the first 6 suggestions depends on prices and free rooms, so the party is looked up through the API for the
 * test's dates and the client's nationality. Applying that suggestion sets the room's extra-bed stepper and the quote
 * charges the bed, on the website and in the partner wizard.
 */
type Party = { adults: number; ages: number[] };

async function partyWithExtraBed(dates: { check_in: string; check_out: string }, nationality: string): Promise<Party | null> {
  const api = await apiContext();
  try {
    // Few candidates: GET /rooms/combinations is throttled (60/min) and the page needs its own calls too.
    for (const { adults, ages } of [
      { adults: 6, ages: [3, 5, 7] },
      { adults: 2, ages: [2, 4] },
      { adults: 4, ages: [5, 7] },
      { adults: 2, ages: [5, 7] },
    ]) {
      const query = new URLSearchParams({ building: "hotel-nile-view", ...dates, adults: String(adults), limit: "6", nationality, locale: "en", currency: "EUR" });
      ages.forEach((a) => query.append("child_ages[]", String(a)));
      const res = await api.get(`rooms/combinations?${query}`);
      if (!res.ok()) continue;
      const list = (await res.json()).data as { rooms: { extra_beds?: number }[] }[];
      if (list.some((c) => c.rooms.some((r) => (r.extra_beds ?? 0) === 1))) return { adults, ages };
    }
    return null;
  } finally {
    await api.dispose();
  }
}

async function setAges(page: Page, scope: ReturnType<Page["getByRole"]>, ages: number[]) {
  for (const [i, age] of ages.entries()) {
    await scope.getByRole("combobox", { name: `Age of child ${i + 1}` }).click();
    await page.getByRole("option", { name: `${age} years`, exact: true }).click();
  }
}

async function applyExtraBedCombination(scope: ReturnType<Page["getByRole"]>, party: Party) {
  const combo = scope.getByTestId("combination").filter({ hasText: "1 extra bed" }).first();
  await expect(combo).toBeVisible();
  const roomName = ((await combo.getByRole("listitem").filter({ hasText: "1 extra bed" }).first().locator("span.block").textContent()) ?? "").split(" · ")[0];
  await combo.getByRole("button", { name: /^Choose combination/ }).click();
  const board = scope.getByTestId("allocation-board");
  await expect(board.getByTestId("allocation-status")).toContainText(`All ${party.adults + party.ages.length} guests are in a room and every room is valid`);
  const room = board.locator("li[data-room]").filter({ hasText: roomName });
  const n = (await room.getByRole("heading", { level: 4 }).textContent())?.match(/\d+/)?.[0];
  await expect(room.getByRole("group", { name: `Extra beds · Room ${n}` }).locator("output")).toHaveText("1");
  return { n: Number(n), roomName };
}

test.describe("website visitor", () => {
  // Anonymous visitor (the partner's account nationality would otherwise price the building page).
  test.use({ storageState: { cookies: [], origins: [{ origin: process.env.E2E_BASE_URL ?? "http://sharm-station.loc", localStorage: [{ name: "ssv_cookie_consent", value: "essential" }] }] } });

  test("website: a suggested combination with an extra bed sets the extra-bed stepper", async ({ page, context }) => {
    await setNationality(context, "IT");
    const dates = stay(2);
    const party = await partyWithExtraBed(dates, "IT");
    test.skip(!party, "no party gets an extra-bed suggestion in the first 6 combinations for these dates");
    await page.goto(`/en/buildings/hotel-nile-view?check_in=${dates.check_in}&check_out=${dates.check_out}&adults=${party!.adults}&children=${party!.ages.length}`);
    const section = page.getByRole("region", { name: "Book several rooms" }).first();
    await section.scrollIntoViewIfNeeded();
    await setAges(page, section, party!.ages);
    await applyExtraBedCombination(section, party!);
    await expect(section.getByRole("complementary").getByText(/^Extra bed · 2 × €/)).toBeVisible();
  });
});

test("partner wizard: a suggested combination with an extra bed sets the stepper and the partner quote", async ({ page }) => {
  const dates = stay(2);
  const party = await partyWithExtraBed(dates, "EG");
  test.skip(!party, "no party gets an extra-bed suggestion in the first 6 combinations for these dates");
  await page.goto("/en/partner/new");
  await pickCombo(page, "Egypt");
  await page.getByRole("tab", { name: "Accommodation" }).click();
  await page.getByRole("button", { name: `Select ${ROOM}` }).click();
  const stayCard = page.getByRole("region", { name: "Stay" });
  await stayCard.locator("#cfg-in").fill(dates.check_in);
  await stayCard.locator("#cfg-out").fill(dates.check_out);
  const adultsGroup = stayCard.getByRole("group", { name: "Adults" });
  for (let i = 0; i < 6 && Number(await adultsGroup.locator("output").textContent()) !== party!.adults; i++) {
    await stayCard.getByRole("button", { name: Number(await adultsGroup.locator("output").textContent()) < party!.adults ? "Add one: Adults" : "Remove one: Adults" }).click();
  }
  await expect(adultsGroup.locator("output")).toHaveText(String(party!.adults));
  for (let i = 0; i < party!.ages.length; i++) await stayCard.getByRole("button", { name: "Add one: Children" }).click();
  await setAges(page, stayCard, party!.ages);
  const rooms = page.getByRole("region", { name: "Rooms & allocation" });
  const { n } = await applyExtraBedCombination(rooms, party!);
  const summary = page.getByRole("region", { name: "Price summary" });
  if (await summary.getByTestId("partner-quote-room").count()) await expect(summary.getByTestId("partner-quote-room").nth(n - 1)).toContainText("1 extra bed");
  else await expect(summary.getByText(/Extra bed/).first()).toBeVisible();
  await expect(summary.getByRole("button", { name: "Continue" })).toBeEnabled();
});
