/**
 * Adapters from the real Laravel API v1 payloads (backend/app/Http/Resources/V1,
 * DTOs/Pricing/QuoteResultData) to the UI types in ./types.ts.
 *
 * Contract notes (as implemented by the backend):
 *  - Money is `{ amount_eur, amount, currency }`; the UI always uses `amount_eur`
 *    and converts in the browser with the /meta rates.
 *  - Quote lines carry `type` (adult|child|unit|addon|night|tax) and `meta`.
 *  - Season prices live under `price_list[].prices` (adult/child/private_tiers,
 *    units[] or price_per_night).
 *  - Availability weekdays are ISO (1 = Monday … 7 = Sunday).
 *  - Images are placeholder paths, resolved by ./images.ts.
 */
import { CHILD_BANDS } from "./mock-data";
import { addonImage, buildingImage, destinationImage, roomImage, tripImage } from "./images";
import type {
  AuthResponse,
  AuthUser,
  BookingDetail,
  BookingStatus,
  Destination,
  PartnerStatus,
  PaymentStatus,
  Building,
  ChildAgeBand,
  Currency,
  CurrencyCode,
  DestinationSummary,
  GoogleReviews,
  HomeData,
  Locale,
  Meta,
  Paginated,
  QuoteLine,
  QuoteResult,
  RoomDetail,
  RoomQuoteResult,
  RoomSummary,
  SeasonPrice,
  TripDetail,
  TripSummary,
  TripType,
} from "./types";

/* ------------------------------------------------------------------ raw types */

export interface RawMoney {
  amount_eur: number;
  amount: number;
  currency: string;
}
type Maybe<T> = T | null | undefined;

const eur = (m: Maybe<RawMoney>, fallback = 0) => (m && typeof m.amount_eur === "number" ? m.amount_eur : fallback);

export interface RawTripCard {
  id: number;
  slug: string;
  type: TripType;
  category?: string | null;
  title: string;
  summary: string | null;
  cover_image: string | null;
  destination: { name: string; slug: string } | null;
  duration_hours: number | null;
  days: number | null;
  nights: number | null;
  pricing_mode: "per_person" | "per_unit";
  booking_types: ("private" | "group")[];
  min_persons: number | null;
  max_persons: number | null;
  from_price: RawMoney | null;
  price_unit?: string | null;
  free_cancellation_hours: number | null;
  featured: boolean;
}

interface RawSeason {
  id: number;
  name: string;
  starts_on: string;
  ends_on: string;
  recurring_yearly: boolean;
  is_current?: boolean;
  prices: {
    adult?: RawMoney;
    child?: RawMoney;
    private_tiers?: { tier_id: number; price_per_person: RawMoney }[];
    units?: { unit_option_id: number; price: RawMoney }[];
    price_per_night?: RawMoney;
  };
}

interface RawAvailabilityRule {
  enabled: boolean;
  lead_time_hours: number | null;
  weekdays: number[] | null;
  dates: string[] | null;
  blackout_dates: string[] | null;
}

export interface RawTripDetail extends RawTripCard {
  description: string | null;
  highlights: string[] | null;
  included: string[] | null;
  excluded: string[] | null;
  meeting_point: string | null;
  location: { lat: number; lng: number } | null;
  deposit_percent: number | null;
  group_prices: { adult: RawMoney; child: RawMoney } | null;
  private_tiers: { id: number; label: string; min_people: number; max_people: number; price_per_person: RawMoney }[];
  unit_options: { id: number; name: string; seats: number; price: RawMoney; max_quantity: number; children_allowed: boolean }[];
  price_list: RawSeason[];
  child_policy: {
    children_allowed: boolean;
    adult_from_age: number | null;
    bands: { min_age: number; max_age: number; discount_type: string; discount_value: number | null; label: string }[];
  } | null;
  availability: { private: RawAvailabilityRule | null; group: RawAvailabilityRule | null } | null;
  itinerary: { day_number: number; title: string | null; description: string | null; items: { time_from: string | null; time_to: string | null; title: string; description: string | null; place: string | null }[] }[];
  media: { path: string; alt: string | null }[];
  addons: { id: number; name: string; description: string | null; image: string | null; price: RawMoney; pricing_basis: "per_person" | "per_booking" | "per_unit"; required: boolean; max_quantity: number; booking_types: ("private" | "group")[] }[];
  nile?: { vessel_name: string | null; itinerary_from: string | null; itinerary_to: string | null; vessel_facilities: string[] | null; meal_type: string | null } | null;
  seo: { title?: string; description?: string } | null;
}

export interface RawBuildingCard {
  id: number;
  name: string;
  slug: string;
  type: string;
  stars: number;
  address: string | null;
  cover_image: string | null;
  destination: { name: string; slug: string } | null;
  location: { lat: number; lng: number } | null;
  tax_included: boolean;
  tax_per_night: RawMoney | null;
}

export interface RawRoomCard {
  id: number;
  name: string;
  room_type: string;
  capacity: { max_adults: number; max_children: number; max_total: number };
  beds: string | null;
  size_m2: number | null;
  view: string | null;
  amenities: string[] | null;
  cover_image: string | null;
  from_price: RawMoney | null;
  base_price: RawMoney | null;
  tax_included: boolean;
  min_nights: number | null;
  max_nights: number | null;
  building: RawBuildingCard;
}

export interface RawRoomDetail extends RawRoomCard {
  description: string | null;
  extra_bed_price: RawMoney | null;
  price_list: RawSeason[];
  child_policy: RawTripDetail["child_policy"];
  building_details: { check_in_from: string | null; check_out_until: string | null; amenities: string[] | null; cancellation_policy: string | null; deposit_percent: number | null } | null;
  other_rooms: RawRoomCard[];
}

export interface RawBuildingDetail extends RawBuildingCard {
  description: string | null;
  amenities: string[] | null;
  check_in_from: string | null;
  check_out_until: string | null;
  cancellation_policy: string | null;
  deposit_percent: number | null;
  rooms: RawRoomCard[];
}

export interface RawQuote {
  valid: boolean;
  service_type: string;
  pricing_mode?: string;
  persons?: number;
  adults?: number;
  children?: number;
  nights?: number;
  tax_included?: boolean;
  tax_per_night?: number | null;
  season_name: string | null;
  lines: { type: string; label: string; quantity: number; unit_price: number; total: number; meta?: Record<string, unknown> }[];
  total: number;
  deposit_percent: number;
  deposit_amount: number;
  balance_amount: number;
}

export interface RawReviews {
  rating: number;
  total: number;
  write_review_url: string;
  maps_url?: string | null;
  reviews: { id: number | string; author_name: string; author_photo_url: string | null; rating: number; text: string; language: string; published_at: string; relative_time: string }[];
}

export interface RawBooking {
  reference: string;
  access_token?: string;
  status: string;
  payment_status?: string;
  travelers?: { first_name: string; last_name: string; type: "adult" | "child"; age: number | null }[];
  payment_due_at?: string | null;
  service_type: "trip" | "accommodation" | "room" | string;
  booking_type: "private" | "group" | null;
  season_name: string | null;
  product: { title: string; slug: string | null } | null;
  start_date: string | null;
  check_in: string | null;
  check_out: string | null;
  lead: { name: string; email: string; phone: string | null };
  adults: number;
  children: number;
  lines: RawQuote["lines"];
  totals: {
    total?: number;
    retail_total: number;
    deposit_percent?: number;
    deposit_amount?: number;
    balance_amount?: number;
    currency?: string;
    exchange_rate?: number;
    total_in_currency?: number;
  };
  payment_options?: { code: string; type: "online" | "manual"; name: string }[];
  created_at: string;
}

/* ------------------------------------------------------------------ labels */

const LABELS: Record<string, { en: string; it: string }> = {
  wifi: { en: "Wi-Fi", it: "Wi-Fi" },
  air_conditioning: { en: "Air conditioning", it: "Aria condizionata" },
  minibar: { en: "Minibar", it: "Minibar" },
  safe: { en: "Safe", it: "Cassaforte" },
  balcony: { en: "Balcony", it: "Balcone" },
  breakfast: { en: "Breakfast", it: "Colazione" },
  pool: { en: "Pool", it: "Piscina" },
  beach: { en: "Beach", it: "Spiaggia" },
  spa: { en: "Spa", it: "Spa" },
  parking: { en: "Parking", it: "Parcheggio" },
  restaurant: { en: "Restaurant", it: "Ristorante" },
  bar: { en: "Bar", it: "Bar" },
  sun_terrace: { en: "Sun terrace", it: "Terrazza solarium" },
  tv: { en: "TV", it: "TV" },
  full_board: { en: "Full board", it: "Pensione completa" },
  half_board: { en: "Half board", it: "Mezza pensione" },
  bed_and_breakfast: { en: "Bed and breakfast", it: "Pernottamento e colazione" },
  single: { en: "Single room", it: "Camera singola" },
  double: { en: "Double room", it: "Camera doppia" },
  twin: { en: "Twin room", it: "Camera twin" },
  triple: { en: "Triple room", it: "Camera tripla" },
  family: { en: "Family room", it: "Camera family" },
  suite: { en: "Suite", it: "Suite" },
  hotel: { en: "Hotel", it: "Hotel" },
  resort: { en: "Resort", it: "Resort" },
  apartment: { en: "Apartment", it: "Appartamento" },
};

const humanize = (code: string) => code.replace(/_/g, " ").replace(/^\w/, (c) => c.toUpperCase());
export const label = (code: string, locale: Locale) => LABELS[code]?.[locale] ?? humanize(code);

/* ------------------------------------------------------------------ helpers */

/** ISO weekday (1 Mon … 7 Sun) -> JS getDay() (0 Sun … 6 Sat). */
const isoToJsDay = (d: number) => d % 7;

function childBands(policy: RawTripDetail["child_policy"]): ChildAgeBand[] {
  if (!policy?.bands?.length) return CHILD_BANDS;
  const bands: ChildAgeBand[] = policy.bands.map((b) => ({
    from: b.min_age,
    to: b.max_age,
    pays: b.discount_type === "free" ? "free" : "child",
  }));
  const adultFrom = policy.adult_from_age ?? 12;
  if (adultFrom <= 17) bands.push({ from: adultFrom, to: 17, pays: "adult" });
  return bands;
}

function childPolicyText(policy: RawTripDetail["child_policy"], locale: Locale): string {
  if (!policy) {
    return locale === "it" ? "Bambini benvenuti. Contattaci per le tariffe dei bambini." : "Children are welcome. Contact us for children's prices.";
  }
  if (!policy.children_allowed) return locale === "it" ? "Riservato agli adulti." : "Adults only.";
  const bands = policy.bands.map((b) => b.label).join(" · ");
  const adult = policy.adult_from_age != null ? (locale === "it" ? ` Dai ${policy.adult_from_age} anni si applica la tariffa adulto.` : ` From ${policy.adult_from_age} years the adult price applies.`) : "";
  return `${bands}.${adult}`;
}

function cancellationText(hours: number | null, deposit: number, locale: Locale): string {
  if (!hours) {
    return locale === "it" ? `Paghi il ${deposit}% oggi per prenotare. Condizioni di cancellazione nel voucher.` : `Pay ${deposit}% today to reserve. Cancellation terms are shown on your voucher.`;
  }
  const window = hours % 24 === 0 ? (locale === "it" ? `${hours / 24} ${hours === 24 ? "giorno" : "giorni"}` : `${hours / 24} ${hours === 24 ? "day" : "days"}`) : `${hours} h`;
  return locale === "it"
    ? `Cancellazione gratuita fino a ${window} prima della partenza con rimborso completo. Paghi il ${deposit}% oggi; il saldo dopo.`
    : `Free cancellation up to ${window} before departure for a full refund. Pay ${deposit}% today; the balance later.`;
}

const TRANSFER_RE = /pick-?up|drop-?off|transfer|return to (the|your) hotel|prelievo|rientro|trasferimento/i;

function season(raw: RawSeason, pricingMode: "per_person" | "per_unit" | "room"): SeasonPrice {
  const p = raw.prices ?? {};
  const units = p.units ? Object.fromEntries(p.units.map((u) => [String(u.unit_option_id), eur(u.price)])) : null;
  const tiers = p.private_tiers ? Object.fromEntries(p.private_tiers.map((t) => [String(t.tier_id), eur(t.price_per_person)])) : null;
  const headline =
    pricingMode === "room"
      ? eur(p.price_per_night)
      : p.adult
        ? eur(p.adult)
        : units
          ? Math.min(...Object.values(units))
          : tiers
            ? Math.min(...Object.values(tiers))
            : 0;
  return {
    id: raw.id,
    name: raw.name,
    starts_on: raw.starts_on,
    ends_on: raw.ends_on,
    recurring_yearly: raw.recurring_yearly,
    is_active: true,
    price: headline,
    adult_price: p.adult ? eur(p.adult) : null,
    child_price: p.child ? eur(p.child) : null,
    unit_prices: units,
    tier_prices: tiers,
    price_per_night: p.price_per_night ? eur(p.price_per_night) : null,
  };
}

/* ------------------------------------------------------------------ mappers */

export function mapMeta(raw: {
  locales: Locale[];
  currencies: { code: CurrencyCode; symbol: string; rate: number }[];
  destinations: { name: string; slug: string }[];
  settings?: Record<string, unknown>;
  google?: { rating: number; total: number } | null;
}): Meta {
  const s = raw.settings ?? {};
  return {
    currencies: raw.currencies.map((c): Currency => ({ code: c.code, symbol: c.symbol, rate_to_eur: c.rate, is_enabled: true })),
    locales: raw.locales,
    destinations: raw.destinations.map((d, i) => ({ id: i + 1, slug: d.slug, name: d.name, image: destinationImage(null, d.slug), trips_count: 0 })),
    settings: {
      phone: String(s["site.phone"] ?? ""),
      whatsapp: String(s["site.whatsapp"] ?? ""),
      email: String(s["site.email"] ?? ""),
      default_deposit_percent: 30,
      google_rating: raw.google?.rating ?? 0,
      google_reviews_total: raw.google?.total ?? 0,
    },
  };
}

export function mapDestination(raw: { id: number; name: string; slug: string; image: string | null; trips_count: number; description?: string | null }): DestinationSummary & { description: string } {
  return { id: raw.id, slug: raw.slug, name: raw.name, image: destinationImage(raw.image, raw.slug), trips_count: raw.trips_count, description: raw.description ?? "" };
}

export function mapTripCard(raw: RawTripCard): TripSummary {
  const free = (raw.free_cancellation_hours ?? 0) > 0;
  return {
    id: raw.id,
    slug: raw.slug,
    type: raw.type,
    title: raw.title,
    summary: raw.summary ?? "",
    destination: raw.destination ?? { slug: "", name: "" },
    image: { url: tripImage(raw.cover_image, raw.slug), alt: raw.title },
    duration_hours: raw.duration_hours,
    days: raw.days,
    nights: raw.nights,
    rating: null,
    reviews_count: null,
    price_from: eur(raw.from_price),
    pricing_mode: raw.pricing_mode,
    unit_label: null,
    booking_types: raw.booking_types ?? [],
    free_cancellation: free,
    badges: free ? ["free_cancellation"] : [],
    featured: raw.featured,
    created_at: "",
  };
}

/** Package cards from GET /packages and GET /home (PackageCardResource). */
export interface RawPackageCard {
  id: number;
  slug: string;
  title: string;
  summary: string | null;
  cover_image: string | null;
  days: number;
  nights: number;
  pricing_mode: "per_person" | "per_unit";
  from_price: RawMoney | null;
  stages?: { destination: string | null; destination_slug?: string | null; days: number; nights: number }[];
  destination?: { name: string; slug: string } | null;
  booking_types?: ("private" | "group")[];
  free_cancellation_hours?: number | null;
  featured?: boolean;
  rating?: number | null;
  reviews_count?: number | null;
}

export function mapPackageCard(raw: RawPackageCard): TripSummary {
  const free = (raw.free_cancellation_hours ?? 0) > 0;
  const first = raw.stages?.[0];
  return {
    id: raw.id,
    slug: raw.slug,
    type: "package",
    title: raw.title,
    summary: raw.summary ?? "",
    destination: raw.destination ?? { slug: first?.destination_slug ?? "", name: first?.destination ?? "" },
    route: raw.stages?.map((s) => ({ name: s.destination ?? "", days: s.days })),
    image: { url: tripImage(raw.cover_image, raw.slug), alt: raw.title },
    days: raw.days,
    nights: raw.nights,
    duration_hours: null,
    rating: raw.rating ?? null,
    reviews_count: raw.reviews_count ?? null,
    price_from: eur(raw.from_price),
    pricing_mode: raw.pricing_mode,
    unit_label: null,
    booking_types: raw.booking_types ?? ["private", "group"],
    free_cancellation: free,
    badges: ["transfers_included"],
    featured: raw.featured ?? true,
    created_at: "",
  };
}

/** GET /packages/{slug} (PackageResource) -> TripDetail with stages and included transfers. */
export interface RawPackageDetail extends Omit<RawPackageCard, "stages"> {
  description: string | null;
  highlights: string[] | null;
  included: string[] | null;
  excluded: string[] | null;
  deposit_percent: number | null;
  group_prices: { adult: RawMoney; child: RawMoney } | null;
  stages: { position: number; destination: { name: string; slug: string } | null; days: number; nights: number; accommodation: string | null; trips: { day_number: number; trip: { title: string } | null }[] }[];
  itinerary: { day_number: number; destination: string | null; accommodation: string | null; items: { type: string; time: string | null; title: string; transfer_included?: boolean }[] }[];
  price_list: RawTripDetail["price_list"];
  child_policy: RawTripDetail["child_policy"];
  addons: RawTripDetail["addons"];
  availability: Record<string, { enabled: boolean; lead_time_hours: number | null }> | null;
  faq?: { question: string; answer: string }[] | null;
  what_to_bring?: string[] | null;
  guide_languages?: string[] | null;
  cancellation_policy?: string | null;
  seo: { title?: string; description?: string } | null;
}

export function mapPackageDetail(raw: RawPackageDetail, locale: Locale): TripDetail {
  const rule = (r?: { enabled: boolean; lead_time_hours: number | null }) =>
    r ? { enabled: r.enabled, lead_time_hours: r.lead_time_hours, weekdays: null, dates: null, blackout_dates: null } : null;
  const asTrip: RawTripDetail = {
    ...raw,
    type: "package",
    destination: raw.destination ?? (raw.stages[0]?.destination ? raw.stages[0].destination : null),
    duration_hours: null,
    min_persons: (raw as { min_persons?: number }).min_persons ?? 1,
    max_persons: (raw as { max_persons?: number | null }).max_persons ?? null,
    booking_types: raw.booking_types ?? ["private", "group"],
    free_cancellation_hours: raw.free_cancellation_hours ?? null,
    featured: raw.featured ?? false,
    meeting_point: null,
    location: null,
    private_tiers: [],
    unit_options: [],
    availability: { private: rule(raw.availability?.private), group: rule(raw.availability?.group) },
    itinerary: (raw.itinerary ?? []).map((d) => ({
      day_number: d.day_number,
      title: d.destination,
      description: d.accommodation,
      items: d.items.map((i) => ({ time_from: i.time, time_to: null, title: i.title, description: null, place: null })),
    })),
    media: [],
    nile: null,
  };
  const detail = mapTripDetail(asTrip, locale);
  const card = mapPackageCard({ ...raw, stages: raw.stages.map((s) => ({ destination: s.destination?.name ?? null, destination_slug: s.destination?.slug, days: s.days, nights: s.nights })) });
  return {
    ...detail,
    route: card.route,
    rating: card.rating,
    reviews_count: card.reviews_count,
    badges: card.free_cancellation ? ["transfers_included", "free_cancellation"] : ["transfers_included"],
    timeline: detail.timeline.map((day, di) => ({
      ...day,
      items: day.items.map((item, ii) => ({ ...item, transfer_included: Boolean(raw.itinerary?.[di]?.items?.[ii]?.transfer_included) || item.transfer_included })),
    })),
    stages: raw.stages.map((s) => ({
      destination: s.destination?.name ?? "",
      days: s.days,
      nights: s.nights,
      description: s.trips.map((t) => t.trip?.title).filter(Boolean).join(" · "),
      accommodation: s.accommodation,
    })),
    transfers_included: true,
    faq: raw.faq ?? [],
    what_to_bring: raw.what_to_bring ?? [],
    guide_languages: raw.guide_languages ?? [],
    policies: raw.cancellation_policy ? { ...detail.policies, cancellation: raw.cancellation_policy } : detail.policies,
  };
}

/** "Single buggy", "Double buggy" -> "buggy" (the API has no unit label yet). */
function unitLabel(names: string[]): string | null {
  const last = names.map((n) => n.trim().split(/\s+/).pop()?.toLowerCase() ?? "");
  return last.length && last.every((w) => w && w === last[0]) ? last[0] : null;
}

export function mapTripDetail(raw: RawTripDetail, locale: Locale): TripDetail {
  const card = { ...mapTripCard(raw), unit_label: unitLabel((raw.unit_options ?? []).map((u) => u.name)) };
  const deposit = raw.deposit_percent ?? 30;
  const mode = raw.pricing_mode;
  const privateRule = raw.availability?.private;
  const groupRule = raw.availability?.group;
  const media = raw.media?.length ? raw.media : [{ path: raw.cover_image ?? "", alt: raw.title }];
  const facilities = raw.nile?.vessel_facilities ?? [];

  return {
    ...card,
    image: { url: tripImage(raw.cover_image, raw.slug, 0), alt: raw.title },
    description: raw.description ?? raw.summary ?? "",
    gallery: media.map((m, i) => ({ url: tripImage(m.path, raw.slug, i), alt: m.alt ?? `${raw.title} ${i + 1}` })),
    highlights: raw.highlights ?? [],
    included: raw.included ?? [],
    excluded: raw.excluded ?? [],
    what_to_bring: [],
    timeline: (raw.itinerary ?? []).map((d) => ({
      day_number: d.day_number,
      title: d.title,
      items: d.items.map((i) => ({
        time_from: i.time_from,
        time_to: i.time_to,
        title: i.title,
        description: i.description ?? "",
        place: i.place,
        // The API has no per-item flag yet: pick-up / drop-off items are hotel transfers included in the price.
        transfer_included: TRANSFER_RE.test(i.title),
      })),
    })),
    meeting_point: {
      text: raw.meeting_point ?? "",
      lat: raw.location?.lat ?? 0,
      lng: raw.location?.lng ?? 0,
      label: raw.destination?.name ?? "",
    },
    min_persons: raw.min_persons ?? 1,
    max_persons: raw.max_persons ?? 99,
    base_price: raw.group_prices ? eur(raw.group_prices.adult) : card.price_from,
    base_child_price: raw.group_prices ? eur(raw.group_prices.child) : card.price_from,
    private_tiers: (raw.private_tiers ?? []).map((t) => ({ id: t.id, min_people: t.min_people, max_people: t.max_people, price_per_person: eur(t.price_per_person) })),
    unit_options: (raw.unit_options ?? []).map((u) => ({ id: u.id, name: u.name, seats: u.seats, price: eur(u.price), max_quantity: u.max_quantity, children_allowed: u.children_allowed })),
    price_list: (raw.price_list ?? []).map((s) => season(s, mode)),
    addons: (raw.addons ?? []).map((a) => ({
      id: a.id,
      name: a.name,
      description: a.description ?? "",
      image: addonImage(a.image, a.name, raw.slug),
      price: eur(a.price),
      pricing_basis: a.pricing_basis,
      required: a.required,
      max_quantity: a.max_quantity,
      booking_types: a.booking_types,
    })),
    availability: {
      private: privateRule?.enabled ? { lead_time_hours: privateRule.lead_time_hours ?? 24, blackout_dates: privateRule.blackout_dates ?? [] } : null,
      group: groupRule?.enabled
        ? { weekdays: (groupRule.weekdays ?? [1, 2, 3, 4, 5, 6, 7]).map(isoToJsDay), dates: groupRule.dates ?? [], blackout_dates: groupRule.blackout_dates ?? [] }
        : null,
    },
    child_age_bands: childBands(raw.child_policy),
    deposit_percent: deposit,
    free_cancellation_hours: raw.free_cancellation_hours ?? 0,
    policies: { cancellation: cancellationText(raw.free_cancellation_hours, deposit, locale), children: childPolicyText(raw.child_policy, locale) },
    faq: [],
    guide_languages: [],
    vessel:
      raw.type === "nile" && raw.nile?.vessel_name
        ? {
            name: raw.nile.vessel_name,
            itinerary_from: raw.nile.itinerary_from ?? "",
            itinerary_to: raw.nile.itinerary_to ?? "",
            facilities: facilities.map((f) => label(f, locale)),
            meal_type: raw.nile.meal_type ? label(raw.nile.meal_type, locale) : "",
            cabins: [],
          }
        : null,
    stages: null,
    transfers_included: false,
    seo: raw.seo,
  };
}

export function mapPaginated<R, T>(raw: { data: R[]; meta: { current_page: number; last_page: number; per_page: number; total: number } }, map: (r: R) => T): Paginated<T> {
  return {
    data: raw.data.map(map),
    meta: { current_page: raw.meta.current_page, last_page: raw.meta.last_page, per_page: raw.meta.per_page, total: raw.meta.total },
  };
}

function mapBuildingCard(b: RawBuildingCard): RoomSummary["building"] & { destination_slug: string } {
  return { slug: b.slug, name: b.name, stars: b.stars, location: b.destination?.name ?? b.address ?? "", taxes_included: b.tax_included, destination_slug: b.destination?.slug ?? "" };
}

export function mapRoomCard(raw: RawRoomCard, locale: Locale, priceFrom: "from" | "base" = "from"): RoomSummary {
  const amenities = raw.amenities ?? [];
  const { destination_slug: _destinationSlug, ...building } = mapBuildingCard(raw.building);
  void _destinationSlug;
  return {
    id: raw.id,
    name: raw.name,
    room_type: raw.room_type,
    type_label: [label(raw.room_type, locale), raw.view].filter(Boolean).join(", "),
    building,
    image: { url: roomImage(raw.cover_image, raw.room_type), alt: raw.name },
    max_adults: raw.capacity.max_adults,
    max_children: raw.capacity.max_children,
    max_total: raw.capacity.max_total,
    beds: 1,
    bed_label: raw.beds ?? "",
    size_m2: raw.size_m2 ?? 0,
    view: raw.view,
    amenities: amenities.map((a) => label(a, locale)),
    price_per_night: priceFrom === "from" ? eur(raw.from_price, eur(raw.base_price)) : eur(raw.base_price, eur(raw.from_price)),
    tax_included: raw.tax_included,
    tax_per_night: raw.tax_included ? null : eur(raw.building.tax_per_night) || null,
    // Not in the API card yet (see contract gaps): no free-cancellation or few-left flags.
    free_cancellation: false,
    breakfast_included: amenities.includes("breakfast"),
    few_left: false,
    min_nights: raw.min_nights ?? 1,
    max_nights: raw.max_nights ?? 30,
  };
}

function mapBuilding(b: RawBuildingCard, details: Partial<RawBuildingDetail>, locale: Locale): Building {
  return {
    id: b.id,
    slug: b.slug,
    name: b.name,
    type: label(b.type, locale),
    stars: b.stars,
    location: b.destination?.name ?? "",
    address: b.address ?? "",
    lat: b.location?.lat ?? 0,
    lng: b.location?.lng ?? 0,
    description: details.description ?? "",
    amenities: (details.amenities ?? []).map((a) => label(a, locale)),
    check_in_from: details.check_in_from ?? "14:00",
    check_out_until: details.check_out_until ?? "12:00",
    cancellation_policy: details.cancellation_policy ?? "",
    taxes_included: b.tax_included,
    tax_per_night: eur(b.tax_per_night),
    deposit_percent: details.deposit_percent ?? 30,
    image: { url: buildingImage(b.cover_image, b.slug, b.destination?.slug), alt: b.name },
  };
}

export function mapRoomDetail(raw: RawRoomDetail, locale: Locale): RoomDetail {
  const card = mapRoomCard(raw, locale, "base");
  const details = raw.building_details ?? null;
  const building = mapBuilding(raw.building, { ...details, description: undefined }, locale);
  return {
    ...card,
    free_cancellation: /free cancellation|cancellazione gratuita/i.test(details?.cancellation_policy ?? ""),
    description: raw.description ?? `${card.type_label} · ${raw.beds ?? ""}${raw.size_m2 ? ` · ${raw.size_m2} m²` : ""}`,
    gallery: [
      { url: roomImage(raw.cover_image, raw.room_type, 0), alt: raw.name },
      { url: roomImage(null, raw.room_type, 1), alt: `${raw.name} 2` },
      { url: building.image.url, alt: raw.building.name },
    ],
    price_list: (raw.price_list ?? []).map((s) => season(s, "room")),
    building_detail: building,
    other_rooms: (raw.other_rooms ?? []).map((r) => mapRoomCard(r, locale)),
    child_policy: childPolicyText(raw.child_policy, locale),
    extra_bed_price: eur(raw.extra_bed_price),
    deposit_percent: details?.deposit_percent ?? 30,
  };
}

export function mapBuildingDetail(raw: RawBuildingDetail, locale: Locale): Building & { rooms: RoomSummary[] } {
  return { ...mapBuilding(raw, raw, locale), rooms: (raw.rooms ?? []).map((r) => mapRoomCard(r, locale)) };
}

export function mapReviews(raw: RawReviews): GoogleReviews {
  return {
    rating: raw.rating,
    total: raw.total,
    write_review_url: raw.write_review_url,
    reviews_url: raw.maps_url ?? raw.write_review_url,
    reviews: raw.reviews.map((r) => ({
      id: String(r.id),
      author_name: r.author_name,
      author_photo_url: r.author_photo_url,
      author_country: null,
      rating: r.rating,
      text: r.text,
      language: r.language,
      relative_time: r.relative_time,
      published_at: r.published_at,
    })),
  };
}

export function mapHome(
  raw: {
    stats: { google_rating: number; google_reviews: number };
    featured_trips: RawTripCard[];
    nile_trips: RawTripCard[];
    packages: RawPackageCard[];
    rooms: RawRoomCard[];
    destinations: Parameters<typeof mapDestination>[0][];
    google_reviews: RawReviews | null;
  },
  locale: Locale,
): HomeData {
  return {
    hero_stats: { rating: raw.stats.google_rating, reviews_total: raw.stats.google_reviews, deposit_percent: 30 },
    featured_trips: raw.featured_trips.map(mapTripCard),
    packages: raw.packages.map(mapPackageCard),
    rooms: raw.rooms.map((r) => mapRoomCard(r, locale)),
    nile: raw.nile_trips.map(mapTripCard),
    destinations: raw.destinations.map(mapDestination),
    google_reviews: raw.google_reviews
      ? mapReviews(raw.google_reviews)
      : { rating: raw.stats.google_rating, total: raw.stats.google_reviews, write_review_url: "", reviews_url: "", reviews: [] },
  };
}

/** QuoteResultData -> UI quote. Night lines are grouped by price ("3 nights × €110"). */
export function mapQuote(raw: RawQuote): QuoteResult & { nights?: number; tax_included?: boolean } {
  const lines: QuoteLine[] = [];
  const addonLines: QuoteLine[] = [];
  const nights = new Map<number, number>();
  for (const l of raw.lines ?? []) {
    switch (l.type) {
      case "adult":
        lines.push({ kind: "adult", quantity: l.quantity, unit_price: l.unit_price, total: l.total });
        break;
      case "child":
        // Server label names the age band ("Child 4-11 years (50% off)").
        lines.push({ kind: l.unit_price === 0 ? "child_free" : "child", quantity: l.quantity, unit_price: l.unit_price, total: l.total, label: l.quantity > 1 ? `${l.label} × ${l.quantity}` : l.label });
        break;
      case "night":
        nights.set(l.unit_price, (nights.get(l.unit_price) ?? 0) + l.quantity);
        break;
      case "addon":
        addonLines.push({ kind: "addon", name: l.label, quantity: l.quantity, unit_price: l.unit_price, total: l.total });
        break;
      case "tax":
        lines.push({ kind: "tax", label: l.label, quantity: l.quantity, unit_price: l.unit_price, total: l.total });
        break;
      default:
        lines.push({ kind: "unit", name: l.label, quantity: l.quantity, unit_price: l.unit_price, total: l.total });
    }
  }
  const nightLines: QuoteLine[] = [...nights.entries()].map(([price, count]) => ({ kind: "night", quantity: count, unit_price: price, total: Math.round(price * count * 100) / 100 }));
  const all = [...nightLines, ...lines];
  const addonsTotal = addonLines.reduce((s, l) => s + l.total, 0);
  const adult = all.find((l) => l.kind === "adult");
  return {
    currency: "EUR",
    season: raw.season_name ? { name: raw.season_name } : null,
    lines: all,
    addon_lines: addonLines,
    subtotal: Math.round((raw.total - addonsTotal) * 100) / 100,
    addons_total: addonsTotal,
    total: raw.total,
    deposit_percent: raw.deposit_percent,
    deposit: raw.deposit_amount,
    balance: raw.balance_amount,
    per_person_price: raw.pricing_mode === "per_person" && adult ? adult.unit_price : null,
    seats_total: null,
    persons: raw.persons ?? (raw.adults ?? 0) + (raw.children ?? 0),
    nights: raw.nights,
    tax_included: raw.tax_included,
  };
}

export function mapRoomQuote(raw: RawQuote): RoomQuoteResult {
  const q = mapQuote(raw);
  return { ...q, nights: raw.nights ?? 0, tax_included: Boolean(raw.tax_included) };
}

export function mapBooking(raw: RawBooking): BookingDetail {
  const currency = (raw.totals.currency ?? "EUR") as CurrencyCode;
  const rate = raw.totals.exchange_rate ?? 1;
  const total = raw.totals.total ?? raw.totals.retail_total;
  const deposit = raw.totals.deposit_amount ?? 0;
  return {
    reference: raw.reference,
    access_token: raw.access_token,
    status: (BOOKING_STATUSES.includes(raw.status as BookingStatus) ? raw.status : "awaiting_payment") as BookingStatus,
    payment_status: raw.payment_status as PaymentStatus | undefined,
    totals: {
      total,
      deposit,
      balance: raw.totals.balance_amount ?? total - deposit,
      currency,
      total_converted: raw.totals.total_in_currency ?? total * rate,
      deposit_converted: Math.round(deposit * rate * 100) / 100,
      exchange_rate: rate,
    },
    payment_options: raw.payment_options ?? [],
    service_type: raw.service_type === "trip" ? "trip" : "room",
    product_title: raw.product?.title ?? raw.reference,
    product_image: null,
    booking_type: raw.booking_type,
    travel_date: raw.start_date,
    check_in: raw.check_in,
    check_out: raw.check_out,
    adults: raw.adults,
    children: raw.children,
    lead: { name: raw.lead.name, email: raw.lead.email, phone: raw.lead.phone ?? "" },
    season_name: raw.season_name,
    lines: mapQuote({ ...(raw as unknown as RawQuote), total, deposit_amount: deposit, balance_amount: 0, deposit_percent: 0, season_name: raw.season_name }).lines,
    travellers: raw.travelers?.map((t) => ({ first_name: t.first_name, last_name: t.last_name, type: t.type, age: t.age })),
    payment_due_at: raw.payment_due_at ?? null,
    created_at: raw.created_at,
  };
}

const BOOKING_STATUSES: BookingStatus[] = ["awaiting_payment", "awaiting_receipt_review", "confirmed", "fully_paid", "completed", "cancelled", "refunded"];

export interface RawUser {
  id: number;
  name: string;
  email: string;
  role: string;
  phone?: string | null;
  locale?: string | null;
  currency?: string | null;
  partner?: { id: number; company: string; status: string; show_retail_price: boolean } | null;
}

/** UserResource -> AuthUser. */
export function mapUser(raw: RawUser): AuthUser {
  return {
    id: raw.id,
    name: raw.name,
    email: raw.email,
    role: raw.role as AuthUser["role"],
    phone: raw.phone ?? null,
    locale: raw.locale === "it" || raw.locale === "en" ? raw.locale : null,
    currency: (raw.currency as CurrencyCode | null) ?? null,
    partner: raw.partner ? { ...raw.partner, status: raw.partner.status as PartnerStatus } : null,
  };
}

/** Money can come as a plain EUR number or as `{ amount_eur }` (MoneyResource). */
export const moneyEur = (v: unknown): number => {
  if (typeof v === "number") return v;
  if (v && typeof v === "object" && "amount_eur" in v && typeof (v as RawMoney).amount_eur === "number") return (v as RawMoney).amount_eur;
  return 0;
};

/** GET /destinations/{slug}: the destination with its trips (all types) and buildings. */
export interface RawDestinationDetail {
  id: number;
  name: string;
  slug: string;
  description: string | null;
  image: string | null;
  trips_count: number;
  trips?: RawTripCard[];
  buildings?: (RawBuildingCard & { google?: { rating: number; total: number } | null })[];
}

export function mapDestinationDetail(raw: RawDestinationDetail): Destination & { trips: TripSummary[]; buildings: (Building & { google_rating: number | null; google_total: number | null })[] } {
  return {
    ...mapDestination(raw),
    trips: (raw.trips ?? []).map(mapTripCard),
    buildings: (raw.buildings ?? []).map((b) => ({ ...mapBuilding(b, {}, "en"), google_rating: b.google?.rating ?? null, google_total: b.google?.total ?? null })),
  };
}

export function mapAuth(raw: { data: RawUser; token: string }): AuthResponse {
  return { token: raw.token, user: mapUser(raw.data) };
}
