"use client";

import { assignFromRooms, autoAssign, combinationPicks, partyTravellers, validateAllocation, type PickedRoom } from "@/lib/allocation";
import { api, ApiError } from "@/lib/api/client";
import type { Locale, RoomAllocation, RoomCombination, RoomSummary } from "@/lib/api/types";
import { useCurrency } from "@/lib/currency";
import { useDebounced } from "@/lib/hooks";
import { useQuery } from "@tanstack/react-query";
import { useLocale, useTranslations } from "next-intl";
import { useMemo, useState } from "react";
import type { BoardRoom } from "./RoomAllocationBoard";

/**
 * State of a multi-room stay in one building (B2.1): the rooms picked (with extra beds / cots), who sleeps where,
 * the SRS 7.2 client checks and the `rooms[]` allocation for the quote / booking. Shared by the website
 * (MultiRoomBooking) and the partner panel's new-booking wizard; each one calls its own quote endpoint.
 */
export function useMultiRoomStay({
  rooms,
  adults,
  childAges,
  initial,
}: {
  /** Rooms of the building (cards: capacity, extra beds / cots offered). */
  rooms: RoomSummary[];
  adults: number;
  childAges: (number | null)[];
  /** Rebuild the board from a saved allocation (e.g. back from the next step). */
  initial?: RoomAllocation[] | null;
}) {
  const t = useTranslations("multiRoom");
  const [picked, setPicked] = useState<PickedRoom[]>(() => (initial ?? []).map((r) => ({ room_id: r.room_id, extra_beds: r.extra_beds ?? 0, cots: r.cots ?? 0 })));
  const [assignment, setAssignment] = useState<Record<string, string | null>>(() => (initial?.length ? assignFromRooms(initial, partyTravellers(adults, childAges)) : {}));
  const [applied, setApplied] = useState<number | null>(null);
  const [announcement, setAnnouncement] = useState("");

  const travellers = partyTravellers(adults, childAges);
  const byId = useMemo(() => new Map(rooms.map((r) => [r.id, r])), [rooms]);

  const boardRooms: BoardRoom[] = picked.flatMap((p): BoardRoom[] => {
    const r = byId.get(p.room_id);
    if (!r) return [];
    return [
      {
        key: String(r.id),
        name: r.name,
        number: r.number,
        capacity: { max_adults: r.max_adults, max_children: r.max_children, max_total: r.max_total },
        extra_beds: p.extra_beds,
        cots: p.cots,
        max_extra_beds: r.extra_beds?.max ?? 0,
        max_cots: r.cots?.max ?? 0,
        extra_bed_price: r.extra_beds?.price ?? null,
        cot_price: r.cots?.price ?? null,
      },
    ];
  });

  const clientErrors = validateAllocation(boardRooms, travellers, assignment);

  const allocation: RoomAllocation[] = boardRooms.map((r) => {
    const inRoom = travellers.filter((tr) => assignment[tr.id] === r.key);
    return {
      room_id: Number(r.key),
      adults: inRoom.filter((tr) => tr.type === "adult").length,
      children_ages: inRoom.filter((tr) => tr.type === "child" && tr.age != null).map((tr) => tr.age!),
      extra_beds: r.extra_beds,
      cots: r.cots,
    };
  });

  /** One click applies the rooms, their extra beds / cots (when the API sends them) and the allocation. */
  const applyCombination = (c: RoomCombination, index: number) => {
    const next = combinationPicks(c);
    setPicked(next);
    setAssignment(assignFromRooms(c.request.rooms, travellers));
    setApplied(index);
    setAnnouncement(t("announceApplied", { count: next.length }));
  };

  const addRoom = (roomId: number) => {
    if (!roomId || picked.some((p) => p.room_id === roomId)) return;
    setPicked((list) => [...list, { room_id: roomId, extra_beds: 0, cots: 0 }]);
    setApplied(null);
  };

  const removeRoom = (key: string) => {
    setPicked((list) => list.filter((p) => String(p.room_id) !== key));
    setAssignment((a) => Object.fromEntries(Object.entries(a).map(([k, v]) => [k, v === key ? null : v])));
    setApplied(null);
  };

  return {
    travellers,
    picked,
    boardRooms,
    assignment,
    allocation,
    clientErrors,
    applied,
    announcement,
    available: rooms.filter((r) => !picked.some((p) => p.room_id === r.id)),
    applyCombination,
    addRoom,
    removeRoom,
    assign: (travellerId: string, key: string | null) => setAssignment((a) => ({ ...a, [travellerId]: key })),
    setExtras: (key: string, extras: { extra_beds?: number; cots?: number }) => setPicked((list) => list.map((p) => (String(p.room_id) === key ? { ...p, ...extras } : p))),
    autoAssign: () => setAssignment(autoAssign(boardRooms, travellers)),
    /** The party changed: suggestions computed for the previous one no longer apply. */
    clearApplied: () => setApplied(null),
    /** Another building: start again. */
    reset: () => {
      setPicked([]);
      setAssignment({});
      setApplied(null);
    },
    /** Start from one room with everybody auto-assigned to it. */
    start: (room: RoomSummary) => {
      const r = { key: String(room.id), capacity: { max_adults: room.max_adults, max_children: room.max_children, max_total: room.max_total } };
      setPicked([{ room_id: room.id, extra_beds: 0, cots: 0 }]);
      setAssignment(autoAssign([r], travellers));
      setApplied(null);
    },
  };
}

export type MultiRoomStay = ReturnType<typeof useMultiRoomStay>;

/** GET /rooms/combinations for the party on screen, priced for `nationality` (the visitor's or the partner's client). */
export function useRoomCombinations({
  building,
  checkIn,
  checkOut,
  adults,
  childAges,
  nationality,
  enabled,
}: {
  building: string;
  checkIn: string;
  checkOut: string;
  adults: number;
  childAges: number[];
  nationality: string | null;
  enabled: boolean;
}) {
  const locale = useLocale() as Locale;
  const { currency } = useCurrency();
  const now = { building, check_in: checkIn, check_out: checkOut, adults, child_ages: childAges };
  const debounced = useDebounced(now, 300);
  // Only ask for the party on screen (never apply suggestions computed for a previous party).
  const current = JSON.stringify(debounced) === JSON.stringify(now);
  const query = useQuery({
    queryKey: ["room-combinations", debounced, currency, locale, nationality],
    queryFn: ({ signal }) => api.roomCombinations(debounced, { locale, currency, nationality, signal }),
    enabled: enabled && Boolean(building) && current,
    retry: false,
  });
  return { query, current };
}

export type RoomCombinationsState = ReturnType<typeof useRoomCombinations>;

/** 422 messages of a multi-room quote: per room position (rooms.N.*) and about the whole stay. */
export function splitRoomErrors(error: unknown): { roomErrors: Record<number, string[]>; errors: string[] } {
  const roomErrors: Record<number, string[]> = {};
  const errors: string[] = [];
  if (error instanceof ApiError && error.isValidation) {
    for (const [key, list] of Object.entries(error.errors)) {
      const m = key.match(/^rooms\.(\d+)\./);
      if (m) (roomErrors[Number(m[1])] ??= []).push(...list);
      else errors.push(...list);
    }
  }
  return { roomErrors, errors };
}
