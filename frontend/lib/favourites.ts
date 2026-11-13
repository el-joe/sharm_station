"use client";

/**
 * Favourites (SRS 13.1 "Customer account: favourites"), stored in localStorage
 * until the backend has a favourites endpoint. A snapshot of the card is stored
 * so the favourites page renders without extra API calls; prices are re-checked
 * on the product page when booking.
 */
import { useCallback, useSyncExternalStore } from "react";
import type { RoomSummary, TripSummary } from "./api/types";

const KEY = "ssv_favourites";
const EVENT = "ssv:favourites";

export type Favourite =
  | { kind: "trip"; key: string; added_at: number; item: TripSummary }
  | { kind: "room"; key: string; added_at: number; item: RoomSummary };

export const favouriteKey = (kind: Favourite["kind"], id: number | string) => `${kind}:${id}`;

let cacheRaw: string | null | undefined;
let cacheList: Favourite[] = [];
const EMPTY: Favourite[] = [];

function read(): Favourite[] {
  let raw: string | null = null;
  try {
    raw = window.localStorage.getItem(KEY);
  } catch {
    raw = null;
  }
  if (raw === cacheRaw) return cacheList;
  cacheRaw = raw;
  try {
    const parsed = raw ? (JSON.parse(raw) as Favourite[]) : [];
    cacheList = Array.isArray(parsed) ? parsed.filter((f) => f && (f.kind === "trip" || f.kind === "room") && f.item) : [];
  } catch {
    cacheList = [];
  }
  return cacheList;
}

function write(list: Favourite[]) {
  try {
    window.localStorage.setItem(KEY, JSON.stringify(list));
  } catch {
    /* storage full or unavailable */
  }
  window.dispatchEvent(new Event(EVENT));
}

function subscribe(cb: () => void) {
  const onStorage = (e: StorageEvent) => e.key === KEY && cb();
  window.addEventListener(EVENT, cb);
  window.addEventListener("storage", onStorage);
  return () => {
    window.removeEventListener(EVENT, cb);
    window.removeEventListener("storage", onStorage);
  };
}

export function useFavourites() {
  const list = useSyncExternalStore(subscribe, read, () => EMPTY);
  const toggle = useCallback((fav: Omit<Favourite, "added_at">) => {
    const current = read();
    const exists = current.some((f) => f.key === fav.key);
    write(exists ? current.filter((f) => f.key !== fav.key) : [{ ...fav, added_at: Date.now() } as Favourite, ...current]);
    return !exists;
  }, []);
  const remove = useCallback((key: string) => write(read().filter((f) => f.key !== key)), []);
  const has = useCallback((key: string) => list.some((f) => f.key === key), [list]);
  return { list, toggle, remove, has };
}
