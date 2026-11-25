"use client";

import type { BookingDetail, BookingType, CreateBookingResponse, QuoteResult, RoomAllocation, RoomOptionSelection } from "./api/types";

/** What the booking widget hands to /checkout (kept in sessionStorage, never in the URL). */
export interface CheckoutDraft {
  service_type: "trip" | "room";
  product: { slug?: string; room_id?: number; title: string; image: string; href: string; subtitle?: string };
  booking_type?: BookingType;
  travel_date?: string;
  check_in?: string;
  check_out?: string;
  adults: number;
  children: number;
  child_ages: number[];
  units: { unit_option_id: number; quantity: number; name: string }[];
  addons: { addon_id: number; quantity: number; name: string }[];
  /** Single room: extra beds / cots. */
  extra_beds?: number;
  cots?: number;
  /** Multi-room stay (B2.1): the allocation sent as rooms[] to POST /bookings. */
  rooms?: (RoomAllocation & { name: string; number?: string | null })[];
  /** Trips / packages: optional rooms (B2.3). */
  room_options?: (RoomOptionSelection & { name: string })[];
  quote: QuoteResult;
  season_note?: string | null;
  created_at: number;
}

const DRAFT_KEY = "ssv_checkout_draft";
const BOOKING_KEY = "ssv_booking_";

function safe<T>(fn: () => T, fallback: T): T {
  try {
    return fn();
  } catch {
    return fallback;
  }
}

export function saveDraft(draft: CheckoutDraft) {
  safe(() => sessionStorage.setItem(DRAFT_KEY, JSON.stringify(draft)), undefined);
}

export function readDraft(): CheckoutDraft | null {
  return safe(() => {
    const raw = sessionStorage.getItem(DRAFT_KEY);
    return raw ? (JSON.parse(raw) as CheckoutDraft) : null;
  }, null);
}

export function clearDraft() {
  safe(() => sessionStorage.removeItem(DRAFT_KEY), undefined);
}

export type StoredBooking = (BookingDetail | CreateBookingResponse) & { draft: CheckoutDraft; lead: { name: string; email: string; phone: string } };

export function saveBooking(booking: StoredBooking) {
  safe(() => sessionStorage.setItem(BOOKING_KEY + booking.reference, JSON.stringify(booking)), undefined);
}

export function readBooking(reference: string): StoredBooking | null {
  return safe(() => {
    const raw = sessionStorage.getItem(BOOKING_KEY + reference);
    return raw ? (JSON.parse(raw) as StoredBooking) : null;
  }, null);
}
