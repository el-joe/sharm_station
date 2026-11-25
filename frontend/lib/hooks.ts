"use client";

import { useEffect, useState, useSyncExternalStore } from "react";
import { toISODate } from "./utils";

const noopSubscribe = () => () => {};

/**
 * Today's date (YYYY-MM-DD) in the browser, `null` during prerender/SSR.
 * Cache Components forbids reading the clock while prerendering, so date
 * defaults are derived from this after hydration.
 */
export function useToday(): string | null {
  return useSyncExternalStore(noopSubscribe, () => toISODate(new Date()), () => null);
}

/**
 * Debounced copy of a plain-data value (JSON-serialisable). Keyed on its serialisation, so a new object with the
 * same content does not restart the timer: several debounced values in one component never keep resetting each
 * other through re-renders.
 */
export function useDebounced<T>(value: T, delay = 350): T {
  const key = JSON.stringify(value) ?? "null";
  const [debounced, setDebounced] = useState<{ key: string; value: T }>(() => ({ key, value }));
  useEffect(() => {
    const id = setTimeout(() => setDebounced((prev) => (prev.key === key ? prev : { key, value: JSON.parse(key) as T })), delay);
    return () => clearTimeout(id);
  }, [key, delay]);
  return debounced.value;
}

/** Current URL query string in the browser ("" during prerender) without opting into useSearchParams' CSR bailout. */
export function useLocationSearch(): string {
  return useSyncExternalStore(
    (cb) => {
      window.addEventListener("popstate", cb);
      return () => window.removeEventListener("popstate", cb);
    },
    () => window.location.search,
    () => "",
  );
}
