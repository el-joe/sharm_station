"use client";

import type { ComboboxGroup, ComboboxOption } from "@/components/ui/Combobox";
import { api } from "@/lib/api/client";
import type { DestinationSummary, Locale, SuggestGroup, SuggestItem, SuggestScope, TransferHub } from "@/lib/api/types";
import { Building2, Compass, Luggage, MapPin, Plane, Ship, Waves } from "lucide-react";
import { useLocale, useTranslations } from "next-intl";
import { useCallback, useMemo } from "react";

export interface HotelSuggestion {
  slug: string;
  name: string;
  stars: number;
  city: string;
}

const HUB_TYPES = ["airport", "port", "city"] as const;
type HubType = (typeof HUB_TYPES)[number] | "other";

function hubKind(type?: string): HubType {
  return HUB_TYPES.includes(type as (typeof HUB_TYPES)[number]) ? (type as HubType) : "other";
}

function hubIcon(type: HubType) {
  return type === "port" ? <Ship aria-hidden /> : type === "airport" ? <Plane aria-hidden /> : <MapPin aria-hidden />;
}

/**
 * Builds the grouped suggestions for every From / To / Destination field:
 * instant local groups (from data already on the page) and `remote(scope)` loaders
 * that query GET /search/suggest while the user types.
 */
export function useLocationGroups() {
  const t = useTranslations("search.suggest");
  const locale = useLocale() as Locale;
  const country = t("country");
  const where = useCallback((...parts: (string | undefined | null)[]) => [...parts, country].filter(Boolean).join(", "), [country]);

  const fromItem = useCallback(
    (item: SuggestItem): ComboboxOption => {
      const base = { value: item.value, label: item.label, data: { type: item.type, ...item.meta } };
      switch (item.type) {
        case "destination":
          return { ...base, description: `${t("tripsCount", { count: item.meta.trips_count ?? 0 })} · ${item.location}`, icon: <MapPin aria-hidden /> };
        case "hotel":
          return { ...base, value: `hotel:${item.value}`, description: item.location, icon: <Building2 aria-hidden /> };
        case "trip":
          return { ...base, value: `trip:${item.value}`, description: item.location, icon: item.meta.trip_type === "nile" ? <Waves aria-hidden /> : <Compass aria-hidden /> };
        case "package":
          return { ...base, value: `package:${item.value}`, description: item.location, icon: <Luggage aria-hidden /> };
        case "hub": {
          const kind = hubKind(item.meta.hub_type);
          return { ...base, description: `${t(`hubType.${kind}`)} · ${item.location}`, icon: hubIcon(kind) };
        }
        case "place":
          return { ...base, description: item.location, badge: item.meta.fixed_price ? t("fixedPrice") : undefined, icon: <MapPin aria-hidden /> };
      }
    },
    [t],
  );

  const toGroups = useCallback(
    (groups: SuggestGroup[]): ComboboxGroup[] =>
      groups.map((g) => ({ key: g.key, label: t(g.key === "hubs" ? "airports" : g.key === "places" ? "places" : g.key), options: g.items.map(fromItem) })),
    [t, fromItem],
  );

  return useMemo(
    () => ({
      destinations: (destinations: DestinationSummary[], mode: "trips" | "stays" = "trips"): ComboboxGroup => ({
        key: "destinations",
        label: t("destinations"),
        options: destinations.map((d) => ({
          value: d.slug,
          label: d.name,
          description: `${mode === "stays" ? t("staysIn") : t("tripsCount", { count: d.trips_count ?? 0 })} · ${country}`,
          icon: <MapPin aria-hidden />,
          data: { type: "destination" },
        })),
      }),

      hotels: (hotels: HotelSuggestion[]): ComboboxGroup => ({
        key: "hotels",
        label: t("hotels"),
        options: hotels.map((h) => ({
          value: `hotel:${h.slug}`,
          label: h.name,
          description: where(h.city),
          icon: <Building2 aria-hidden />,
          data: { type: "hotel", stars: h.stars },
        })),
      }),

      hubs: (hubs: TransferHub[]): ComboboxGroup => ({
        key: "hubs",
        label: t("airports"),
        options: hubs.map((h) => {
          const kind = hubKind(h.type);
          return {
            value: h.code,
            label: h.name,
            description: `${t(`hubType.${kind}`)} · ${where(h.city)}`,
            icon: hubIcon(kind),
            data: { type: "hub" },
          };
        }),
      }),

      places: (hub: TransferHub | undefined): ComboboxGroup => ({
        key: "places",
        label: t("places"),
        options: (hub?.places ?? []).map((p) => ({
          value: p.name,
          label: p.name,
          description: where(hub?.city),
          badge: t("fixedPrice"),
          icon: <MapPin aria-hidden />,
          data: { type: "place", lat: p.lat, lng: p.lng },
        })),
      }),

      /** Server search loader for a field; pass to <Combobox loadGroups>. */
      remote:
        (scope: SuggestScope, hubId?: number) =>
        async (query: string, signal: AbortSignal): Promise<ComboboxGroup[]> =>
          toGroups(await api.suggest(scope, query, { locale, signal }, hubId)),

      labels: {
        useTyped: (text: string) => t("useTyped", { text }),
        noMatches: t("noMatches"),
        clear: t("clear"),
        searching: t("searching"),
        fromPlaceholder: t("fromPlaceholder"),
      },
    }),
    [t, where, country, locale, toGroups],
  );
}
