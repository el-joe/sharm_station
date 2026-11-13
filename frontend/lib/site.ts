export const SITE_NAME = "Sharm Station Viaggi";
export const SITE_URL = (process.env.NEXT_PUBLIC_SITE_URL ?? "http://localhost:3000").replace(/\/$/, "");
/** Public API base, identical on server and browser (use it for anything rendered into HTML, e.g. image URLs). */
export const PUBLIC_API_URL = (process.env.NEXT_PUBLIC_API_URL ?? "http://localhost:8090/api/v1").replace(/\/$/, "");
/**
 * Browser requests use the public API URL; server rendering can use a direct internal URL
 * (API_INTERNAL_URL, e.g. http://127.0.0.1:8090/api/v1) so it never depends on DNS or the proxy.
 */
export const API_URL = (
  typeof window === "undefined" ? (process.env.API_INTERNAL_URL ?? PUBLIC_API_URL) : PUBLIC_API_URL
).replace(/\/$/, "");
export const USE_MOCKS = process.env.NEXT_PUBLIC_USE_MOCKS === "true";
export const WHATSAPP_NUMBER = process.env.NEXT_PUBLIC_WHATSAPP_NUMBER ?? "201234567890";
export const PHONE_DISPLAY = "+20 12 3456 7890";
export const CONTACT_EMAIL = "hello@sharmstation.example";
export const GOOGLE_REVIEW_URL =
  process.env.NEXT_PUBLIC_GOOGLE_REVIEW_URL ??
  "https://search.google.com/local/writereview?placeid=ChIJ_sharm_station_demo";

export function whatsappHref(text?: string) {
  const base = `https://wa.me/${WHATSAPP_NUMBER}`;
  return text ? `${base}?text=${encodeURIComponent(text)}` : base;
}
