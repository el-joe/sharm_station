"use client";

import { useNationality } from "@/lib/nationality";
import { normalizeNationality } from "@/lib/nationality-shared";
import { useCallback, useSyncExternalStore } from "react";

const KEY = "ssv_partner_client_nationality";
const EVENT = "ssv:partner-client-nationality";

function read(): string | null {
  try {
    return normalizeNationality(sessionStorage.getItem(KEY));
  } catch {
    return null;
  }
}

function subscribe(cb: () => void) {
  window.addEventListener(EVENT, cb);
  return () => window.removeEventListener(EVENT, cb);
}

/**
 * The end client's nationality the partner is working with (D7). It is independent from the partner's
 * own `ssv_nationality` cookie; it is remembered for the browser session so catalogue, price list and
 * the booking flow stay on the same client. Defaults to the partner's own nationality.
 */
export function useClientNationality(): [string | null, (code: string) => void] {
  const stored = useSyncExternalStore(subscribe, read, () => null);
  const own = useNationality().code ?? null;
  const set = useCallback((code: string) => {
    try {
      sessionStorage.setItem(KEY, code);
    } catch {
      /* storage unavailable */
    }
    window.dispatchEvent(new Event(EVENT));
  }, []);
  return [stored ?? own, set];
}
