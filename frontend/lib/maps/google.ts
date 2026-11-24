"use client";

import { importLibrary, setOptions } from "@googlemaps/js-api-loader";
import { GOOGLE_MAPS_API_KEY, GOOGLE_MAPS_MAP_ID, HAS_GOOGLE_MAPS_KEY } from "./urls";

let configured = false;

/**
 * Loads one Maps JavaScript API library (the script is fetched once, on the first call).
 * The language is fixed by the first call: the API cannot switch language without a reload.
 */
export function loadGoogleMaps<L extends keyof google.maps.ImportLibraryMap>(library: L, locale: string): Promise<google.maps.ImportLibraryMap[L]> {
  if (!HAS_GOOGLE_MAPS_KEY) return Promise.reject(new Error("NEXT_PUBLIC_GOOGLE_MAPS_API_KEY is not set"));
  if (!configured) {
    setOptions({ key: GOOGLE_MAPS_API_KEY, v: "weekly", libraries: ["places"], language: locale, region: "EG", ...(GOOGLE_MAPS_MAP_ID ? { mapIds: [GOOGLE_MAPS_MAP_ID] } : {}) });
    configured = true;
  }
  return importLibrary(library);
}
