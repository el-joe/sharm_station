"use client";

import { MapView } from "@/components/map/MapView";
import { hasCoords, type MapPlace } from "@/components/map/types";
import { buttonClasses } from "@/components/ui/Button";
import { SegmentedControl } from "@/components/ui/SegmentedControl";
import { Switch } from "@/components/ui/Switch";
import { Link, usePathname, useRouter } from "@/i18n/navigation";
import type { RoomGroup, RoomSummary } from "@/lib/api/types";
import { cn } from "@/lib/utils";
import { BedDouble, Building2, MapPin, Star } from "lucide-react";
import { useTranslations } from "next-intl";
import { useId, useMemo, useState, useTransition } from "react";
import { RoomCard } from "./RoomCard";

type View = "list" | "map";

/**
 * Accommodation results with a List / Map toggle (?view=map) and "Group by building" (?group=building, sent to the
 * API as group_by=building). Rooms that cannot be booked for the search (B2.4) come after the bookable ones, greyed
 * out, and never feed the map's prices or links.
 */
export function AccommodationResults({
  rooms,
  groups,
  nights,
  query,
  initialView,
  grouped,
}: {
  rooms: RoomSummary[];
  groups?: RoomGroup[];
  nights?: number;
  query: Record<string, string>;
  initialView: View;
  grouped: boolean;
}) {
  const t = useTranslations("accommodation");
  const router = useRouter();
  const pathname = usePathname();
  const switchId = useId();
  const [pending, startTransition] = useTransition();
  const [view, setView] = useState<View>(initialView);
  const [active, setActive] = useState<string | null>(null);

  const change = (next: View) => {
    setView(next);
    const url = new URL(window.location.href);
    if (next === "map") url.searchParams.set("view", "map");
    else url.searchParams.delete("view");
    window.history.replaceState(window.history.state, "", url);
  };

  const toggleGroup = (on: boolean) =>
    startTransition(() => {
      const params = Object.fromEntries(new URLSearchParams(window.location.search));
      if (on) params.group = "building";
      else delete params.group;
      router.push({ pathname, query: params }, { scroll: false });
    });

  const qs = useMemo(() => {
    const s = new URLSearchParams(query).toString();
    return s ? `?${s}` : "";
  }, [query]);

  const bookable = rooms.filter((r) => r.available !== false);
  const unavailable = rooms.filter((r) => r.available === false);

  // One marker per building, priced from its cheapest bookable room; the popup lists only bookable rooms.
  const places = useMemo<MapPlace[]>(() => {
    const byBuilding = new Map<string, MapPlace>();
    for (const r of rooms) {
      if (!hasCoords(r.building.lat, r.building.lng)) continue;
      const id = `building-${r.building.slug}`;
      const place = byBuilding.get(id) ?? { id, kind: "building" as const, lat: r.building.lat!, lng: r.building.lng!, title: r.building.name, subtitle: r.building.location, priceEur: null, links: [] };
      if (r.available !== false) {
        place.priceEur = Math.min(place.priceEur ?? Infinity, r.price_per_night);
        place.links!.push({ label: r.name, href: `/rooms/${r.id}${qs}`, priceEur: r.price_per_night });
      }
      byBuilding.set(id, place);
    }
    return [...byBuilding.values()].map((p) => (p.links!.length ? p : { ...p, subtitle: `${p.subtitle ? `${p.subtitle} · ` : ""}${t("groupNoneAvailable")}` }));
  }, [rooms, qs, t]);

  const unmapped = rooms.filter((r) => !hasCoords(r.building.lat, r.building.lng)).length;

  const grid = (list: RoomSummary[]) => (
    <ul className="grid gap-6 sm:grid-cols-2 lg:grid-cols-4">
      {list.map((room) => (
        <li key={room.id}>
          <RoomCard room={room} nights={nights} className="h-full" query={query} />
        </li>
      ))}
    </ul>
  );

  return (
    <div className={cn("flex flex-col gap-6 transition-opacity", pending && "opacity-60")}>
      <div className="flex flex-wrap items-center justify-between gap-4">
        <div className="flex items-center gap-3">
          <Switch id={switchId} checked={grouped} onChange={toggleGroup} describedBy={undefined} />
          <label htmlFor={switchId} className="cursor-pointer font-medium text-ink-900">
            {t("groupByBuilding")}
          </label>
        </div>
        <SegmentedControl
          label={t("viewLabel")}
          value={view}
          onChange={change}
          options={[
            { value: "list", label: t("viewList") },
            { value: "map", label: t("viewMap") },
          ]}
          className="w-full max-w-xs"
        />
      </div>
      {view === "list" ? (
        grouped && groups?.length ? (
          <div className="flex flex-col gap-10">
            {groups.map((g) => (
              <BuildingGroup key={g.building.slug} group={g} query={query}>
                {grid(g.rooms)}
              </BuildingGroup>
            ))}
          </div>
        ) : (
          <div className="flex flex-col gap-10">
            {bookable.length > 0 && grid(bookable)}
            {unavailable.length > 0 && (
              <section aria-labelledby="rooms-unavailable-title" className="flex flex-col gap-4">
                <div>
                  <h2 id="rooms-unavailable-title" className="text-h4 text-ink-900">{t("unavailableTitle")}</h2>
                  <p className="mt-1 text-sm text-ink-700">{t("unavailableText")}</p>
                </div>
                {grid(unavailable)}
              </section>
            )}
          </div>
        )
      ) : (
        <div className="grid gap-6 lg:grid-cols-[minmax(0,380px)_minmax(0,1fr)]">
          <div className="order-2 lg:order-1">
            <h2 className="sr-only">{t("mapListTitle")}</h2>
            <ul className="flex flex-col gap-4 lg:max-h-[calc(100vh-180px)] lg:overflow-y-auto lg:pe-2">
              {[...bookable, ...unavailable].map((room) => {
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

function BuildingGroup({ group, query, children }: { group: RoomGroup; query: Record<string, string>; children: React.ReactNode }) {
  const t = useTranslations("accommodation");
  const tr = useTranslations("room");
  const b = group.building;
  const headingId = `group-${b.slug}`;
  return (
    <section aria-labelledby={headingId} className="flex flex-col gap-4" data-testid="building-group">
      <div className="flex flex-wrap items-end justify-between gap-3 border-b border-ink-100 pb-3">
        <div>
          <h2 id={headingId} className="flex items-center gap-2 text-h4 text-ink-900">
            <Building2 className="size-5 shrink-0 text-primary-700" aria-hidden />
            <Link href={`/buildings/${b.slug}`} className="hover:underline">{b.name}</Link>
            <span className="inline-flex" role="img" aria-label={tr("stars", { count: b.stars })}>
              {Array.from({ length: b.stars }, (_, i) => (
                <Star key={i} className="size-4 fill-gold-500 text-gold-500" aria-hidden />
              ))}
            </span>
          </h2>
          <p className="mt-1 flex flex-wrap items-center gap-x-3 gap-y-1 text-sm text-ink-700">
            <span className="inline-flex items-center gap-1"><MapPin className="size-4" aria-hidden />{b.location}</span>
            <span className={cn("inline-flex items-center gap-1 font-medium", group.available_count === 0 ? "text-error" : "text-success")}>
              <BedDouble className="size-4" aria-hidden />
              {group.available_count === 0 ? t("groupNoneAvailable") : t("groupAvailable", { available: group.available_count, total: group.rooms.length })}
            </span>
          </p>
        </div>
        {group.available_count > 0 && (
          <Link href={{ pathname: `/buildings/${b.slug}`, query, hash: "book-rooms" }} className={buttonClasses("outline", "sm", "h-11")}>
            {t("bookSeveral")}
          </Link>
        )}
      </div>
      {children}
    </section>
  );
}
