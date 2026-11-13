import type { BookingType, PricingMode, TripFilters, TripSort, TripType } from "@/lib/api/types";

export type ListingKind = "trips" | "nile" | "packages";

export interface ListingState {
  type: TripType[];
  destination?: string;
  booking_type?: BookingType;
  pricing_mode?: PricingMode;
  price_max?: number;
  free_cancellation: boolean;
  sort: TripSort;
  page: number;
}

const SORTS: TripSort[] = ["recommended", "price_asc", "price_desc", "rating", "newest"];
const TYPES: TripType[] = ["single_day", "multi_day", "nile", "package"];

type Raw = Record<string, string | string[] | undefined>;
const first = (v: string | string[] | undefined) => (Array.isArray(v) ? v[0] : v);

export function parseListingState(raw: Raw): ListingState {
  const type = (first(raw.type) ?? "")
    .split(",")
    .filter((t): t is TripType => TYPES.includes(t as TripType));
  const sort = first(raw.sort) as TripSort | undefined;
  const bt = first(raw.booking_type);
  const pm = first(raw.pricing_mode);
  const priceMax = Number(first(raw.price_max));
  const page = Number(first(raw.page));
  return {
    type,
    destination: first(raw.destination) || undefined,
    booking_type: bt === "private" || bt === "group" ? bt : undefined,
    pricing_mode: pm === "per_person" || pm === "per_unit" ? pm : undefined,
    price_max: Number.isFinite(priceMax) && priceMax > 0 ? priceMax : undefined,
    free_cancellation: first(raw.free_cancellation) === "1",
    sort: sort && SORTS.includes(sort) ? sort : "recommended",
    page: Number.isFinite(page) && page > 1 ? Math.floor(page) : 1,
  };
}

/** Listing state -> API filters. No date filter exists (CR-5). */
export function toApiFilters(kind: ListingKind, s: ListingState): TripFilters {
  const type: TripFilters["type"] =
    kind === "nile" ? "nile" : kind === "packages" ? undefined : s.type.length ? s.type : ["single_day", "multi_day"];
  return {
    type,
    destination: s.destination,
    booking_type: s.booking_type,
    pricing_mode: s.pricing_mode,
    price_max: s.price_max,
    free_cancellation: s.free_cancellation || undefined,
    sort: s.sort,
    page: s.page,
    per_page: 9,
  };
}

export function toQuery(s: Partial<ListingState>): Record<string, string> {
  const q: Record<string, string> = {};
  if (s.type?.length) q.type = s.type.join(",");
  if (s.destination) q.destination = s.destination;
  if (s.booking_type) q.booking_type = s.booking_type;
  if (s.pricing_mode) q.pricing_mode = s.pricing_mode;
  if (s.price_max) q.price_max = String(s.price_max);
  if (s.free_cancellation) q.free_cancellation = "1";
  if (s.sort && s.sort !== "recommended") q.sort = s.sort;
  if (s.page && s.page > 1) q.page = String(s.page);
  return q;
}
