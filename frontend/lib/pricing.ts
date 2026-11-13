/**
 * Client-side mirror of the backend PricingEngine (docs/ARCHITECTURE.md §4).
 * The server is the source of truth; this is used when running on mock data
 * (NEXT_PUBLIC_USE_MOCKS=true or API unreachable) and for instant UI hints.
 */
import type {
  QuoteLine,
  QuoteResult,
  RoomDetail,
  RoomQuoteRequest,
  RoomQuoteResult,
  SeasonPrice,
  TripDetail,
  TripQuoteRequest,
} from "./api/types";
import { addDays, nightsBetween, parseISODate, round2, toISODate } from "./utils";

function monthDay(date: Date): number {
  return (date.getMonth() + 1) * 100 + date.getDate();
}

/** True when `date` falls inside the season (inclusive; handles year-crossing and recurring ranges). */
export function seasonContains(season: SeasonPrice, date: Date): boolean {
  if (!season.is_active) return false;
  const start = parseISODate(season.starts_on);
  const end = parseISODate(season.ends_on);
  if (!season.recurring_yearly) {
    return date >= start && date <= end;
  }
  const md = monthDay(date);
  const s = monthDay(start);
  const e = monthDay(end);
  return s <= e ? md >= s && md <= e : md >= s || md <= e;
}

export function seasonForDate(seasons: SeasonPrice[], date: string | Date | null | undefined): SeasonPrice | null {
  if (!date) return null;
  const d = typeof date === "string" ? parseISODate(date) : date;
  return seasons.find((s) => seasonContains(s, d)) ?? null;
}

/** Concrete start/end for a recurring season around `ref` (for "1 Nov – 19 Dec" notes). */
export function seasonRange(season: SeasonPrice, ref: Date = new Date()): { start: Date; end: Date } {
  const start = parseISODate(season.starts_on);
  const end = parseISODate(season.ends_on);
  if (!season.recurring_yearly) return { start, end };
  const s = new Date(ref.getFullYear(), start.getMonth(), start.getDate());
  let e = new Date(ref.getFullYear(), end.getMonth(), end.getDate());
  if (e < s) e = new Date(ref.getFullYear() + 1, end.getMonth(), end.getDate());
  return { start: s, end: e };
}

export interface PartyValidation {
  persons: number;
  /** Translation key under `booking.errors` + params. */
  errors: { key: string; values?: Record<string, string | number> }[];
  seatsTotal: number;
  unitsTotal: number;
}

export function validateParty(trip: TripDetail, req: Pick<TripQuoteRequest, "adults" | "children" | "child_ages" | "units">): PartyValidation {
  const persons = req.adults + req.children;
  const errors: PartyValidation["errors"] = [];
  let seatsTotal = 0;
  let unitsTotal = 0;

  if (req.adults < 1) errors.push({ key: "minAdult" });
  if (req.child_ages.length < req.children || req.child_ages.some((a) => a == null || Number.isNaN(a))) {
    errors.push({ key: "childAges" });
  }

  if (trip.pricing_mode === "per_person") {
    if (persons > trip.max_persons) errors.push({ key: "maxPersons", values: { max: trip.max_persons } });
    if (persons < trip.min_persons) errors.push({ key: "minPersons", values: { min: trip.min_persons } });
  } else {
    for (const u of req.units) {
      const option = trip.unit_options.find((o) => o.id === u.unit_option_id);
      if (!option || u.quantity <= 0) continue;
      seatsTotal += option.seats * u.quantity;
      unitsTotal += u.quantity;
      if (u.quantity > option.max_quantity) errors.push({ key: "maxQuantity", values: { name: option.name, max: option.max_quantity } });
    }
    if (unitsTotal === 0) errors.push({ key: "chooseUnit" });
    else if (seatsTotal < persons) errors.push({ key: "notEnoughSeats", values: { seats: seatsTotal, persons } });
    else if (unitsTotal > persons) errors.push({ key: "emptyUnit" });
    if (unitsTotal > 0 && req.adults < unitsTotal) errors.push({ key: "adultPerUnit" });
  }
  return { persons, errors, seatsTotal, unitsTotal };
}

function childPays(trip: TripDetail, age: number): "free" | "child" | "adult" {
  const band = trip.child_age_bands.find((b) => age >= b.from && age <= b.to);
  return band?.pays ?? "child";
}

export function quoteTrip(trip: TripDetail, req: TripQuoteRequest): QuoteResult {
  const season = seasonForDate(trip.price_list, req.travel_date);
  const persons = req.adults + req.children;
  const lines: QuoteLine[] = [];
  let perPerson: number | null = null;
  let seatsTotal: number | null = null;

  if (trip.pricing_mode === "per_unit") {
    seatsTotal = 0;
    for (const u of req.units) {
      const option = trip.unit_options.find((o) => o.id === u.unit_option_id);
      if (!option || u.quantity <= 0) continue;
      const price = season?.unit_prices?.[String(option.id)] ?? option.price;
      seatsTotal += option.seats * u.quantity;
      lines.push({ kind: "unit", name: option.name, quantity: u.quantity, unit_price: price, total: round2(price * u.quantity) });
    }
  } else {
    let adultPrice: number;
    let childPrice: number;
    if (req.booking_type === "private" && trip.private_tiers.length) {
      const tier =
        trip.private_tiers.find((t) => persons >= t.min_people && persons <= t.max_people) ??
        trip.private_tiers[trip.private_tiers.length - 1];
      adultPrice = season?.tier_prices?.[String(tier.id)] ?? tier.price_per_person;
      childPrice = adultPrice;
    } else {
      adultPrice = season?.adult_price ?? trip.base_price;
      childPrice = season?.child_price ?? trip.base_child_price;
    }
    perPerson = adultPrice;
    if (req.adults > 0) {
      lines.push({ kind: "adult", quantity: req.adults, unit_price: adultPrice, total: round2(adultPrice * req.adults) });
    }
    const ages = req.child_ages.slice(0, req.children);
    for (const age of ages) {
      const pays = childPays(trip, age);
      if (pays === "free") lines.push({ kind: "child_free", quantity: 1, unit_price: 0, total: 0, age });
      else {
        const p = pays === "adult" ? adultPrice : childPrice;
        lines.push({ kind: "child", quantity: 1, unit_price: p, total: p, age });
      }
    }
    // Children without an age yet are priced at the child rate so the total is not misleading.
    for (let i = ages.length; i < req.children; i++) {
      lines.push({ kind: "child", quantity: 1, unit_price: childPrice, total: childPrice, age: null });
    }
  }

  const subtotal = round2(lines.reduce((sum, l) => sum + l.total, 0));
  const unitsCount = req.units.reduce((s, u) => s + u.quantity, 0);
  const addonLines: QuoteLine[] = [];
  for (const a of trip.addons) {
    const selected = req.addons.find((x) => x.addon_id === a.id);
    if (!selected && !a.required) continue;
    if (!a.booking_types.includes(req.booking_type)) continue;
    const qty =
      a.pricing_basis === "per_person"
        ? persons
        : a.pricing_basis === "per_unit"
          ? Math.max(1, Math.min(selected?.quantity ?? unitsCount, a.max_quantity))
          : 1;
    addonLines.push({ kind: "addon", name: a.name, quantity: qty, unit_price: a.price, total: round2(a.price * qty) });
  }
  const addonsTotal = round2(addonLines.reduce((s, l) => s + l.total, 0));
  const total = round2(subtotal + addonsTotal);
  const deposit = round2((total * trip.deposit_percent) / 100);

  return {
    currency: "EUR",
    season: season ? { name: season.name, starts_on: season.starts_on, ends_on: season.ends_on } : null,
    lines,
    addon_lines: addonLines,
    subtotal,
    addons_total: addonsTotal,
    total,
    deposit_percent: trip.deposit_percent,
    deposit,
    balance: round2(total - deposit),
    per_person_price: perPerson,
    seats_total: seatsTotal,
    persons,
  };
}

/** Accommodation: each night priced by the season that contains it (SRS 3.8). */
export function quoteRoom(room: RoomDetail, req: RoomQuoteRequest): RoomQuoteResult {
  const nights = nightsBetween(req.check_in, req.check_out);
  const grouped = new Map<number, number>();
  let cursor = parseISODate(req.check_in);
  for (let i = 0; i < nights; i++) {
    const season = seasonForDate(room.price_list, cursor);
    const price = season?.price_per_night ?? room.price_per_night;
    grouped.set(price, (grouped.get(price) ?? 0) + 1);
    cursor = addDays(cursor, 1);
  }
  const lines: QuoteLine[] = [...grouped.entries()].map(([price, count]) => ({
    kind: "night",
    quantity: count,
    unit_price: price,
    total: round2(price * count),
  }));
  const total = round2(lines.reduce((s, l) => s + l.total, 0));
  const deposit = round2((total * room.deposit_percent) / 100);
  const season = seasonForDate(room.price_list, req.check_in);
  return {
    currency: "EUR",
    season: season ? { name: season.name, starts_on: season.starts_on, ends_on: season.ends_on } : null,
    lines,
    addon_lines: [],
    subtotal: total,
    addons_total: 0,
    total,
    deposit_percent: room.deposit_percent,
    deposit,
    balance: round2(total - deposit),
    persons: req.adults + req.children,
    nights,
    tax_included: room.tax_included,
  };
}

/** Earliest bookable travel date for a booking type (YYYY-MM-DD). */
export function firstAvailableDate(trip: TripDetail, bookingType: "private" | "group", from: Date = new Date()): string {
  let d = addDays(from, 1);
  for (let i = 0; i < 400; i++) {
    if (isDateAvailable(trip, bookingType, toISODate(d), from)) return toISODate(d);
    d = addDays(d, 1);
  }
  return toISODate(addDays(from, 1));
}

export function isDateAvailable(trip: TripDetail, bookingType: "private" | "group", iso: string, now: Date = new Date()): boolean {
  const date = parseISODate(iso);
  if (bookingType === "private") {
    const rule = trip.availability.private;
    if (!rule) return false;
    const earliest = new Date(now.getTime() + rule.lead_time_hours * 3_600_000);
    const endOfDay = new Date(date);
    endOfDay.setHours(23, 59, 59);
    if (endOfDay < earliest) return false;
    return !rule.blackout_dates.includes(iso);
  }
  const rule = trip.availability.group;
  if (!rule) return false;
  if (date <= now) return false;
  if (rule.blackout_dates.includes(iso)) return false;
  return rule.weekdays.includes(date.getDay()) || rule.dates.includes(iso);
}
