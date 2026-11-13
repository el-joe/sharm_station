export const SITE_NAME = "Sharm Station Viaggi";
export const SITE_URL = (process.env.NEXT_PUBLIC_SITE_URL ?? "http://localhost:3000").replace(/\/$/, "");
export const API_URL = (process.env.NEXT_PUBLIC_API_URL ?? "http://localhost:8090/api/v1").replace(/\/$/, "");
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
