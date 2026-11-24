"use client";

import { MapView } from "@/components/map/MapView";
import { hasCoords, type MapPlace } from "@/components/map/types";
import { SegmentedControl } from "@/components/ui/SegmentedControl";
import type { RoomSummary } from "@/lib/api/types";
import { cn } from "@/lib/utils";
import { useTranslations } from "next-intl";
import { useMemo, useState } from "react";
import { RoomCard } from "./RoomCard";

type View = "list" | "map";

/** Accommodation results with a List / Map toggle (the choice is kept in ?view= so it can be shared). */
export function AccommodationResults({ rooms, nights, query, initialView }: { rooms: RoomSummary[]; nights?: number; query: Record<string, string>; initialView: View }) {
  const t = useTranslations("accommodation");
  const [view, setView] = useState<View>(initialView);
  const [active, setActive] = useState<string | null>(null);

  const change = (next: View) => {
    setView(next);
    const url = new URL(window.location.href);
    if (next === "map") url.searchParams.set("view", "map");
    else url.searchParams.delete("view");
    window.history.replaceState(window.history.state, "", url);
  };

  const qs = useMemo(() => {
    const s = new URLSearchParams(query).toString();
    return s ? `?${s}` : "";
  }, [query]);

  // One marker per building, priced from its cheapest room; the popup lists every room.
  const places = useMemo<MapPlace[]>(() => {
    const byBuilding = new Map<string, MapPlace>();
    for (const r of rooms) {
      if (!hasCoords(r.building.lat, r.building.lng)) continue;
      const id = `building-${r.building.slug}`;
      const place = byBuilding.get(id) ?? { id, kind: "building" as const, lat: r.building.lat!, lng: r.building.lng!, title: r.building.name, subtitle: r.building.location, priceEur: r.price_per_night, links: [] };
      place.priceEur = Math.min(place.priceEur ?? Infinity, r.price_per_night);
      place.links!.push({ label: r.name, href: `/rooms/${r.id}${qs}`, priceEur: r.price_per_night });
      byBuilding.set(id, place);
    }
    return [...byBuilding.values()];
  }, [rooms, qs]);

  const unmapped = rooms.filter((r) => !hasCoords(r.building.lat, r.building.lng)).length;

  return (
    <div className="flex flex-col gap-6">
      <SegmentedControl
        label={t("viewLabel")}
        value={view}
        onChange={change}
        options={[
          { value: "list", label: t("viewList") },
          { value: "map", label: t("viewMap") },
        ]}
        className="w-full max-w-xs self-end"
      />
      {view === "list" ? (
        <ul className="grid gap-6 sm:grid-cols-2 lg:grid-cols-4">
          {rooms.map((room) => (
            <li key={room.id}>
              <RoomCard room={room} nights={nights} className="h-full" query={query} />
            </li>
          ))}
        </ul>
      ) : (
        <div className="grid gap-6 lg:grid-cols-[minmax(0,380px)_minmax(0,1fr)]">
          <div className="order-2 lg:order-1">
            <h2 className="sr-only">{t("mapListTitle")}</h2>
            <ul className="flex flex-col gap-4 lg:max-h-[calc(100vh-180px)] lg:overflow-y-auto lg:pe-2">
              {rooms.map((room) => {
                const id = `building-${room.building.slug}`;
                return (
                  <li key={room.id} onMouseEnter={() => setActive(id)} onMouseLeave={() => setActive(null)} onFocus={() => setActive(id)} onBlur={() => setActive(null)}>
                    <RoomCard room={room} nights={nights} query={query} className={cn(active === id && "ring-2 ring-primary-500")} />
                  </li>
                );
              })}
            </ul>
          </div>
          <div className="order-1 lg:sticky lg:top-32 lg:order-2 lg:self-start">
            <MapView places={places} label={t("mapLabel")} list="sr-only" activeId={active} className="h-[60vh] min-h-[320px] md:h-[60vh] lg:h-[calc(100vh-180px)]" />
            {unmapped > 0 && <p className="mt-2 text-sm text-ink-500">{t("mapUnmapped", { count: unmapped })}</p>}
          </div>
        </div>
      )}
    </div>
  );
}
