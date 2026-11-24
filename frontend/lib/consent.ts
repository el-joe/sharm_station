"use client";

import { useSyncExternalStore } from "react";

/**
 * Cookie consent stored by components/layout/CookieBanner: "all" | "essential",
 * null while the visitor has not chosen, "unavailable" when storage is blocked.
 */
export const CONSENT_KEY = "ssv_cookie_consent";
const EVENT = "ssv:consent";

export type ConsentValue = "all" | "essential";

export function readConsent(): string | null {
  try {
    return window.localStorage.getItem(CONSENT_KEY);
  } catch {
    return "unavailable";
  }
}

export function saveConsent(value: ConsentValue) {
  try {
    window.localStorage.setItem(CONSENT_KEY, value);
  } catch {
    /* storage blocked: applies to this page view only */
  }
  window.dispatchEvent(new Event(EVENT));
}

export function subscribeConsent(cb: () => void) {
  window.addEventListener(EVENT, cb);
  window.addEventListener("storage", cb);
  return () => {
    window.removeEventListener(EVENT, cb);
    window.removeEventListener("storage", cb);
  };
}

/** Server snapshot: "pending" keeps consent-dependent UI out of the prerendered HTML. */
export function useConsent(): string | null {
  return useSyncExternalStore(subscribeConsent, readConsent, () => "pending");
}

/* ---- Google Maps (non-essential third party) ---------------------------------------- */

/** "Show map" clicked on a click-to-load placeholder: allowed for the rest of this page view (all maps). */
let mapsOptIn = false;
const optInListeners = new Set<() => void>();

export function allowGoogleMapsOnce() {
  mapsOptIn = true;
  optInListeners.forEach((cb) => cb());
}

function subscribeOptIn(cb: () => void) {
  optInListeners.add(cb);
  return () => optInListeners.delete(cb);
}

/**
 * Whether Google Maps (embeds, Maps JavaScript API, Places) may load:
 * "pending" during server render / hydration, "allowed" after "Accept all" or a
 * "Show map" click, "blocked" with "Essential only", no choice yet or blocked storage.
 */
export function useGoogleMapsConsent(): "pending" | "allowed" | "blocked" {
  const consent = useConsent();
  const optIn = useSyncExternalStore(subscribeOptIn, () => mapsOptIn, () => false);
  if (consent === "pending") return "pending";
  return consent === "all" || optIn ? "allowed" : "blocked";
}
