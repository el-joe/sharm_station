import { cookies } from "next/headers";
import type { BookingStatus, Locale } from "../api/types";
import { SESSION_COOKIE } from "../session-shared";
import { USE_MOCKS } from "../site";
import { API_BASE } from "./session";

export { bookingTitleKind } from "../booking-title";

/**
 * Booking status for the page metadata, request time only. Readable server-side when the visitor
 * has the session cookie (owner / partner) or opened a booking link with ?token=; otherwise null
 * (guests right after checkout keep their access token in sessionStorage) and the page sets a
 * neutral title that the client updates once the booking is loaded.
 */
export async function bookingStatusForMetadata(reference: string, locale: Locale, accessToken: string | null): Promise<BookingStatus | null> {
  if (USE_MOCKS) return null;
  const session = (await cookies()).get(SESSION_COOKIE)?.value ?? null;
  if (!session && !accessToken) return null;
  const search = new URLSearchParams({ locale });
  if (accessToken) search.set("token", accessToken);
  try {
    const res = await fetch(`${API_BASE}/bookings/${encodeURIComponent(reference)}?${search}`, {
      headers: { Accept: "application/json", ...(session ? { Authorization: `Bearer ${session}` } : {}) },
      signal: AbortSignal.timeout(2500),
      cache: "no-store",
    });
    if (!res.ok) return null;
    const body = (await res.json()) as { data?: { status?: BookingStatus } };
    return body.data?.status ?? null;
  } catch {
    return null;
  }
}
