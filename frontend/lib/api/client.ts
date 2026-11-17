/**
 * Isomorphic API client for the Laravel API v1 (NEXT_PUBLIC_API_URL).
 *
 * Every endpoint the backend implements is called for real and its payload is
 * mapped by ./adapters.ts. Mock data is used only in these explicit cases:
 *  - NEXT_PUBLIC_USE_MOCKS=true                       -> mocks everywhere (offline demo)
 *  - transfers: the mock catalogue while GET /transfers/hubs|vehicles return no data
 *  - `planned(...)` endpoints still being built (account, partner panel, contact): 404 -> "unavailable"
 *  - resilience: the API is unreachable, times out or answers 5xx -> mock answer + console warning.
 *    404 means "not found" (null), 401/403/422 are surfaced to the UI, never masked.
 */
import { API_URL, USE_MOCKS } from "../site";
import {
  mapAuth,
  mapBooking,
  mapDestinationDetail,
  mapPackageCard,
  mapPackageDetail,
  type RawPackageCard,
  type RawPackageDetail,
  mapUser,
  moneyEur,
  type RawDestinationDetail,
  type RawUser,
  mapBuildingDetail,
  mapDestination,
  mapHome,
  mapMeta,
  mapPaginated,
  mapQuote,
  mapReviews,
  mapRoomCard,
  mapRoomDetail,
  mapRoomQuote,
  mapTripCard,
  mapTripDetail,
  type RawBooking,
  type RawBuildingDetail,
  type RawQuote,
  type RawReviews,
  type RawRoomCard,
  type RawRoomDetail,
  type RawTripCard,
  type RawTripDetail,
} from "./adapters";
import { roomImage, tripImage } from "./images";
import { mockApi } from "./mock-api";
import type {
  SuggestGroup,
  SuggestScope,
  ApiValidationError,
  AuthResponse,
  AuthUser,
  ContactRequest,
  PartnerBooking,
  PartnerDashboard,
  PartnerPriceRow,
  PartnerProfile,
  PartnerStatement,
  PasswordUpdate,
  Nationalities,
  PartnerBookingDetail,
  PartnerBookingRequest,
  PartnerCatalogueFilters,
  PartnerCatalogueItem,
  PartnerQuote,
  PartnerQuoteRequest,
  NationalityGroup,
  CommissionSource,
  PaymentMethod,
  Planned,
  ProfileUpdate,
  ProofUpload,
  BookingDetail,
  BookingType,
  Building,
  CreateBookingRequest,
  CurrencyCode,
  Destination,
  DestinationSummary,
  GoogleReviews,
  HomeData,
  Locale,
  Meta,
  Paginated,
  PartnerApplication,
  QuoteResult,
  RoomDetail,
  RoomFilters,
  RoomQuoteRequest,
  RoomQuoteResult,
  RoomSummary,
  TransferCatalog,
  TransferHub,
  TransferQuoteRequest,
  TransferQuoteResult,
  TripDetail,
  TripFilters,
  TripQuoteRequest,
  TripSummary,
} from "./types";

export class ApiError extends Error {
  constructor(
    public status: number,
    message: string,
    public errors: Record<string, string[]> = {},
  ) {
    super(message);
    this.name = "ApiError";
  }
  get isValidation() {
    return this.status === 422;
  }
}

export interface RequestOptions {
  locale: Locale;
  currency?: CurrencyCode;
  token?: string | null;
  signal?: AbortSignal;
  /** ISO-2 nationality of the visitor (or of the partner's client): prices depend on it (CR-10). */
  nationality?: string | null;
}

type Query = Record<string, string | number | boolean | undefined | null | (string | number)[]>;

function buildUrl(path: string, query: Query, opts: RequestOptions) {
  const url = new URL(`${API_URL}${path}`);
  url.searchParams.set("locale", opts.locale);
  url.searchParams.set("currency", opts.currency ?? "EUR");
  if (opts.nationality) url.searchParams.set("nationality", opts.nationality);
  for (const [key, value] of Object.entries(query)) {
    if (value === undefined || value === null || value === "" || value === false) continue;
    if (Array.isArray(value)) value.forEach((v) => url.searchParams.append(`${key}[]`, String(v)));
    else url.searchParams.set(key, value === true ? "1" : String(value));
  }
  return url.toString();
}

type Method = "GET" | "POST" | "PUT" | "PATCH" | "DELETE";

async function request<T>(method: Method, path: string, opts: RequestOptions, query: Query = {}, body?: unknown): Promise<T> {
  const multipart = typeof FormData !== "undefined" && body instanceof FormData;
  const timeout = AbortSignal.timeout(method === "GET" ? 4000 : multipart ? 60000 : 10000);
  const signal = opts.signal ? AbortSignal.any([opts.signal, timeout]) : timeout;
  const res = await fetch(buildUrl(path, query, opts), {
    method,
    headers: {
      Accept: "application/json",
      "Accept-Language": opts.locale,
      ...(body && !multipart ? { "Content-Type": "application/json" } : {}),
      ...(opts.token ? { Authorization: `Bearer ${opts.token}` } : {}),
    },
    body: body ? (multipart ? (body as FormData) : JSON.stringify(body)) : undefined,
    signal,
  });
  if (!res.ok) {
    let payload: Partial<ApiValidationError> = {};
    try {
      payload = await res.json();
    } catch {
      /* non-JSON error page */
    }
    throw new ApiError(res.status, payload.message ?? res.statusText, payload.errors ?? {});
  }
  if (res.status === 204) return undefined as T;
  return (await res.json()) as T;
}

type Envelope<T> = { data: T };

function data<T>(envelope: Envelope<T>, check?: (value: T) => boolean): T {
  if (!envelope || typeof envelope !== "object" || !("data" in envelope)) throw new ApiError(502, "Unexpected API response");
  if (check && !check(envelope.data)) throw new ApiError(502, "Unexpected API response shape");
  return envelope.data;
}

// Client-side circuit breaker: after the API failed (down / 5xx), answer from
// mocks for a short while instead of hitting it on every keystroke.
let apiDownUntil = 0;
const API_RETRY_MS = 30_000;

function isPassThrough(error: unknown) {
  if (error instanceof ApiError && [401, 403, 404, 409, 419, 422, 429].includes(error.status)) return true;
  return error instanceof DOMException && error.name === "AbortError";
}

/**
 * Real API call; mock only when the API is unavailable (network error, timeout, 5xx, bad shape).
 * `notFound` is returned for 404 so detail pages render the not-found UI.
 */
async function live<T>(call: () => Promise<T>, fallback: () => T, notFound?: () => T): Promise<T> {
  if (USE_MOCKS) return fallback();
  if (typeof window !== "undefined" && Date.now() < apiDownUntil) return fallback();
  try {
    return await call();
  } catch (error) {
    if (notFound && error instanceof ApiError && error.status === 404) return notFound();
    if (isPassThrough(error)) throw error;
    if (typeof window !== "undefined") apiDownUntil = Date.now() + API_RETRY_MS;
    console.warn(`[api] API unavailable, using mock data: ${(error as Error).message}`);
    return fallback();
  }
}

/**
 * Endpoints the backend is building right now (contract agreed, not deployed yet).
 * 404/405/501 -> `{ status: "unavailable" }` so the UI shows a "coming soon" state;
 * network errors and 5xx are treated the same (never crash the page);
 * 401/403/422 are surfaced so the caller can sign out or show field errors.
 */
async function planned<T>(call: () => Promise<T>): Promise<Planned<T>> {
  if (USE_MOCKS) return { status: "unavailable" };
  try {
    return { status: "ok", data: await call() };
  } catch (error) {
    if (error instanceof ApiError && [401, 403, 409, 413, 419, 422, 429].includes(error.status)) throw error;
    if (error instanceof DOMException && error.name === "AbortError") throw error;
    return { status: "unavailable" };
  }
}

const isArray = (v: unknown) => Array.isArray(v);
type Paged<R> = { data: R[]; meta: { current_page: number; last_page: number; per_page: number; total: number } };
const MAX_PER_PAGE = 48;

/** UI filters -> GET /trips query (backend sorts: recommended, price_asc, price_desc, newest, duration). */
function tripQuery(f: TripFilters): Query {
  return {
    type: f.type ? (Array.isArray(f.type) ? f.type : [f.type]) : undefined,
    destination: f.destination,
    booking_type: f.booking_type,
    pricing_mode: f.pricing_mode,
    price_min: f.price_min,
    price_max: f.price_max,
    free_cancellation: f.free_cancellation,
    sort: f.sort === "rating" ? "recommended" : f.sort,
    page: f.page,
    per_page: f.per_page ? Math.min(f.per_page, MAX_PER_PAGE) : undefined,
  };
}

/** UI filters -> GET /rooms query (the API filters by total guests; dates are not filtered server-side yet). */
function roomQuery(f: RoomFilters): Query {
  const guests = (f.adults ?? 0) + (f.children ?? 0);
  return {
    destination: f.destination,
    building: f.building,
    guests: guests > 0 ? guests : undefined,
    sort: f.sort === "rating" ? "recommended" : f.sort,
    page: f.page,
    per_page: MAX_PER_PAGE,
  };
}

/** UI booking payload -> StoreBookingRequest. */
function bookingBody(b: CreateBookingRequest) {
  const notes = [b.notes, b.pickup_hotel ? `Pick-up hotel: ${b.pickup_hotel}` : null, b.lead.country ? `Country: ${b.lead.country}` : null].filter(Boolean).join("\n");
  const common = {
    lead: { name: b.lead.name, email: b.lead.email, phone: b.lead.phone },
    travelers: b.travellers.map((t) => ({ first_name: t.first_name, last_name: t.last_name, type: t.type, age: t.age ?? null })),
    adults: b.adults,
    children: b.children,
    child_ages: b.child_ages,
    notes: notes || undefined,
    nationality_code: b.nationality_code,
  };
  if (b.service_type === "room") return { ...common, room_id: b.room_id, check_in: b.check_in, check_out: b.check_out };
  if (b.product_type === "package") return { ...common, package_slug: b.product_slug, booking_type: b.booking_type, travel_date: b.travel_date, addons: b.addons };
  return { ...common, trip_slug: b.product_slug, booking_type: b.booking_type, travel_date: b.travel_date, units: b.units, addons: b.addons };
}

export const api = {
  meta: (o: RequestOptions) =>
    live<Meta>(async () => mapMeta(data(await request<Envelope<Parameters<typeof mapMeta>[0]>>("GET", "/meta", o), (m) => isArray(m.currencies))), () => mockApi.meta(o.locale)),

  home: (o: RequestOptions) =>
    live<HomeData>(
      async () => mapHome(data(await request<Envelope<Parameters<typeof mapHome>[0]>>("GET", "/home", o), (h) => isArray(h.featured_trips)), o.locale),
      () => mockApi.home(o.locale),
    ),

  destinations: (o: RequestOptions) =>
    live<DestinationSummary[]>(
      async () => data(await request<Envelope<Parameters<typeof mapDestination>[0][]>>("GET", "/destinations", o), isArray).map(mapDestination),
      () => mockApi.destinations(o.locale),
    ),

  destination: (slug: string, o: RequestOptions) =>
    live<Destination | null>(
      async () => mapDestination(data(await request<Envelope<Parameters<typeof mapDestination>[0]>>("GET", `/destinations/${encodeURIComponent(slug)}`, o))),
      () => mockApi.destination(slug, o.locale),
      () => null,
    ),

  trips: (filters: TripFilters, o: RequestOptions) =>
    live<Paginated<TripSummary>>(
      async () => {
        const res = await request<Paged<RawTripCard>>("GET", "/trips", o, tripQuery(filters));
        if (!isArray(res.data) || !res.meta) throw new ApiError(502, "Unexpected API response");
        return mapPaginated(res, mapTripCard);
      },
      () => mockApi.trips(filters, o.locale),
    ),

  /** GET /packages (PackageCardResource, paginated). */
  packages: (filters: TripFilters, o: RequestOptions) =>
    live<Paginated<TripSummary>>(
      async () => {
        const res = await request<Paged<RawPackageCard>>("GET", "/packages", o, {
          destination: filters.destination,
          booking_type: filters.booking_type,
          price_min: filters.price_min,
          price_max: filters.price_max,
          free_cancellation: filters.free_cancellation,
          sort: filters.sort === "rating" ? "recommended" : filters.sort,
          page: filters.page,
          per_page: filters.per_page ? Math.min(filters.per_page, MAX_PER_PAGE) : undefined,
        });
        if (!isArray(res.data) || !res.meta) throw new ApiError(502, "Unexpected API response");
        return mapPaginated(res, mapPackageCard);
      },
      () => mockApi.trips(filters, o.locale, "packages"),
    ),

  trip: (slug: string, o: RequestOptions) =>
    live<TripDetail | null>(
      async () => mapTripDetail(data(await request<Envelope<RawTripDetail>>("GET", `/trips/${encodeURIComponent(slug)}`, o), (t) => isArray(t.price_list)), o.locale),
      () => mockApi.trip(slug, o.locale),
      () => null,
    ),

  /** GET /packages/{slug} (PackageResource). */
  packageDetail: (slug: string, o: RequestOptions) =>
    live<TripDetail | null>(
      async () => mapPackageDetail(data(await request<Envelope<RawPackageDetail>>("GET", `/packages/${encodeURIComponent(slug)}`, o), (p) => isArray(p.stages)), o.locale),
      () => mockApi.trip(slug, o.locale),
      () => null,
    ),

  /** POST /trips/{slug}/quote or POST /packages/{slug}/quote (packages take no units). */
  quoteTrip: (trip: TripDetail, body: TripQuoteRequest, o: RequestOptions) => {
    const isPackage = trip.type === "package";
    const path = `/${isPackage ? "packages" : "trips"}/${encodeURIComponent(trip.slug)}/quote`;
    const payload = isPackage ? { ...body, units: undefined } : body;
    return live<QuoteResult>(
      async () => mapQuote(data(await request<Envelope<RawQuote>>("POST", path, o, {}, payload), (q) => typeof q.total === "number")),
      () => mockApi.quoteTrip(trip.slug, body, o.locale),
    );
  },

  rooms: (filters: RoomFilters, o: RequestOptions) =>
    live<Paginated<RoomSummary>>(
      async () => {
        const res = await request<Paged<RawRoomCard>>("GET", "/rooms", o, roomQuery(filters));
        if (!isArray(res.data)) throw new ApiError(502, "Unexpected API response");
        return mapPaginated(res, (r) => mapRoomCard(r, o.locale));
      },
      () => mockApi.rooms(filters, o.locale),
    ),

  room: (id: number, o: RequestOptions) =>
    live<RoomDetail | null>(
      async () => mapRoomDetail(data(await request<Envelope<RawRoomDetail>>("GET", `/rooms/${id}`, o), (r) => isArray(r.price_list)), o.locale),
      () => mockApi.room(id, o.locale),
      () => null,
    ),

  building: (slug: string, o: RequestOptions) =>
    live<(Building & { rooms: RoomSummary[] }) | null>(
      async () => mapBuildingDetail(data(await request<Envelope<RawBuildingDetail>>("GET", `/buildings/${encodeURIComponent(slug)}`, o)), o.locale),
      () => mockApi.building(slug, o.locale),
      () => null,
    ),

  quoteRoom: (body: RoomQuoteRequest, o: RequestOptions) =>
    live<RoomQuoteResult>(
      async () => mapRoomQuote(data(await request<Envelope<RawQuote>>("POST", "/rooms/quote", o, {}, body), (q) => typeof q.total === "number")),
      () => mockApi.quoteRoom(body, o.locale),
    ),

  googleReviews: (o: RequestOptions) =>
    live<GoogleReviews>(async () => mapReviews(data(await request<Envelope<RawReviews>>("GET", "/reviews/google", o), (r) => isArray(r.reviews))), () => mockApi.reviews()),

  /** POST /bookings -> 201 BookingResource incl. `access_token` for GET /bookings/{reference}?token=. */
  createBooking: (body: CreateBookingRequest, o: RequestOptions) =>
    live<BookingDetail>(
      async () => mapBooking(data(await request<Envelope<RawBooking>>("POST", "/bookings", o, {}, bookingBody(body)), (b) => typeof b.reference === "string")),
      () => mockApi.createBooking(body, o.locale),
    ),

  /** GET /bookings/{reference}?token=…; null when not visible (the page then uses the local copy). */
  booking: async (reference: string, o: RequestOptions & { accessToken?: string | null }): Promise<BookingDetail | null> => {
    if (USE_MOCKS) return null;
    try {
      return mapBooking(data(await request<Envelope<RawBooking>>("GET", `/bookings/${encodeURIComponent(reference)}`, o, { token: o.accessToken ?? undefined })));
    } catch {
      return null;
    }
  },

  login: (body: { email: string; password: string }, o: RequestOptions) =>
    live<AuthResponse>(
      async () => mapAuth(await request<Parameters<typeof mapAuth>[0]>("POST", "/auth/login", o, {}, body)),
      () => ({ token: "demo-token", user: { id: 1, name: body.email.split("@")[0], email: body.email, role: "customer" } }),
    ),

  register: (body: { name: string; email: string; password: string; password_confirmation: string }, o: RequestOptions) =>
    live<AuthResponse>(
      async () => mapAuth(await request<Parameters<typeof mapAuth>[0]>("POST", "/auth/register", o, {}, { ...body, locale: o.locale })),
      () => ({ token: "demo-token", user: { id: 1, name: body.name, email: body.email, role: "customer" } }),
    ),

  /** POST /partners/apply (creates the partner user; country is ISO-2). */
  applyPartner: (body: PartnerApplication, o: RequestOptions) =>
    live<{ status: string }>(
      async () => {
        const res = await request<{ data: { status: string }; token?: string }>("POST", "/partners/apply", o, {}, {
          company: body.company,
          name: body.contact_name,
          email: body.email,
          password: body.password,
          password_confirmation: body.password_confirmation,
          phone: body.phone,
          country: body.country,
          website: body.website,
          tax_number: body.tax_number,
          message: [`Business type: ${body.business_type}`, body.message].filter(Boolean).join("\n"),
        });
        return { status: res.data.status };
      },
      () => ({ status: "pending" }),
    ),

  /** GET /search/suggest: grouped type-ahead suggestions; [] when the API is unavailable. */
  suggest: async (scope: SuggestScope, q: string, o: RequestOptions, hubId?: number): Promise<SuggestGroup[]> => {
    if (USE_MOCKS) return [];
    try {
      const res = await request<Envelope<SuggestGroup[]>>("GET", "/search/suggest", o, { scope, q, hub_id: hubId, limit: 8 });
      return isArray(res.data) ? res.data : [];
    } catch (error) {
      if (o.signal?.aborted) throw error;
      return [];
    }
  },

  /** Mock hubs (used when GET /transfers/hubs is empty or unreachable). */
  transferHubs: (): TransferHub[] => mockApi.transferHubs(),

  /**
   * GET /transfers/hubs + GET /transfers/vehicles. Falls back to the mock catalogue
   * (live: false) while the API has no hubs or vehicles configured.
   */
  transferCatalog: async (o: RequestOptions): Promise<TransferCatalog> => {
    const mock: TransferCatalog = {
      hubs: mockApi.transferHubs(),
      vehicles: [
        { code: "sedan", name: "" },
        { code: "minivan", name: "" },
        { code: "van", name: "" },
      ],
      live: false,
    };
    if (USE_MOCKS) return mock;
    try {
      const [hubs, vehicles] = await Promise.all([
        request<Envelope<RawTransferHub[]>>("GET", "/transfers/hubs", o),
        request<Envelope<{ id: number; name: string; capacity: number | null }[]>>("GET", "/transfers/vehicles", o),
      ]);
      if (!isArray(hubs.data) || !isArray(vehicles.data) || !hubs.data.length || !vehicles.data.length) return mock;
      return {
        hubs: hubs.data.map((h) => ({ code: String(h.id), id: h.id, name: h.name, city: h.destination?.name, type: h.type, places: h.places ?? [], vehicle_type_ids: h.vehicle_type_ids })),
        vehicles: vehicles.data.map((v) => ({ code: String(v.id), name: v.name, capacity: v.capacity })),
        live: true,
      };
    } catch {
      return mock;
    }
  },

  /**
   * POST /transfers/quote {hub_id, vehicle_type_id, destination{name,lat,lng}, pickup_at, passengers}.
   * The API needs coordinates, so live quotes work for the hub's known places; other
   * destinations throw ApiError(422) and the form offers a WhatsApp request instead.
   */
  quoteTransfer: async (body: TransferQuoteRequest, o: RequestOptions, catalog?: TransferCatalog): Promise<TransferQuoteResult> => {
    if (!catalog?.live) return mockApi.quoteTransfer(body);
    const hub = catalog.hubs.find((h) => h.code === body.hub);
    const place =
      hub?.places?.find((p) => p.name.trim().toLowerCase() === body.destination.trim().toLowerCase()) ??
      (body.point ? { name: body.destination.trim(), lat: body.point.lat, lng: body.point.lng } : undefined);
    if (!hub?.id || !place) throw new ApiError(422, "Unknown destination", { destination: ["unknown"] });
    const raw = data(
      await request<Envelope<RawQuote & { distance_km?: number; duration_minutes?: number }>>("POST", "/transfers/quote", o, {}, {
        hub_id: hub.id,
        vehicle_type_id: Number(body.vehicle),
        destination: place,
        pickup_at: `${body.date} 10:00`,
        passengers: body.passengers ?? 1,
      }),
      (q) => typeof q.total === "number",
    );
    return { price: raw.total, currency: "EUR", vehicle: body.vehicle, distance_km: Math.round(raw.distance_km ?? 0), duration_minutes: Math.round(raw.duration_minutes ?? 0) };
  },

  /** POST /newsletter {email, locale, source}. */
  newsletter: (email: string, o: RequestOptions) =>
    live<{ subscribed: boolean }>(
      async () => {
        const res = await request<Envelope<{ subscribed: boolean }>>("POST", "/newsletter", o, {}, { email, locale: o.locale, source: "footer" });
        return { subscribed: res.data?.subscribed ?? true };
      },
      () => ({ subscribed: true }),
    ),

  /* ---------------------------------------------------------------- account (Sanctum bearer token) */

  /** GET /auth/me -> UserResource (with `partner` when the user is a partner). */
  me: async (o: RequestOptions & { token: string }): Promise<AuthUser> => {
    if (USE_MOCKS || o.token === "demo-token") return { id: 1, name: "Demo traveller", email: "demo@example.com", role: "customer", partner: null };
    return mapUser(data(await request<Envelope<RawUser>>("GET", "/auth/me", o), (u) => typeof u.id === "number"));
  },

  /** POST /auth/logout (204). Errors are ignored: the local token is dropped anyway. */
  logout: async (o: RequestOptions & { token: string }) => {
    if (USE_MOCKS) return;
    try {
      await request<void>("POST", "/auth/logout", o);
    } catch {
      /* token already invalid or API down */
    }
  },

  /** GET /me/bookings?status=upcoming|past|cancelled -> paginated BookingResource. PENDING BACKEND. */
  getMyBookings: (o: RequestOptions & { token: string }) =>
    planned<BookingDetail[]>(async () => {
      const res = await request<Paged<RawBooking>>("GET", "/me/bookings", o, { per_page: 50 });
      if (!isArray(res.data)) throw new ApiError(502, "Unexpected API response");
      return res.data.map(mapBooking);
    }),

  /** PATCH /me/profile {name, phone, locale, currency, newsletter} -> UserResource. PENDING BACKEND. */
  updateProfile: (body: Partial<ProfileUpdate>, o: RequestOptions & { token: string }) =>
    planned<AuthUser>(async () => mapUser(data(await request<Envelope<RawUser>>("PATCH", "/me/profile", o, {}, body)))),

  /** PUT /me/password {current_password, password, password_confirmation} -> 204. PENDING BACKEND. */
  updatePassword: (body: PasswordUpdate, o: RequestOptions & { token: string }) => planned<void>(() => request<void>("PUT", "/me/password", o, {}, body)),

  /* ---------------------------------------------------------------- nationality (CR-10) */

  /** GET /nationalities: every country with its nationality group, plus the detected country. */
  nationalities: async (o: RequestOptions): Promise<Nationalities> => {
    const raw = await request<{ data: Nationalities["countries"]; detected?: string | null; detected_group?: NationalityGroup | null; default_group?: NationalityGroup | null; groups?: NationalityGroup[] }>("GET", "/nationalities", o);
    if (!isArray(raw.data)) throw new ApiError(502, "Unexpected API response");
    return {
      countries: raw.data,
      detected: raw.detected ?? null,
      detected_group: raw.detected_group ?? null,
      default_group: raw.default_group ?? null,
      groups: raw.groups ?? [],
    };
  },

  /* ---------------------------------------------------------------- partner panel (active partner token, else 403) */

  /** GET /partner/dashboard -> { kpis, commissions[], recent_bookings[] }. */
  partnerDashboard: async (o: RequestOptions & { token: string }): Promise<PartnerDashboard> => {
    const raw = data(
      await request<Envelope<{ kpis: Record<string, unknown>; commissions?: PartnerDashboard["commissions"]; recent_bookings?: RawPartnerBooking[] }>>("GET", "/partner/dashboard", o),
      (d) => Boolean(d?.kpis),
    );
    return {
      kpis: {
        bookings_month: Number(raw.kpis.bookings_month ?? 0),
        sales_month: moneyEur(raw.kpis.partner_sales_month ?? raw.kpis.sales_month),
        commission_earned_month: moneyEur(raw.kpis.commission_earned_month ?? raw.kpis.commission_saved_month),
        balance_due: moneyEur(raw.kpis.balance_due),
        pending_proofs: Number(raw.kpis.pending_proofs ?? 0),
      },
      commissions: raw.commissions ?? [],
      recent_bookings: (raw.recent_bookings ?? []).map(mapPartnerBooking),
    };
  },

  /** GET /partner/bookings?status=&service=&payment_status= -> paginated BookingResource. */
  partnerBookings: async (filters: { status?: string; service?: string; payment_status?: string }, o: RequestOptions & { token: string }): Promise<PartnerBooking[]> => {
    const res = await request<Paged<RawPartnerBooking>>("GET", "/partner/bookings", o, { ...filters, per_page: 100 });
    if (!isArray(res.data)) throw new ApiError(502, "Unexpected API response");
    return res.data.map(mapPartnerBooking);
  },

  /** GET /bookings/{reference} with the partner's bearer token (partner totals included for the booker). Null when not found. */
  partnerBooking: async (reference: string, o: RequestOptions & { token: string }): Promise<PartnerBookingDetail | null> => {
    try {
      return mapPartnerBookingDetail(data(await request<Envelope<RawBookingFull>>("GET", `/bookings/${encodeURIComponent(reference)}`, o)));
    } catch (error) {
      if (error instanceof ApiError && error.status === 404) return null;
      throw error;
    }
  },

  /** GET /partner/statements -> monthly statements (retail, commission, partner totals). */
  partnerStatements: async (o: RequestOptions & { token: string }): Promise<PartnerStatement[]> =>
    data(await request<Envelope<Record<string, unknown>[]>>("GET", "/partner/statements", o), isArray).map((s) => ({
      id: Number(s.id),
      period: String(s.period ?? ""),
      period_start: String(s.period_start ?? ""),
      period_end: String(s.period_end ?? ""),
      bookings_count: Number(s.bookings_count ?? 0),
      retail_total: moneyEur(s.retail_total),
      commission_amount: moneyEur(s.commission_amount),
      partner_total: moneyEur(s.partner_total),
      paid: moneyEur(s.paid),
      balance: moneyEur(s.balance),
      status: (s.status as PartnerStatement["status"]) ?? "open",
      pdf_url: (s.pdf_url as string | null) ?? null,
      excel_url: (s.excel_url as string | null) ?? null,
    })),

  /** GET /partner/price-list?nationality=&service= (403 when the admin has not enabled it). */
  partnerPriceList: async (filters: { nationality: string; service?: string }, o: RequestOptions & { token: string }): Promise<PartnerPriceRow[]> =>
    data(await request<Envelope<Record<string, unknown>[]>>("GET", "/partner/price-list", o, filters), isArray).map((r) => ({
      service: String(r.service ?? ""),
      service_type: String(r.service_type ?? ""),
      title: String(r.title ?? ""),
      slug: (r.slug as string | null) ?? null,
      row_type: r.row_type === "season" || r.row_type === "from" ? r.row_type : "base",
      season: (r.season as string | null) ?? null,
      starts_on: (r.starts_on as string | null) ?? null,
      ends_on: (r.ends_on as string | null) ?? null,
      pricing_mode: (r.pricing_mode as string | null) ?? null,
      retail_price: moneyEur(r.retail_price),
      partner_price: moneyEur(r.partner_price),
      commission: mapCommission(r.commission),
      nationality_group: (r.nationality_group as NationalityGroup | null) ?? null,
    })),

  /** GET /partner/profile -> company data + read-only commissions per type. */
  partnerProfile: async (o: RequestOptions & { token: string }): Promise<PartnerProfile> => data(await request<Envelope<PartnerProfile>>("GET", "/partner/profile", o)),

  /** PATCH /partner/profile (contact fields and notifications) -> PartnerProfile. */
  updatePartnerProfile: async (body: Partial<Omit<PartnerProfile, "status" | "commissions">>, o: RequestOptions & { token: string }): Promise<PartnerProfile> =>
    data(await request<Envelope<PartnerProfile>>("PATCH", "/partner/profile", o, {}, body)),

  /** GET /partner/catalogue: customer price for the client's nationality, partner price and commission per product. */
  partnerCatalogue: async (f: PartnerCatalogueFilters, o: RequestOptions & { token: string }): Promise<PartnerCatalogueItem[]> =>
    data(await request<Envelope<Record<string, unknown>[]>>("GET", "/partner/catalogue", o, { ...f }), isArray).map((r) => ({
      service: r.service as PartnerCatalogueItem["service"],
      service_type: String(r.service_type ?? ""),
      product_type: r.product_type as PartnerCatalogueItem["product_type"],
      id: Number(r.id),
      slug: (r.slug as string | null) ?? null,
      title: String(r.title ?? ""),
      subtitle: (r.subtitle as string | null) ?? null,
      cover_image:
        r.product_type === "transfer"
          ? null
          : r.product_type === "room"
            ? roomImage(r.cover_image as string | null, String(r.title ?? ""), Number(r.id))
            : tripImage(r.cover_image as string | null, String(r.slug ?? r.title ?? "")),
      price_unit: (r.price_unit as string | null) ?? null,
      price_basis: r.price_basis === "date" ? "date" : "from",
      season: (r.season as string | null) ?? null,
      customer_price: moneyEur(r.customer_price),
      partner_price: moneyEur(r.partner_price),
      commission: mapCommission(r.commission),
      nationality_group: (r.nationality_group as NationalityGroup | null) ?? null,
      hub_id: (r.hub_id as number | null) ?? null,
      vehicle_type_id: (r.vehicle_type_id as number | null) ?? null,
      location: (r.location as { lat: number; lng: number } | null) ?? null,
    })),

  /** POST /partner/quotes -> customer lines, commission and partner totals (422 carries the invalid quote's messages). */
  partnerQuote: async (body: PartnerQuoteRequest, o: RequestOptions & { token: string }): Promise<PartnerQuote> =>
    mapPartnerQuote(data(await request<Envelope<RawPartnerQuote>>("POST", "/partner/quotes", o, {}, body), (q) => typeof q.retail_total === "number")),

  /** POST /partner/bookings -> 201 BookingResource (partner is the booker; client nationality sets the price). */
  partnerCreateBooking: async (body: PartnerBookingRequest, o: RequestOptions & { token: string }): Promise<PartnerBookingDetail> =>
    mapPartnerBookingDetail(data(await request<Envelope<RawBookingFull>>("POST", "/partner/bookings", o, {}, body), (b) => typeof b.reference === "string")),

  /* ---------------------------------------------------------------- payments (PENDING BACKEND, SRS 10) */

  /** GET /bookings/{reference}/payment-methods?token= -> { data: PaymentMethod[], meta }. */
  paymentMethods: (reference: string, o: RequestOptions & { accessToken?: string | null }) =>
    planned<PaymentMethod[]>(async () =>
      data(await request<Envelope<(Omit<PaymentMethod, "account_details"> & { account_details: unknown })[]>>("GET", `/bookings/${encodeURIComponent(reference)}/payment-methods`, o, { token: o.accessToken ?? undefined }), isArray).map((m) => ({
        ...m,
        account_details: accountDetails(m.account_details),
        currencies: m.currencies ?? [],
        surcharge_percent: Number(m.surcharge_percent ?? 0),
      })),
    ),

  /** POST /bookings/{reference}/checkout?token= {payment_method_id} -> 201 { data: { checkout_url, session_reference, expires_at } } (also accepts { redirect_url }). */
  checkout: (reference: string, paymentMethodId: number, o: RequestOptions & { accessToken?: string | null }) =>
    planned<{ redirect_url: string }>(async () => {
      const res = await request<{ redirect_url?: string; data?: { redirect_url?: string; checkout_url?: string } }>(
        "POST",
        `/bookings/${encodeURIComponent(reference)}/checkout`,
        o,
        { token: o.accessToken ?? undefined },
        { payment_method_id: paymentMethodId },
      );
      const url = res.redirect_url ?? res.data?.checkout_url ?? res.data?.redirect_url;
      if (!url || !/^https?:\/\//.test(url)) throw new ApiError(502, "Missing checkout_url");
      return { redirect_url: url };
    }),

  /** POST /bookings/{reference}/proofs?token= multipart: files[], transaction_number, transfer_date, amount, sender_name, payment_method_id. */
  uploadProofs: (reference: string, body: ProofUpload & { payment_method_id: number }, o: RequestOptions & { accessToken?: string | null }) =>
    planned<{ status: string }>(async () => {
      const form = new FormData();
      body.files.forEach((f) => form.append("files[]", f, f.name));
      form.append("payment_method_id", String(body.payment_method_id));
      form.append("transaction_number", body.transaction_number);
      // The backend validates `transaction_date`; `transfer_date` is kept for the documented contract.
      form.append("transaction_date", body.transfer_date);
      form.append("transfer_date", body.transfer_date);
      form.append("amount", String(body.amount));
      form.append("sender_name", body.sender_name);
      const res = await request<{ data?: { status?: string }; meta?: { payment_status?: string } } | undefined>("POST", `/bookings/${encodeURIComponent(reference)}/proofs`, o, { token: o.accessToken ?? undefined }, form);
      return { status: res?.meta?.payment_status ?? res?.data?.status ?? "awaiting_receipt_review" };
    }),

  /* ---------------------------------------------------------------- content */

  /** POST /contact {name, email, phone, subject, booking_reference, message} -> 201/202. PENDING BACKEND. */
  contact: (body: ContactRequest, o: RequestOptions) => planned<void>(async () => void (await request<unknown>("POST", "/contact", o, {}, { ...body, locale: o.locale }))),

  /** GET /destinations/{slug} with its trips and buildings (null when unknown). */
  destinationDetail: (slug: string, o: RequestOptions) =>
    live<ReturnType<typeof mapDestinationDetail> | null>(
      async () => mapDestinationDetail(data(await request<Envelope<RawDestinationDetail>>("GET", `/destinations/${encodeURIComponent(slug)}`, o))),
      () => {
        const d = mockApi.destination(slug, o.locale);
        return d ? { ...d, trips: [], buildings: [] } : null;
      },
      () => null,
    ),
};

/** account_details may arrive as { label: value } or as [{ label, value }]. */
function accountDetails(raw: unknown): Record<string, string> {
  if (Array.isArray(raw)) {
    return Object.fromEntries(
      raw
        .filter((r): r is { label: unknown; value: unknown } => Boolean(r) && typeof r === "object" && "label" in r && "value" in r)
        .map((r) => [String(r.label), String(r.value)]),
    );
  }
  if (raw && typeof raw === "object") return Object.fromEntries(Object.entries(raw).map(([k, v]) => [k, String(v)]));
  return {};
}

interface RawTransferHub {
  id: number;
  name: string;
  type?: string;
  destination?: { name: string; slug: string } | null;
  places?: { name: string; lat: number; lng: number }[];
  vehicle_type_ids?: number[] | null;
}

interface RawPartnerBooking {
  reference: string;
  service_type?: string;
  product?: { title: string } | null;
  product_title?: string;
  start_date?: string | null;
  check_in?: string | null;
  travel_date?: string | null;
  lead?: { name: string } | null;
  lead_name?: string;
  nationality?: { code: string | null } | null;
  totals?: Record<string, unknown>;
  partner_total?: unknown;
  retail_total?: unknown;
  paid?: unknown;
  balance?: unknown;
  payment_summary?: { paid?: number; due?: number } | null;
  status: string;
  payment_status?: string;
  created_at: string;
}

/** Accepts both a BookingResource and a flat partner row. */
function mapPartnerBooking(r: RawPartnerBooking): PartnerBooking {
  const t = r.totals ?? {};
  const partnerTotal = moneyEur(r.partner_total ?? t.partner_total ?? t.total);
  const paid = moneyEur(r.paid ?? r.payment_summary?.paid ?? t.paid_amount ?? 0);
  return {
    reference: r.reference,
    service_type: r.service_type ?? "trip",
    product_title: r.product_title ?? r.product?.title ?? r.reference,
    travel_date: r.travel_date ?? r.start_date ?? r.check_in ?? null,
    lead_name: r.lead_name ?? r.lead?.name ?? "",
    nationality_code: r.nationality?.code ?? null,
    partner_total: partnerTotal,
    retail_total: moneyEur(r.retail_total ?? t.retail_total),
    paid,
    balance: r.balance != null ? moneyEur(r.balance) : r.payment_summary?.due != null ? moneyEur(r.payment_summary.due) : Math.max(0, partnerTotal - paid),
    status: r.status as PartnerBooking["status"],
    payment_status: (r.payment_status ?? "awaiting_payment") as PartnerBooking["payment_status"],
    created_at: r.created_at,
  };
}

function mapCommission(raw: unknown): { percent: number; source: CommissionSource } {
  const c = (raw ?? {}) as { percent?: unknown; source?: unknown };
  return { percent: Number(c.percent ?? 0), source: (c.source as CommissionSource) ?? "none" };
}

interface RawPartnerQuote {
  valid?: boolean;
  season_name?: string | null;
  nationality?: { code: string; group: NationalityGroup | null };
  lines?: { type: string; label: string; quantity: number; unit_price: number; total: number; partner_unit_price?: number; partner_total?: number; commission_percent?: number }[];
  retail_total: number;
  total: number;
  partner_total?: number;
  commission_amount?: number;
  commission_percent?: number;
  commission_source?: CommissionSource;
  deposit_percent: number;
  deposit_amount: number;
  balance_amount: number;
  nights?: number;
}

function mapPartnerQuote(q: RawPartnerQuote): PartnerQuote {
  return {
    valid: q.valid !== false,
    season_name: q.season_name ?? null,
    nationality: q.nationality ?? { code: "", group: null },
    lines: (q.lines ?? []).map((l) => ({
      type: l.type,
      label: l.label,
      quantity: l.quantity,
      unit_price: l.unit_price,
      total: l.total,
      partner_unit_price: l.partner_unit_price ?? l.unit_price,
      partner_total: l.partner_total ?? l.total,
      commission_percent: l.commission_percent ?? 0,
    })),
    retail_total: q.retail_total,
    partner_total: q.partner_total ?? q.total,
    commission_amount: q.commission_amount ?? 0,
    commission_percent: q.commission_percent ?? 0,
    commission_source: q.commission_source ?? "none",
    deposit_percent: q.deposit_percent,
    deposit_amount: q.deposit_amount,
    balance_amount: q.balance_amount,
    nights: q.nights,
  };
}

interface RawBookingFull {
  reference: string;
  access_token?: string;
  status: string;
  payment_status: string;
  service_type: string;
  booking_type?: BookingType | null;
  season_name?: string | null;
  nationality?: { code: string | null; group: NationalityGroup | null } | null;
  product?: { title: string } | null;
  start_date?: string | null;
  check_in?: string | null;
  check_out?: string | null;
  lead: { name: string; email: string; phone?: string | null };
  adults: number;
  children: number;
  travelers?: { first_name: string; last_name: string; type: string; age: number | null }[];
  lines?: { label?: string; type?: string; quantity: number; unit_price: number; total: number; partner_unit_price?: number; partner_total?: number }[];
  totals: Record<string, unknown>;
  payment_options?: PartnerBookingDetail["payment_options"];
  payment_summary?: { paid?: number; due?: number } | null;
  payments?: { type: string; status: string; amount: number; method: string | null; paid_at: string | null }[];
  transfer?: PartnerBookingDetail["transfer"];
  created_at: string;
}

function mapPartnerBookingDetail(raw: RawBookingFull): PartnerBookingDetail {
  const t = raw.totals ?? {};
  const total = moneyEur(t.total ?? t.retail_total);
  const partnerTotal = moneyEur(t.partner_total ?? total);
  return {
    reference: raw.reference,
    access_token: raw.access_token,
    status: raw.status as PartnerBookingDetail["status"],
    payment_status: raw.payment_status as PartnerBookingDetail["payment_status"],
    service_type: raw.service_type,
    booking_type: raw.booking_type ?? null,
    season_name: raw.season_name ?? null,
    nationality: raw.nationality ?? { code: null, group: null },
    product_title: raw.product?.title ?? raw.reference,
    travel_date: raw.start_date ?? null,
    check_in: raw.check_in ?? null,
    check_out: raw.check_out ?? null,
    lead: { name: raw.lead?.name ?? "", email: raw.lead?.email ?? "", phone: raw.lead?.phone ?? "" },
    adults: raw.adults ?? 0,
    children: raw.children ?? 0,
    travellers: raw.travelers ?? [],
    lines: (raw.lines ?? []).map((l) => ({ label: l.label ?? l.type ?? "", quantity: l.quantity, unit_price: l.unit_price, total: l.total, partner_unit_price: l.partner_unit_price, partner_total: l.partner_total })),
    totals: {
      retail_total: moneyEur(t.retail_total),
      total,
      partner_total: partnerTotal,
      commission_amount: moneyEur(t.commission_amount),
      commission_percent: t.commission_percent != null ? Number(t.commission_percent) : null,
      commission_source: (t.commission_source as CommissionSource | null) ?? null,
      deposit_percent: Number(t.deposit_percent ?? 0),
      deposit_amount: moneyEur(t.deposit_amount),
      balance_amount: moneyEur(t.balance_amount),
    },
    paid: Number(raw.payment_summary?.paid ?? 0),
    due: Number(raw.payment_summary?.due ?? partnerTotal),
    payments: raw.payments ?? [],
    payment_options: raw.payment_options ?? [],
    transfer: raw.transfer ?? null,
    created_at: raw.created_at,
  };
}
