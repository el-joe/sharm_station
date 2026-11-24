"use client";

import type { ComboboxGroup, ComboboxOption } from "@/components/ui/Combobox";
import { useCallback, useMemo, useRef } from "react";
import { loadGoogleMaps } from "./google";
import { HAS_GOOGLE_MAPS_KEY, type LatLng } from "./urls";

/** Places API (New) radius limit for a circle bias, in metres. */
const MAX_BIAS_M = 50_000;

export interface PickedPlace extends LatLng {
  name: string;
}

/**
 * Google Places Autocomplete (Places API (New), programmatic) for a Combobox: `search()` returns a
 * "Google Maps" group biased to `bias` (the pick-up hub) and limited to Egypt; `resolve()` turns a
 * picked option into name + coordinates (one Place Details call, closing the billing session).
 * Only usable with NEXT_PUBLIC_GOOGLE_MAPS_API_KEY and the visitor's consent (`enabled`).
 */
export function useGooglePlaces({ enabled, locale, bias, radiusKm, groupLabel, icon }: { enabled: boolean; locale: string; bias: LatLng | null; radiusKm?: number | null; groupLabel: string; icon: ComboboxOption["icon"] }) {
  const token = useRef<google.maps.places.AutocompleteSessionToken | null>(null);
  const active = enabled && HAS_GOOGLE_MAPS_KEY;
  const biasLat = bias?.lat;
  const biasLng = bias?.lng;

  const search = useCallback(
    async (query: string, signal: AbortSignal): Promise<ComboboxGroup | null> => {
      if (!active || query.trim().length < 2) return null;
      const { AutocompleteSuggestion, AutocompleteSessionToken } = await loadGoogleMaps("places", locale);
      token.current ??= new AutocompleteSessionToken();
      const request: google.maps.places.AutocompleteRequest = { input: query, sessionToken: token.current, language: locale, region: "eg", includedRegionCodes: ["eg"] };
      if (typeof biasLat === "number" && typeof biasLng === "number") {
        request.locationBias = { center: { lat: biasLat, lng: biasLng }, radius: Math.min(MAX_BIAS_M, Math.max(5_000, (radiusKm ?? 50) * 1000)) };
      }
      const { suggestions } = await AutocompleteSuggestion.fetchAutocompleteSuggestions(request);
      if (signal.aborted) return null;
      const options: ComboboxOption[] = suggestions.flatMap((s) => {
        const p = s.placePrediction;
        if (!p) return [];
        return [
          {
            value: `google:${p.placeId}`,
            label: p.mainText?.text || p.text.text,
            description: p.secondaryText?.text ?? undefined,
            // Google already matched the text (typos, other scripts): never filter these out locally.
            keywords: [query],
            icon,
            data: { type: "google", prediction: p },
          },
        ];
      });
      return options.length ? { key: "google", label: groupLabel, options } : null;
    },
    [active, locale, biasLat, biasLng, radiusKm, groupLabel, icon],
  );

  const resolve = useCallback(async (option: ComboboxOption): Promise<PickedPlace | null> => {
    const prediction = option.data?.prediction as google.maps.places.PlacePrediction | undefined;
    if (!prediction) return null;
    const place = prediction.toPlace();
    await place.fetchFields({ fields: ["displayName", "location"] });
    token.current = null; // the session ends with the details call
    if (!place.location) return null;
    return { name: option.label || place.displayName || "", lat: place.location.lat(), lng: place.location.lng() };
  }, []);

  return useMemo(() => ({ active, search, resolve }), [active, search, resolve]);
}
