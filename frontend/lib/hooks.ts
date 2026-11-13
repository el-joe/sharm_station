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

export function useDebounced<T>(value: T, delay = 350): T {
  const [debounced, setDebounced] = useState(value);
  useEffect(() => {
    const id = setTimeout(() => setDebounced(value), delay);
    return () => clearTimeout(id);
  }, [value, delay]);
  return debounced;
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
