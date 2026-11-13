/**
 * In-memory implementation of the API v1 contract on top of mock-data.ts.
 * Each function returns exactly what the Laravel endpoint returns (unwrapped from `data`).
 */
import { quoteRoom, quoteTrip, seasonForDate } from "../pricing";
import { GOOGLE_REVIEW_URL } from "../site";
import { round2 } from "../utils";
import {
  CHILD_BANDS,
  MOCK_BUILDINGS,
  MOCK_CURRENCIES,
  MOCK_DESTINATIONS,
  MOCK_GOOGLE,
  MOCK_PACKAGES,
  MOCK_REVIEWS,
  MOCK_ROOMS,
  MOCK_TRANSFER_HUBS,
  MOCK_TRIPS,
  MOCK_VEHICLES,
  PRIVATE_LEAD_HOURS,
  STANDARD_FAQ,
  STANDARD_POLICIES,
  pick,
  type MockRoom,
  type MockSeason,
  type MockTrip,
} from "./mock-data";
import type {
  Building,
  BookingDetail,
  CreateBookingRequest,
  CurrencyCode,
  Destination,
  DestinationSummary,
  GoogleReviews,
  HomeData,
  Locale,
  Meta,
  Paginated,
  RoomDetail,
  RoomFilters,
  RoomQuoteRequest,
  RoomSummary,
  SeasonPrice,
  TransferQuoteRequest,
  TransferQuoteResult,
  TripDetail,
  TripFilters,
  TripQuoteRequest,
  TripSummary,
} from "./types";

const ALL_PRODUCTS = [...MOCK_TRIPS, ...MOCK_PACKAGES];

function season(s: MockSeason, locale: Locale): SeasonPrice {
  return { ...s, name: pick(s.name, locale) };
}

/** "Prices from": lowest price among active seasons (all are recurring, so all are current or future). */
function priceFrom(t: { price_list: MockSeason[]; base_price: number }) {
  const prices = t.price_list.filter((s) => s.is_active).map((s) => s.price);
  return prices.length ? Math.min(...prices) : t.base_price;
}

function destinationSummary(slug: string, locale: Locale): { slug: string; name: string } {
  const d = MOCK_DESTINATIONS.find((x) => x.slug === slug);
  return { slug, name: d ? pick(d.name, locale) : slug };
}

function tripSummary(t: MockTrip, locale: Locale): TripSummary {
  return {
    id: t.id,
    slug: t.slug,
    type: t.type,
    title: pick(t.title, locale),
    summary: pick(t.summary, locale),
    destination: destinationSummary(t.destination, locale),
    route: t.route?.map((r) => ({ name: pick(r.name, locale), days: r.days })),
    image: { url: t.image, alt: pick(t.title, locale) },
    duration_hours: t.duration_hours ?? null,
    days: t.days ?? null,
    nights: t.nights ?? null,
    rating: t.rating,
    reviews_count: t.reviews_count,
    price_from: t.pricing_mode === "per_unit" ? Math.min(...t.unit_options.map((u) => u.price)) : priceFrom(t),
    pricing_mode: t.pricing_mode,
    unit_label: t.unit_label ? pick(t.unit_label, locale) : null,
    booking_types: t.booking_types,
    free_cancellation: t.badges.includes("free_cancellation") || t.free_cancellation_hours <= 24,
    badges: t.badges,
    featured: t.featured,
    created_at: t.created_at,
  };
}

function tripDetail(t: MockTrip, locale: Locale): TripDetail {
  return {
    ...tripSummary(t, locale),
    description: pick(t.description, locale),
    gallery: t.gallery.map((url, i) => ({ url, alt: `${pick(t.title, locale)} ${i + 1}` })),
    highlights: t.highlights.map((h) => pick(h, locale)),
    included: t.included.map((h) => pick(h, locale)),
    excluded: t.excluded.map((h) => pick(h, locale)),
    what_to_bring: t.what_to_bring.map((h) => pick(h, locale)),
    timeline: t.timeline.map((d) => ({
      day_number: d.day_number,
      title: d.title ? pick(d.title, locale) : null,
      items: d.items.map((i) => ({
        time_from: i.time_from ?? null,
        time_to: i.time_to ?? null,
        title: pick(i.title, locale),
        description: pick(i.description, locale),
        place: i.place ?? null,
        transfer_included: Boolean(i.transfer_included),
      })),
    })),
    meeting_point: { ...t.meeting_point, text: pick(t.meeting_point.text, locale) },
    min_persons: t.min_persons,
    max_persons: t.max_persons,
    base_price: t.base_price,
    base_child_price: t.base_child_price,
    private_tiers: t.private_tiers,
    unit_options: t.unit_options.map((u) => ({ ...u, name: pick(u.name, locale) })),
    price_list: t.price_list.map((s) => season(s, locale)),
    addons: t.addons.map((a) => ({ ...a, name: pick(a.name, locale), description: pick(a.description, locale), image: a.image ?? null })),
    availability: {
      private: t.booking_types.includes("private") ? { lead_time_hours: PRIVATE_LEAD_HOURS, blackout_dates: ["2026-12-25"] } : null,
      group: t.booking_types.includes("group") ? { weekdays: t.group_weekdays, dates: [], blackout_dates: ["2026-12-25"] } : null,
    },
    child_age_bands: CHILD_BANDS,
    deposit_percent: t.deposit_percent,
    free_cancellation_hours: t.free_cancellation_hours,
    policies: { cancellation: pick(STANDARD_POLICIES.cancellation, locale), children: pick(STANDARD_POLICIES.children, locale) },
    faq: STANDARD_FAQ.map((f) => ({ question: pick(f.question, locale), answer: pick(f.answer, locale) })),
    guide_languages: t.guide_languages,
    vessel: t.vessel
      ? {
          name: t.vessel.name,
          itinerary_from: t.vessel.itinerary_from,
          itinerary_to: t.vessel.itinerary_to,
          facilities: t.vessel.facilities.map((f) => pick(f, locale)),
          meal_type: pick(t.vessel.meal_type, locale),
          cabins: t.vessel.cabins.map((c) => ({ type: pick(c.type, locale), stars: c.stars, description: pick(c.description, locale) })),
        }
      : null,
    stages: t.stages?.map((s) => ({
      destination: pick(s.destination, locale),
      days: s.days,
      nights: s.nights,
      description: pick(s.description, locale),
      accommodation: s.accommodation ? pick(s.accommodation, locale) : null,
    })) ?? null,
    transfers_included: t.transfers_included,
    seo: null,
  };
}

function building(b: (typeof MOCK_BUILDINGS)[number], locale: Locale): Building {
  return {
    id: b.id,
    slug: b.slug,
    name: b.name,
    type: pick(b.type, locale),
    stars: b.stars,
    location: pick(b.location, locale),
    address: b.address,
    lat: b.lat,
    lng: b.lng,
    description: pick(b.description, locale),
    amenities: b.amenities.map((a) => pick(a, locale)),
    check_in_from: b.check_in_from,
    check_out_until: b.check_out_until,
    cancellation_policy: pick(b.cancellation_policy, locale),
    taxes_included: b.taxes_included,
    tax_per_night: b.tax_per_night,
    deposit_percent: b.deposit_percent,
    image: { url: b.image, alt: b.name },
  };
}

function roomSummary(r: MockRoom, locale: Locale, date?: string): RoomSummary {
  const b = MOCK_BUILDINGS.find((x) => x.id === r.building_id)!;
  const s = seasonForDate(r.price_list.map((p) => season(p, locale)), date ?? new Date());
  return {
    id: r.id,
    name: pick(r.name, locale),
    room_type: r.room_type,
    type_label: pick(r.type_label, locale),
    building: { slug: b.slug, name: b.name, stars: b.stars, location: pick(b.location, locale), taxes_included: b.taxes_included },
    image: { url: r.image, alt: pick(r.name, locale) },
    max_adults: r.max_adults,
    max_children: r.max_children,
    max_total: r.max_total,
    beds: r.beds,
    bed_label: pick(r.bed_label, locale),
    size_m2: r.size_m2,
    view: r.view ? pick(r.view, locale) : null,
    amenities: r.amenities.map((a) => pick(a, locale)),
    price_per_night: s?.price_per_night ?? r.price_per_night,
    tax_included: b.taxes_included,
    free_cancellation: r.free_cancellation,
    breakfast_included: r.breakfast_included,
    few_left: r.few_left,
    min_nights: r.min_nights,
    max_nights: r.max_nights,
  };
}

function roomDetail(r: MockRoom, locale: Locale): RoomDetail {
  const b = MOCK_BUILDINGS.find((x) => x.id === r.building_id)!;
  return {
    ...roomSummary(r, locale),
    price_per_night: r.price_per_night,
    description: pick(r.description, locale),
    gallery: r.gallery.map((url, i) => ({ url, alt: `${pick(r.name, locale)} ${i + 1}` })),
    price_list: r.price_list.map((s) => season(s, locale)),
    building_detail: building(b, locale),
    other_rooms: MOCK_ROOMS.filter((x) => x.building_id === r.building_id && x.id !== r.id).map((x) => roomSummary(x, locale)),
    child_policy: pick(STANDARD_POLICIES.children, locale),
    extra_bed_price: r.extra_bed_price,
    deposit_percent: b.deposit_percent,
  };
}

function destinations(locale: Locale): DestinationSummary[] {
  return MOCK_DESTINATIONS.map((d) => ({ id: d.id, slug: d.slug, name: pick(d.name, locale), image: d.image, trips_count: d.trips_count }));
}

export const mockApi = {
  meta(locale: Locale): Meta {
    return {
      currencies: MOCK_CURRENCIES,
      locales: ["en", "it"],
      destinations: destinations(locale),
      settings: {
        phone: "+20 12 3456 7890",
        whatsapp: "201234567890",
        email: "hello@sharmstation.example",
        default_deposit_percent: 30,
        google_rating: MOCK_GOOGLE.rating,
        google_reviews_total: MOCK_GOOGLE.total,
      },
    };
  },

  home(locale: Locale): HomeData {
    return {
      hero_stats: { rating: MOCK_GOOGLE.rating, reviews_total: MOCK_GOOGLE.total, deposit_percent: 30 },
      featured_trips: [
        "pyramids-giza-sphinx-egyptian-museum",
        "ras-mohammed-snorkelling",
        "desert-buggy-safari",
        "luxor-highlights-3-days",
        "felucca-sunset-sail-aswan",
        "giftun-island-snorkelling",
      ]
        .map((slug) => MOCK_TRIPS.find((t) => t.slug === slug)!)
        .map((t) => tripSummary(t, locale)),
      packages: MOCK_PACKAGES.map((t) => tripSummary(t, locale)),
      rooms: MOCK_ROOMS.map((r) => roomSummary(r, locale)),
      nile: MOCK_TRIPS.filter((t) => t.type === "nile").map((t) => tripSummary(t, locale)),
      destinations: destinations(locale),
      google_reviews: this.reviews(),
    };
  },

  destinations,

  destination(slug: string, locale: Locale): Destination | null {
    const d = MOCK_DESTINATIONS.find((x) => x.slug === slug);
    return d ? { id: d.id, slug: d.slug, name: pick(d.name, locale), image: d.image, trips_count: d.trips_count, description: pick(d.description, locale) } : null;
  },

  trips(filters: TripFilters, locale: Locale, source: "trips" | "packages" = "trips"): Paginated<TripSummary> {
    const types = filters.type ? (Array.isArray(filters.type) ? filters.type : [filters.type]) : null;
    let list = (source === "packages" ? MOCK_PACKAGES : MOCK_TRIPS).filter((t) => !types || types.includes(t.type));
    if (filters.destination) list = list.filter((t) => t.destination === filters.destination);
    if (filters.booking_type) list = list.filter((t) => t.booking_types.includes(filters.booking_type!));
    if (filters.pricing_mode) list = list.filter((t) => t.pricing_mode === filters.pricing_mode);
    let summaries = list.map((t) => tripSummary(t, locale));
    if (filters.free_cancellation) summaries = summaries.filter((t) => t.free_cancellation);
    if (filters.price_min != null) summaries = summaries.filter((t) => t.price_from >= filters.price_min!);
    if (filters.price_max != null) summaries = summaries.filter((t) => t.price_from <= filters.price_max!);
    switch (filters.sort) {
      case "price_asc":
        summaries.sort((a, b) => a.price_from - b.price_from);
        break;
      case "price_desc":
        summaries.sort((a, b) => b.price_from - a.price_from);
        break;
      case "rating":
        summaries.sort((a, b) => (b.rating ?? 0) - (a.rating ?? 0));
        break;
      case "newest":
        summaries.sort((a, b) => b.created_at.localeCompare(a.created_at));
        break;
      default:
        summaries.sort((a, b) => Number(b.featured) - Number(a.featured) || (b.reviews_count ?? 0) - (a.reviews_count ?? 0));
    }
    const perPage = filters.per_page ?? 9;
    const total = summaries.length;
    const lastPage = Math.max(1, Math.ceil(total / perPage));
    const page = Math.min(Math.max(1, filters.page ?? 1), lastPage);
    return {
      data: summaries.slice((page - 1) * perPage, page * perPage),
      meta: { current_page: page, last_page: lastPage, per_page: perPage, total },
    };
  },

  trip(slug: string, locale: Locale): TripDetail | null {
    const t = ALL_PRODUCTS.find((x) => x.slug === slug);
    return t ? tripDetail(t, locale) : null;
  },

  allProductSlugs(): { slug: string; type: MockTrip["type"] }[] {
    return ALL_PRODUCTS.map((t) => ({ slug: t.slug, type: t.type }));
  },

  quoteTrip(slug: string, req: TripQuoteRequest, locale: Locale) {
    const trip = this.trip(slug, locale);
    if (!trip) throw new Error("Not found");
    return quoteTrip(trip, req);
  },

  rooms(filters: RoomFilters, locale: Locale): Paginated<RoomSummary> {
    let list = MOCK_ROOMS.slice();
    if (filters.destination) list = list.filter((r) => MOCK_BUILDINGS.find((b) => b.id === r.building_id)?.destination === filters.destination);
    if (filters.building) list = list.filter((r) => MOCK_BUILDINGS.find((b) => b.id === r.building_id)?.slug === filters.building);
    const guests = (filters.adults ?? 0) + (filters.children ?? 0);
    if (guests > 0) list = list.filter((r) => r.max_total >= guests && r.max_adults >= (filters.adults ?? 1));
    if (filters.free_cancellation) list = list.filter((r) => r.free_cancellation);
    let summaries = list.map((r) => roomSummary(r, locale, filters.check_in));
    if (filters.sort === "price_asc") summaries.sort((a, b) => a.price_per_night - b.price_per_night);
    if (filters.sort === "price_desc") summaries.sort((a, b) => b.price_per_night - a.price_per_night);
    if (filters.sort === "rating") summaries = summaries.sort((a, b) => b.building.stars - a.building.stars);
    return { data: summaries, meta: { current_page: 1, last_page: 1, per_page: 12, total: summaries.length } };
  },

  room(id: number, locale: Locale): RoomDetail | null {
    const r = MOCK_ROOMS.find((x) => x.id === id);
    return r ? roomDetail(r, locale) : null;
  },

  roomIds(): number[] {
    return MOCK_ROOMS.map((r) => r.id);
  },

  building(slug: string, locale: Locale): (Building & { rooms: RoomSummary[] }) | null {
    const b = MOCK_BUILDINGS.find((x) => x.slug === slug);
    if (!b) return null;
    return { ...building(b, locale), rooms: MOCK_ROOMS.filter((r) => r.building_id === b.id).map((r) => roomSummary(r, locale)) };
  },

  quoteRoom(req: RoomQuoteRequest, locale: Locale) {
    const room = this.room(req.room_id, locale);
    if (!room) throw new Error("Not found");
    return quoteRoom(room, req);
  },

  /** Google reviews keep their original language, so the locale is not used. */
  reviews(): GoogleReviews {
    return {
      rating: MOCK_GOOGLE.rating,
      total: MOCK_GOOGLE.total,
      write_review_url: GOOGLE_REVIEW_URL,
      reviews_url: "https://www.google.com/maps/search/Sharm+Station+Viaggi",
      reviews: MOCK_REVIEWS,
    };
  },

  transferHubs() {
    return MOCK_TRANSFER_HUBS;
  },

  quoteTransfer(req: TransferQuoteRequest): TransferQuoteResult {
    const vehicle = MOCK_VEHICLES.find((v) => v.code === req.vehicle) ?? MOCK_VEHICLES[0];
    // Deterministic pseudo-distance from the destination text (the real API geocodes the address).
    const seed = [...req.destination.toLowerCase()].reduce((s, c) => s + c.charCodeAt(0), 0);
    const distance = 12 + (seed % 38);
    return {
      price: round2(vehicle.base + distance * vehicle.per_km),
      currency: "EUR",
      vehicle: vehicle.code,
      distance_km: distance,
      duration_minutes: Math.round(distance * 1.6 + 10),
    };
  },

  createBooking(req: CreateBookingRequest, locale: Locale): BookingDetail {
    const rate = MOCK_CURRENCIES.find((c) => c.code === req.currency)?.rate_to_eur ?? 1;
    let title = "";
    let image: string | null = null;
    let quote;
    let seasonName: string | null = null;
    if (req.service_type === "room" && req.room_id) {
      const room = this.room(req.room_id, locale);
      if (!room) throw new Error("Room not found");
      quote = quoteRoom(room, { room_id: req.room_id, check_in: req.check_in!, check_out: req.check_out!, adults: req.adults, children: req.children, child_ages: req.child_ages });
      title = `${room.name} · ${room.building.name}`;
      image = room.image.url;
    } else {
      const trip = this.trip(req.product_slug!, locale);
      if (!trip) throw new Error("Trip not found");
      quote = quoteTrip(trip, {
        booking_type: req.booking_type ?? "group",
        travel_date: req.travel_date!,
        adults: req.adults,
        children: req.children,
        child_ages: req.child_ages,
        units: req.units,
        addons: req.addons,
      });
      title = trip.title;
      image = trip.image.url;
    }
    seasonName = quote.season?.name ?? null;
    const reference = `SSV-${Date.now().toString(36).toUpperCase().slice(-6)}`;
    const currency: CurrencyCode = req.currency;
    return {
      reference,
      status: "awaiting_payment",
      totals: {
        total: quote.total,
        deposit: quote.deposit,
        balance: quote.balance,
        currency,
        total_converted: round2(quote.total * rate),
        deposit_converted: round2(quote.deposit * rate),
        exchange_rate: rate,
      },
      payment_options: [
        { code: "stripe", name: "Card (Stripe)", type: "online" },
        { code: "paypal", name: "PayPal", type: "online" },
        { code: "bank_transfer", name: "Bank transfer", type: "manual" },
        { code: "instapay", name: "InstaPay", type: "manual" },
      ],
      service_type: req.service_type,
      product_title: title,
      product_image: image,
      booking_type: req.booking_type ?? null,
      travel_date: req.travel_date ?? null,
      check_in: req.check_in ?? null,
      check_out: req.check_out ?? null,
      adults: req.adults,
      children: req.children,
      lead: req.lead,
      season_name: seasonName,
      lines: [...quote.lines, ...quote.addon_lines],
      created_at: new Date().toISOString(),
    };
  },
};
