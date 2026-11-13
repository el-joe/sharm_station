import { routing } from "@/i18n/routing";
import { getCatalogueKeys, getDestinations } from "@/lib/api/server";
import { POSTS } from "@/lib/content/blog";
import { SITE_URL } from "@/lib/site";
import type { MetadataRoute } from "next";

const STATIC_PATHS = [
  "",
  "/trips",
  "/nile",
  "/packages",
  "/accommodation",
  "/transfers",
  "/destinations",
  "/become-a-partner",
  "/about",
  "/contact",
  "/faq",
  "/blog",
  "/booking-terms",
  "/cancellation-policy",
  "/privacy",
  "/cookie-policy",
];

export default async function sitemap(): Promise<MetadataRoute.Sitemap> {
  const [keys, destinations] = await Promise.all([getCatalogueKeys(), getDestinations("en")]);
  const paths = [
    ...STATIC_PATHS,
    ...keys.trips.map((s) => `/trips/${s}`),
    ...keys.nile.map((s) => `/nile/${s}`),
    ...keys.packages.map((s) => `/packages/${s}`),
    ...keys.rooms.map((id) => `/rooms/${id}`),
    ...destinations.map((d) => `/destinations/${d.slug}`),
    ...POSTS.map((p) => `/blog/${p.slug}`),
  ];
  // One entry per locale, each listing every language version (hreflang).
  return paths.flatMap((path) =>
    routing.locales.map((locale) => ({
      url: `${SITE_URL}/${locale}${path}`,
      changeFrequency: path === "" ? ("daily" as const) : ("weekly" as const),
      priority: path === "" ? 1 : path.split("/").length > 2 ? 0.7 : 0.8,
      alternates: { languages: Object.fromEntries(routing.locales.map((l) => [l, `${SITE_URL}/${l}${path}`])) },
    })),
  );
}
