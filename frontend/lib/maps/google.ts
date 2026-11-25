"use client";

import { importLibrary, setOptions } from "@googlemaps/js-api-loader";
import { useLocale } from "next-intl";
import { useEffect } from "react";
import { GOOGLE_MAPS_API_KEY, GOOGLE_MAPS_MAP_ID, HAS_GOOGLE_MAPS_KEY } from "./urls";

/** Language the Maps JavaScript API was loaded with in this document (null: not loaded yet). */
let loadedLanguage: string | null = null;

/**
 * Loads one Maps JavaScript API library (the script is fetched once, on the first call).
 *
 * The language is fixed by the first call: Google's API cannot switch language inside a document
 * (the `google.maps` global and its loader options are set once; a second script with another
 * `language` is refused). So a locale switch while Maps JS is loaded must be a full page load:
 * see `switchLocaleHref` / `useMapsLocaleGuard` below.
 */
export function loadGoogleMaps<L extends keyof google.maps.ImportLibraryMap>(library: L, locale: string): Promise<google.maps.ImportLibraryMap[L]> {
  if (!HAS_GOOGLE_MAPS_KEY) return Promise.reject(new Error("NEXT_PUBLIC_GOOGLE_MAPS_API_KEY is not set"));
  if (!loadedLanguage) {
    setOptions({ key: GOOGLE_MAPS_API_KEY, v: "weekly", libraries: ["places"], language: locale, region: "EG", ...(GOOGLE_MAPS_MAP_ID ? { mapIds: [GOOGLE_MAPS_MAP_ID] } : {}) });
    loadedLanguage = locale;
  }
  return importLibrary(library);
}

/** True when Maps JS is loaded in another language than `locale` (a client-side switch would keep the old one). */
export function mapsNeedReloadFor(locale: string): boolean {
  return loadedLanguage !== null && loadedLanguage !== locale;
}

/**
 * Safety net for locale changes that did not go through the language switcher (e.g. a link with
 * another locale, the profile form): when the page is now in a different locale than the loaded
 * Maps JS, reload the document so maps, Places suggestions and their attributions use the new language.
 */
export function useMapsLocaleGuard() {
  const locale = useLocale();
  useEffect(() => {
    if (mapsNeedReloadFor(locale)) window.location.reload();
  }, [locale]);
}
