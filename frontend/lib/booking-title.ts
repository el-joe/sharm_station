import type { BookingStatus } from "./api/types";

/** Which booking page title a status gets (shared by generateMetadata and the client update). */
export type BookingTitleKind = "received" | "confirmed" | "cancelled";

export function bookingTitleKind(status: BookingStatus | null | undefined): BookingTitleKind | null {
  if (!status) return null;
  if (status === "cancelled" || status === "refunded") return "cancelled";
  if (status === "confirmed" || status === "fully_paid" || status === "completed") return "confirmed";
  return "received";
}
