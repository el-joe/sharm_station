/**
 * Types for the Laravel REST API v1 (docs/ARCHITECTURE.md §5).
 * All money values are EUR (the base currency). The UI converts to the
 * visitor's currency with the rates from GET /meta; when the API also sends a
 * converted amount for `?currency=` we still render from the EUR value so a
 * single code path handles both.
 */

export type Locale = "en" | "it";
export type CurrencyCode = "EUR" | "USD" | "GBP" | "EGP";
export type TripType = "single_day" | "multi_day" | "nile" | "package";
export type ProductKind = "trip" | "nile" | "package";
export type PricingMode = "per_person" | "per_unit";
export type BookingType = "private" | "group";
export type AddonPricingBasis = "per_person" | "per_booking" | "per_unit";
export type TripBadge = "best_seller" | "deposit_only" | "free_cancellation" | "transfers_included";
export type TripSort = "recommended" | "price_asc" | "price_desc" | "rating" | "newest";

/** Laravel API Resource envelope. */
export interface ApiEnvelope<T> {
  data: T;
  meta?: PaginationMeta & Record<string, unknown>;
  links?: Record<string, string | null>;
}

export interface PaginationMeta {
  current_page: number;
  last_page: number;
  per_page: number;
  total: number;
}

export interface Paginated<T> {
  data: T[];
  meta: PaginationMeta;
}

/** Laravel 422 error body. */
export interface ApiValidationError {
  message: string;
  errors: Record<string, string[]>;
}

export interface Currency {
  code: CurrencyCode;
  symbol: string;
  /** Units of this currency per 1 EUR (margin already applied). */
  rate_to_eur: number;
  is_enabled: boolean;
}

export interface MediaImage {
  url: string;
  alt: string;
}

export interface DestinationSummary {
  id: number;
  slug: string;
  name: string;
  image: string;
  trips_count: number;
}

export interface Destination extends DestinationSummary {
  description: string;
}

export interface Meta {
  currencies: Currency[];
  locales: Locale[];
  destinations: DestinationSummary[];
  settings: {
    phone: string;
    whatsapp: string;
    email: string;
    default_deposit_percent: number;
    google_rating: number;
    google_reviews_total: number;
  };
}

/** One price period (season). Dates are ISO `YYYY-MM-DD`; with `recurring_yearly` only month/day matter. */
export interface SeasonPrice {
  id: number;
  name: string;
  starts_on: string;
  ends_on: string;
  recurring_yearly: boolean;
  is_active: boolean;
  /** Headline price shown in the "Prices and seasons" table. */
  price: number;
  adult_price?: number | null;
  child_price?: number | null;
  /** `{ [unit_option_id]: price }` for per-unit products. */
  unit_prices?: Record<string, number> | null;
  /** `{ [private_tier_id]: price per person }`. */
  tier_prices?: Record<string, number> | null;
  /** Rooms: price per night. */
  price_per_night?: number | null;
}

export interface UnitOption {
  id: number;
  name: string;
  /** Seats per unit (riders). */
  seats: number;
  price: number;
  max_quantity: number;
  children_allowed: boolean;
}

export interface PrivateTier {
  id: number;
  min_people: number;
  max_people: number;
  price_per_person: number;
}

export interface Addon {
  id: number;
  name: string;
  description: string;
  image?: string | null;
  price: number;
  pricing_basis: AddonPricingBasis;
  required: boolean;
  max_quantity: number;
  booking_types: BookingType[];
}

export interface TimelineItem {
  time_from?: string | null;
  time_to?: string | null;
  title: string;
  description: string;
  place?: string | null;
  transfer_included?: boolean;
}

export interface TimelineDay {
  day_number: number;
  title?: string | null;
  items: TimelineItem[];
}

export interface AvailabilityRules {
  private: { lead_time_hours: number; blackout_dates: string[] } | null;
  group: { weekdays: number[]; dates: string[]; blackout_dates: string[] } | null;
}

export interface ChildAgeBand {
  from: number;
  to: number;
  /** "free" | "child" (child price) | "adult" (adult price) */
  pays: "free" | "child" | "adult";
}

export interface TripSummary {
  id: number;
  slug: string;
  type: TripType;
  title: string;
  summary: string;
  destination: { slug: string; name: string };
  /** Packages: list of destinations on the route. */
  route?: { name: string; days: number }[];
  image: MediaImage;
  duration_hours?: number | null;
  days?: number | null;
  nights?: number | null;
  /** Not provided by the API yet (null): cards hide the stars. */
  rating: number | null;
  reviews_count: number | null;
  /** Lowest active/future season price (EUR). */
  price_from: number;
  pricing_mode: PricingMode;
  /** For per-unit trips: what a unit is called ("buggy"). */
  unit_label?: string | null;
  booking_types: BookingType[];
  free_cancellation: boolean;
  badges: TripBadge[];
  featured: boolean;
  created_at: string;
}

export interface PackageStage {
  destination: string;
  days: number;
  nights: number;
  description: string;
  accommodation?: string | null;
}

export interface VesselInfo {
  name: string;
  itinerary_from: string;
  itinerary_to: string;
  facilities: string[];
  meal_type: string;
  /** Cabins are by type only. There is no deck selection (CR-8). */
  cabins: { type: string; stars: number; description: string }[];
}

export interface TripDetail extends TripSummary {
  description: string;
  gallery: MediaImage[];
  highlights: string[];
  included: string[];
  excluded: string[];
  what_to_bring: string[];
  timeline: TimelineDay[];
  meeting_point: { text: string; lat: number; lng: number; label: string };
  min_persons: number;
  max_persons: number;
  base_price: number;
  base_child_price: number;
  private_tiers: PrivateTier[];
  unit_options: UnitOption[];
  price_list: SeasonPrice[];
  addons: Addon[];
  availability: AvailabilityRules;
  child_age_bands: ChildAgeBand[];
  deposit_percent: number;
  free_cancellation_hours: number;
  policies: { cancellation: string; children: string; terms?: string };
  faq: { question: string; answer: string }[];
  guide_languages: string[];
  vessel?: VesselInfo | null;
  stages?: PackageStage[] | null;
  transfers_included: boolean;
  seo?: { title?: string; description?: string } | null;
}

export interface TripFilters {
  type?: TripType | TripType[];
  destination?: string;
  booking_type?: BookingType;
  pricing_mode?: PricingMode;
  price_min?: number;
  price_max?: number;
  free_cancellation?: boolean;
  sort?: TripSort;
  page?: number;
  per_page?: number;
}

export interface TripQuoteRequest {
  booking_type: BookingType;
  travel_date: string;
  adults: number;
  children: number;
  child_ages: number[];
  units: { unit_option_id: number; quantity: number }[];
  addons: { addon_id: number; quantity: number }[];
}

export type QuoteLineKind = "adult" | "child" | "child_free" | "unit" | "addon" | "night" | "tier" | "tax";

export interface QuoteLine {
  kind: QuoteLineKind;
  /** Server-localised label; the UI builds its own when absent. */
  label?: string | null;
  name?: string | null;
  quantity: number;
  unit_price: number;
  total: number;
  age?: number | null;
}

export interface QuoteResult {
  currency: "EUR";
  season: { name: string; starts_on?: string | null; ends_on?: string | null } | null;
  lines: QuoteLine[];
  addon_lines: QuoteLine[];
  subtotal: number;
  addons_total: number;
  total: number;
  deposit_percent: number;
  deposit: number;
  balance: number;
  /** Per-person price used for the "2 people: €85 each" hint. */
  per_person_price?: number | null;
  seats_total?: number | null;
  persons: number;
}

export interface Building {
  id: number;
  slug: string;
  name: string;
  type: string;
  stars: number;
  location: string;
  address: string;
  lat: number;
  lng: number;
  description: string;
  amenities: string[];
  check_in_from: string;
  check_out_until: string;
  cancellation_policy: string;
  taxes_included: boolean;
  tax_per_night: number;
  deposit_percent: number;
  image: MediaImage;
}

export interface RoomSummary {
  id: number;
  name: string;
  room_type: string;
  type_label: string;
  building: Pick<Building, "slug" | "name" | "stars" | "location" | "taxes_included">;
  image: MediaImage;
  max_adults: number;
  max_children: number;
  max_total: number;
  beds: number;
  bed_label: string;
  size_m2: number;
  view?: string | null;
  amenities: string[];
  price_per_night: number;
  /** CR-2: true when the building has taxes_included. */
  tax_included: boolean;
  /** EUR per night charged on top when tax_included is false. */
  tax_per_night?: number | null;
  free_cancellation: boolean;
  breakfast_included: boolean;
  few_left: boolean;
  min_nights: number;
  max_nights: number;
}

export interface RoomDetail extends RoomSummary {
  description: string;
  gallery: MediaImage[];
  price_list: SeasonPrice[];
  building_detail: Building;
  other_rooms: RoomSummary[];
  child_policy: string;
  extra_bed_price: number;
  deposit_percent: number;
}

export interface RoomFilters {
  destination?: string;
  building?: string;
  check_in?: string;
  check_out?: string;
  adults?: number;
  children?: number;
  free_cancellation?: boolean;
  sort?: "recommended" | "price_asc" | "price_desc" | "rating";
  page?: number;
}

export interface RoomQuoteRequest {
  room_id: number;
  check_in: string;
  check_out: string;
  adults: number;
  children: number;
  child_ages: number[];
}

export interface RoomQuoteResult extends QuoteResult {
  nights: number;
  tax_included: boolean;
}

export interface GoogleReview {
  id: string;
  author_name: string;
  author_photo_url?: string | null;
  author_country?: string | null;
  rating: number;
  text: string;
  language: string;
  relative_time: string;
  published_at: string;
}

export interface GoogleReviews {
  rating: number;
  total: number;
  write_review_url: string;
  reviews_url: string;
  reviews: GoogleReview[];
}

export interface HomeData {
  hero_stats: { rating: number; reviews_total: number; deposit_percent: number };
  featured_trips: TripSummary[];
  packages: TripSummary[];
  rooms: RoomSummary[];
  nile: TripSummary[];
  destinations: DestinationSummary[];
  google_reviews: GoogleReviews;
}

export interface Traveller {
  first_name: string;
  last_name: string;
  type: "adult" | "child";
  age?: number | null;
}

export interface CreateBookingRequest {
  service_type: "trip" | "room";
  product_slug?: string;
  room_id?: number;
  booking_type?: BookingType;
  travel_date?: string;
  check_in?: string;
  check_out?: string;
  adults: number;
  children: number;
  child_ages: number[];
  units: { unit_option_id: number; quantity: number }[];
  addons: { addon_id: number; quantity: number }[];
  lead: { name: string; email: string; phone: string; country?: string };
  travellers: Traveller[];
  notes?: string;
  pickup_hotel?: string;
  locale: Locale;
  currency: CurrencyCode;
  /** Set for packages: the booking is created with `package_slug`. */
  product_type?: TripType;
  /** ISO-2 nationality of the booker (required by POST /bookings, CR-10). */
  nationality_code: string;
}

export interface BookingTotals {
  total: number;
  deposit: number;
  balance: number;
  currency: CurrencyCode;
  /** Totals converted to `currency` with the rate frozen at booking time. */
  total_converted: number;
  deposit_converted: number;
  exchange_rate: number;
}

/** Booking lifecycle (SRS 12.1, backend App\Enums\BookingStatus). */
export type BookingStatus = "awaiting_payment" | "awaiting_receipt_review" | "confirmed" | "fully_paid" | "completed" | "cancelled" | "refunded";
/** Backend App\Enums\PaymentStatus. */
export type PaymentStatus = "awaiting_payment" | "awaiting_receipt_review" | "proof_rejected" | "deposit_paid" | "fully_paid" | "failed" | "refunded";

export interface CreateBookingResponse {
  reference: string;
  status: BookingStatus;
  payment_status?: PaymentStatus;
  totals: BookingTotals;
  payment_options: { code: string; name: string; type: "online" | "manual" }[];
  access_token?: string;
}

export interface BookingDetail extends CreateBookingResponse {
  service_type: "trip" | "room";
  product_title: string;
  product_image?: string | null;
  booking_type?: BookingType | null;
  travel_date?: string | null;
  check_in?: string | null;
  check_out?: string | null;
  adults: number;
  children: number;
  lead: { name: string; email: string; phone: string };
  season_name?: string | null;
  lines: QuoteLine[];
  travellers?: Traveller[];
  /** ISO date-time the deposit is due by (planned field; derived from created_at + 48 h when absent). */
  payment_due_at?: string | null;
  created_at: string;
}

export type UserRole = "customer" | "partner" | "super_admin" | "agent" | "editor" | "admin";
export type PartnerStatus = "pending" | "active" | "suspended";

export interface AuthUser {
  id: number;
  name: string;
  email: string;
  role: UserRole;
  phone?: string | null;
  locale?: Locale | null;
  currency?: CurrencyCode | null;
  /** ISO-2 nationality saved on the account (CR-10). */
  nationality_code?: string | null;
  partner?: { id: number; company: string; status: PartnerStatus; show_retail_price: boolean } | null;
}

export interface AuthResponse {
  token: string;
  user: AuthUser;
}

export interface PartnerApplication {
  company: string;
  contact_name: string;
  email: string;
  phone: string;
  country: string;
  website?: string;
  tax_number?: string;
  business_type: "agency" | "hotel" | "tour_operator" | "other";
  message?: string;
  /** The API creates the partner user account at the same time. */
  password: string;
  password_confirmation: string;
}

export interface TransferHub {
  /** Mock hubs: a short code; API hubs: the hub id as a string. */
  code: string;
  name: string;
  /** City / destination the hub serves (e.g. "Sharm El Sheikh"), shown under the name. */
  city?: string;
  /** Hub kind from the API: airport, port, city... */
  type?: string;
  id?: number;
  /** API only: known destinations (fixed routes) with coordinates. */
  places?: { name: string; lat: number; lng: number }[];
  vehicle_type_ids?: number[] | null;
}

export interface TransferVehicle {
  /** "sedan" | "minivan" | "van" for mocks; the vehicle type id (string) for the API. */
  code: string;
  name: string;
  capacity?: number | null;
}

export interface TransferCatalog {
  hubs: TransferHub[];
  vehicles: TransferVehicle[];
  /** true when hubs/vehicles come from GET /transfers/hubs|vehicles. */
  live: boolean;
}

export interface TransferQuoteRequest {
  hub: string;
  destination: string;
  vehicle: string;
  date: string;
  passengers?: number;
  /** Coordinates of a picked suggestion (fixed-price place or hotel), so it can be priced. */
  point?: { lat: number; lng: number } | null;
}

/** GET /search/suggest scopes (one per search field). */
export type SuggestScope = "trips" | "nile" | "packages" | "stays" | "transfer_from" | "transfer_to";

export interface SuggestItem {
  type: "destination" | "hotel" | "trip" | "package" | "hub" | "place";
  value: string;
  label: string;
  /** "Naama Bay, Sharm El Sheikh, Egypt" */
  location: string;
  meta: { trips_count?: number; stars?: number; lat?: number | null; lng?: number | null; fixed_price?: boolean; trip_type?: string; hub_type?: string };
}

export interface SuggestGroup {
  key: "destinations" | "hotels" | "trips" | "nile_trips" | "packages" | "hubs" | "places";
  items: SuggestItem[];
}

export interface TransferQuoteResult {
  price: number;
  currency: "EUR";
  vehicle: string;
  distance_km: number;
  duration_minutes: number;
}

/* ------------------------------------------------------------------ planned endpoints
 * Shapes below are what the frontend expects from endpoints the backend is still
 * building (see docs/BACKLOG.md). Each call resolves to `Planned<T>` so the UI can
 * show a "coming soon / no data" state when the endpoint answers 404/405.
 */

export type Planned<T> = { status: "ok"; data: T } | { status: "unavailable" };

/** GET /me/bookings item (PENDING BACKEND). Same shape as BookingDetail, list subset. */
export type MyBooking = BookingDetail;

export interface ProfileUpdate {
  name: string;
  phone?: string;
  locale: Locale;
  currency: CurrencyCode;
  newsletter?: boolean;
  nationality_code?: string | null;
}

export interface PasswordUpdate {
  current_password: string;
  password: string;
  password_confirmation: string;
}

/* ------------------------------------------------------------------ nationality (CR-10) */

export interface NationalityGroup {
  id: number;
  code: string;
  name: string;
  is_default: boolean;
}

export interface Country {
  code: string;
  name: string;
  group: NationalityGroup;
}

/** GET /nationalities */
export interface Nationalities {
  countries: Country[];
  /** ISO-2 detected from the connection (CF-IPCountry / X-Country-Code), when known. */
  detected: string | null;
  detected_group: NationalityGroup | null;
  default_group: NationalityGroup | null;
  groups: NationalityGroup[];
}

/* ------------------------------------------------------------------ partner panel (CR-11..CR-15) */

export type CommissionSource = "partner_override" | "global_setting" | "none";
export type CommissionCategory = "single_day_trips" | "multi_day_trips" | "nile_trips" | "packages" | "transfers";
export type CatalogueService = CommissionCategory | "accommodation";

export interface PartnerCommission {
  category: CommissionCategory;
  label: string;
  percent: number;
  source: CommissionSource;
  standard_percent: number;
  override_percent: number | null;
}

export interface PartnerKpis {
  bookings_month: number;
  sales_month: number;
  commission_earned_month: number;
  balance_due: number;
  pending_proofs: number;
}

export interface PartnerBooking {
  reference: string;
  service_type: string;
  product_title: string;
  travel_date: string | null;
  lead_name: string;
  nationality_code: string | null;
  partner_total: number;
  retail_total: number;
  paid: number;
  balance: number;
  status: BookingStatus;
  payment_status: PaymentStatus;
  created_at: string;
}

export interface PartnerDashboard {
  kpis: PartnerKpis;
  commissions: PartnerCommission[];
  recent_bookings: PartnerBooking[];
}

export interface PartnerStatement {
  id: number;
  period: string;
  period_start: string;
  period_end: string;
  bookings_count: number;
  retail_total: number;
  commission_amount: number;
  partner_total: number;
  paid: number;
  balance: number;
  status: "open" | "paid" | "overdue";
  pdf_url?: string | null;
  excel_url?: string | null;
}

export interface PartnerPriceRow {
  service: CatalogueService | string;
  service_type: string;
  title: string;
  slug: string | null;
  /** "base" (outside every season), "season" (one season of the client's group) or "from" (computed packages). */
  row_type: "base" | "season" | "from";
  season: string | null;
  starts_on: string | null;
  ends_on: string | null;
  pricing_mode: string | null;
  retail_price: number;
  partner_price: number;
  commission: { percent: number; source: CommissionSource };
  nationality_group: NationalityGroup | null;
}

export interface PartnerProfile {
  company: string;
  tax_number: string | null;
  contact_name: string;
  email: string;
  phone: string | null;
  country: string | null;
  website: string | null;
  status: PartnerStatus;
  notify_bookings: boolean;
  notify_statements: boolean;
  price_list_enabled?: boolean;
  show_retail_price?: boolean;
  commissions?: PartnerCommission[];
}

export type PartnerProductType = "trip" | "package" | "room" | "transfer";

/** GET /partner/catalogue item. */
export interface PartnerCatalogueItem {
  service: CatalogueService;
  service_type: string;
  product_type: PartnerProductType;
  id: number;
  slug: string | null;
  title: string;
  subtitle: string | null;
  cover_image: string | null;
  price_unit: string | null;
  /** "from": lowest price of the active/upcoming seasons (no date chosen); "date": the chosen date's price. */
  price_basis: "from" | "date";
  /** Season of the chosen date (null: base price, or no date chosen). */
  season: string | null;
  customer_price: number;
  partner_price: number;
  commission: { percent: number; source: CommissionSource };
  nationality_group: NationalityGroup | null;
  hub_id: number | null;
  vehicle_type_id: number | null;
  location: { lat: number; lng: number } | null;
}

export interface PartnerCatalogueFilters {
  service: CatalogueService;
  nationality: string;
  destination?: string;
  q?: string;
  date?: string;
}

export interface PartnerTransferFields {
  hub_id: number;
  vehicle_type_id: number;
  destination: { name: string; lat: number; lng: number };
  pickup_at: string;
  passengers: number;
  round_trip?: boolean;
  return_at?: string;
  flight_number?: string;
}

/** POST /partner/quotes */
export interface PartnerQuoteRequest {
  product_type: PartnerProductType;
  slug?: string;
  id?: number;
  nationality_code: string;
  booking_type?: BookingType;
  travel_date?: string;
  check_in?: string;
  check_out?: string;
  adults?: number;
  children?: number;
  child_ages?: number[];
  units?: { unit_option_id: number; quantity: number }[];
  addons?: { addon_id: number; quantity: number }[];
  transfer?: PartnerTransferFields;
}

export interface PartnerQuoteLine {
  type: string;
  label: string;
  quantity: number;
  unit_price: number;
  total: number;
  partner_unit_price: number;
  partner_total: number;
  commission_percent: number;
}

export interface PartnerQuote {
  valid: boolean;
  season_name: string | null;
  nationality: { code: string; group: NationalityGroup | null };
  lines: PartnerQuoteLine[];
  retail_total: number;
  partner_total: number;
  commission_amount: number;
  commission_percent: number;
  commission_source: CommissionSource;
  deposit_percent: number;
  deposit_amount: number;
  balance_amount: number;
  nights?: number;
}

/** POST /partner/bookings */
export interface PartnerBookingRequest extends PartnerQuoteRequest {
  lead: { name: string; email: string; phone?: string };
  travelers?: { first_name: string; last_name: string; type: "adult" | "child"; age?: number | null }[];
  payment_method?: string;
  notes?: string;
}

/** BookingResource as seen by the partner who made it (partner totals included). */
export interface PartnerBookingDetail {
  reference: string;
  access_token?: string;
  status: BookingStatus;
  payment_status: PaymentStatus;
  service_type: string;
  booking_type: BookingType | null;
  season_name: string | null;
  nationality: { code: string | null; group: NationalityGroup | null };
  product_title: string;
  travel_date: string | null;
  check_in: string | null;
  check_out: string | null;
  lead: { name: string; email: string; phone: string };
  adults: number;
  children: number;
  travellers: { first_name: string; last_name: string; type: string; age: number | null }[];
  lines: { label: string; quantity: number; unit_price: number; total: number; partner_unit_price?: number; partner_total?: number }[];
  totals: {
    retail_total: number;
    total: number;
    partner_total: number;
    commission_amount: number;
    commission_percent: number | null;
    commission_source: CommissionSource | null;
    deposit_percent: number;
    deposit_amount: number;
    balance_amount: number;
  };
  paid: number;
  due: number;
  payments: { type: string; status: string; amount: number; method: string | null; paid_at: string | null }[];
  payment_options: { id?: number; code?: string; type?: string; name: string; proof_required?: boolean }[];
  transfer: { destination: { name: string }; pickup_at: string; passengers: number } | null;
  created_at: string;
}

export interface PaymentMethod {
  id: number;
  type: "online" | "manual";
  name: string;
  instructions: string | null;
  account_details: Record<string, string>;
  currencies: CurrencyCode[];
  proof_required: boolean;
  surcharge_percent: number;
  code?: string | null;
  /** Hours after booking within which a manual payment must arrive. */
  deadline_hours?: number | null;
  /** Server-computed amount due now incl. surcharge (EUR + booking currency). */
  amount_due?: { amount_eur: number; amount: number; currency: CurrencyCode } | null;
}

export interface ProofUpload {
  files: File[];
  transaction_number: string;
  transfer_date: string;
  amount: number;
  sender_name: string;
}

export interface ContactRequest {
  name: string;
  email: string;
  phone?: string;
  subject: "booking" | "trip" | "partner" | "other";
  booking_reference?: string;
  message: string;
}
