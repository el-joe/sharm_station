/**
 * Cached server-side data access (Cache Components). Every function takes the
 * locale explicitly so it becomes part of the cache key. Prices are fetched in
 * EUR and converted in the browser (currency is a per-visitor cookie), which
 * keeps the catalogue pages in the static shell.
 */
import { cacheLife, cacheTag } from "next/cache";
import { api } from "./client";
import { mockApi } from "./mock-api";
import type { Locale, RoomFilters, TripFilters } from "./types";

export async function getMeta(locale: Locale) {
  "use cache";
  cacheLife("hours");
  cacheTag("meta");
  return api.meta({ locale });
}

export async function getHome(locale: Locale) {
  "use cache";
  cacheLife("minutes");
  cacheTag("home");
  return api.home({ locale });
}

export async function getTrips(filters: TripFilters, locale: Locale) {
  "use cache";
  cacheLife("minutes");
  cacheTag("trips");
  return api.trips(filters, { locale });
}

export async function getPackages(filters: TripFilters, locale: Locale) {
  "use cache";
  cacheLife("minutes");
  cacheTag("packages");
  return api.packages(filters, { locale });
}

export async function getTrip(slug: string, locale: Locale) {
  "use cache";
  cacheLife("minutes");
  cacheTag("trips", `trip:${slug}`);
  return api.trip(slug, { locale });
}

export async function getPackage(slug: string, locale: Locale) {
  "use cache";
  cacheLife("minutes");
  cacheTag("packages", `package:${slug}`);
  return api.packageDetail(slug, { locale });
}

export async function getRooms(filters: RoomFilters, locale: Locale) {
  "use cache";
  cacheLife("minutes");
  cacheTag("rooms");
  return api.rooms(filters, { locale });
}

export async function getRoom(id: number, locale: Locale) {
  "use cache";
  cacheLife("minutes");
  cacheTag("rooms", `room:${id}`);
  return api.room(id, { locale });
}

export async function getGoogleReviews(locale: Locale) {
  "use cache";
  cacheLife("hours");
  cacheTag("google-reviews");
  return api.googleReviews({ locale });
}

/** Slugs/ids to prerender at build time (generateStaticParams, sitemap). */
export async function getCatalogueKeys() {
  "use cache";
  cacheLife("hours");
  const [single, multi, nile, packages, rooms] = await Promise.all([
    api.trips({ type: "single_day", per_page: 48 }, { locale: "en" }),
    api.trips({ type: "multi_day", per_page: 48 }, { locale: "en" }),
    api.trips({ type: "nile", per_page: 48 }, { locale: "en" }),
    api.packages({ per_page: 48 }, { locale: "en" }),
    api.rooms({}, { locale: "en" }),
  ]);
  const keys = {
    trips: [...single.data, ...multi.data].map((t) => t.slug),
    nile: nile.data.map((t) => t.slug),
    packages: packages.data.map((t) => t.slug),
    rooms: rooms.data.map((r) => r.id),
    buildings: [...new Set(rooms.data.map((r) => r.building.slug))],
  };
  // Always have at least one param per route (required by Cache Components).
  if (!keys.trips.length) keys.trips = mockApi.allProductSlugs().filter((t) => t.type === "single_day" || t.type === "multi_day").map((t) => t.slug);
  if (!keys.nile.length) keys.nile = mockApi.allProductSlugs().filter((t) => t.type === "nile").map((t) => t.slug);
  if (!keys.packages.length) keys.packages = mockApi.allProductSlugs().filter((t) => t.type === "package").map((t) => t.slug);
  if (!keys.rooms.length) keys.rooms = mockApi.roomIds();
  if (!keys.buildings.length) keys.buildings = ["hotel-nile-view"];
  return keys;
}

export async function getDestinations(locale: Locale) {
  "use cache";
  cacheLife("hours");
  cacheTag("destinations");
  return api.destinations({ locale });
}

export async function getDestination(slug: string, locale: Locale) {
  "use cache";
  cacheLife("minutes");
  cacheTag("destinations", `destination:${slug}`);
  return api.destinationDetail(slug, { locale });
}

export async function getTransferCatalog(locale: Locale) {
  "use cache";
  cacheLife("minutes");
  cacheTag("transfers");
  return api.transferCatalog({ locale });
}
