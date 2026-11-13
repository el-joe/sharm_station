import type { BookingDetail } from "@/lib/api/types";

/** Bookings carry no image yet: pick a representative local photo by service. */
export function bookingImage(b: Pick<BookingDetail, "service_type" | "product_image" | "product_title">): string {
  if (b.product_image) return b.product_image;
  if (b.service_type === "room") return "/images/room-deluxe.webp";
  const title = b.product_title.toLowerCase();
  if (/nile|cruise|felucca|crociera/.test(title)) return "/images/nile-cruise.webp";
  if (/pyramid|cairo|giza/.test(title)) return "/images/cairo.webp";
  if (/luxor|valley|karnak/.test(title)) return "/images/luxor.webp";
  if (/aswan|philae/.test(title)) return "/images/aswan.webp";
  if (/snorkel|reef|ras mohammed|sea|island/.test(title)) return "/images/hurghada.webp";
  return "/images/sharm.webp";
}

export function bookingDate(b: Pick<BookingDetail, "travel_date" | "check_in">): string | null {
  return b.travel_date ?? b.check_in ?? null;
}
