/**
 * Google Maps URLs (server and client safe, no script).
 *
 * NEXT_PUBLIC_GOOGLE_MAPS_API_KEY is a browser key (restricted by HTTP referrer). With it, single
 * locations use the Maps Embed API; without it, the keyless maps.google.com embed.
 */
export const GOOGLE_MAPS_API_KEY = process.env.NEXT_PUBLIC_GOOGLE_MAPS_API_KEY?.trim() || "";
/** Optional Map ID (Cloud console): enables AdvancedMarkerElement and cloud styling. */
export const GOOGLE_MAPS_MAP_ID = process.env.NEXT_PUBLIC_GOOGLE_MAPS_MAP_ID?.trim() || "";
export const HAS_GOOGLE_MAPS_KEY = GOOGLE_MAPS_API_KEY !== "";

export type LatLng = { lat: number; lng: number };

const coord = (p: LatLng) => `${Number(p.lat.toFixed(6))},${Number(p.lng.toFixed(6))}`;

/** iframe src for one location (pin at `point`). */
export function embedUrl(point: LatLng, { zoom = 15, locale }: { zoom?: number; locale: string }): string {
  if (HAS_GOOGLE_MAPS_KEY) {
    const u = new URL("https://www.google.com/maps/embed/v1/place");
    u.searchParams.set("key", GOOGLE_MAPS_API_KEY);
    u.searchParams.set("q", coord(point));
    u.searchParams.set("zoom", String(zoom));
    u.searchParams.set("language", locale);
    return u.toString();
  }
  return `https://maps.google.com/maps?q=${coord(point)}&z=${zoom}&hl=${encodeURIComponent(locale)}&output=embed`;
}

/** iframe src centred on an area without a pin (keyless fallback of multi-marker maps). */
export function areaEmbedUrl(center: LatLng, { zoom = 12, locale }: { zoom?: number; locale: string }): string {
  if (HAS_GOOGLE_MAPS_KEY) {
    const u = new URL("https://www.google.com/maps/embed/v1/view");
    u.searchParams.set("key", GOOGLE_MAPS_API_KEY);
    u.searchParams.set("center", coord(center));
    u.searchParams.set("zoom", String(zoom));
    u.searchParams.set("language", locale);
    return u.toString();
  }
  return `https://maps.google.com/maps?ll=${coord(center)}&z=${zoom}&hl=${encodeURIComponent(locale)}&output=embed`;
}

/** "Open in Google Maps" for a point (or a free-text query). */
export function placeUrl(point: LatLng | string): string {
  const q = typeof point === "string" ? encodeURIComponent(point) : coord(point);
  return `https://www.google.com/maps/search/?api=1&query=${q}`;
}

/** "Get directions" to a point; with `origin`, the full route (driving). */
export function directionsUrl(destination: LatLng | string, origin?: LatLng | string | null): string {
  const fmt = (p: LatLng | string) => (typeof p === "string" ? encodeURIComponent(p) : coord(p));
  const parts = [`api=1`, `destination=${fmt(destination)}`];
  if (origin) parts.push(`origin=${fmt(origin)}`, "travelmode=driving");
  return `https://www.google.com/maps/dir/?${parts.join("&")}`;
}

/** Centre and a zoom that roughly fits the points (keyless embed has no fitBounds). */
export function fitView(points: LatLng[], fallback: LatLng | null, fallbackZoom = 11): { center: LatLng; zoom: number } | null {
  if (!points.length) return fallback ? { center: fallback, zoom: fallbackZoom } : null;
  if (points.length === 1) return { center: points[0], zoom: 14 };
  const lats = points.map((p) => p.lat);
  const lngs = points.map((p) => p.lng);
  const [minLat, maxLat, minLng, maxLng] = [Math.min(...lats), Math.max(...lats), Math.min(...lngs), Math.max(...lngs)];
  const span = Math.max(maxLat - minLat, maxLng - minLng, 0.005);
  // ~360° at zoom 0; leave a margin around the points.
  const zoom = Math.max(5, Math.min(14, Math.floor(Math.log2(360 / (span * 1.6)))));
  return { center: { lat: (minLat + maxLat) / 2, lng: (minLng + maxLng) / 2 }, zoom };
}
