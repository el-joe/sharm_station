"use client";

import { TripCard } from "@/components/trips/TripCard";
import type { TripSummary } from "@/lib/api/types";
import { cn } from "@/lib/utils";
import { useTranslations } from "next-intl";
import { useId, useState } from "react";

type Filter = "all" | "single_day" | "multi_day";

export function FeaturedTrips({ trips }: { trips: TripSummary[] }) {
  const t = useTranslations("home.featured");
  const id = useId();
  const [filter, setFilter] = useState<Filter>("all");
  const visible = trips.filter((trip) => filter === "all" || (filter === "single_day" ? trip.type === "single_day" || (trip.type === "nile" && !trip.days) : trip.type === "multi_day" || (trip.days ?? 0) > 1)).slice(0, 4);
  const tabs: { key: Filter; label: string }[] = [
    { key: "all", label: t("allTab") },
    { key: "single_day", label: t("dayTrips") },
    { key: "multi_day", label: t("multiDay") },
  ];
  return (
    <>
      <div role="tablist" aria-label={t("filterLabel")} className="mb-8 flex gap-2 md:mb-12 md:gap-0 md:rounded-image md:border-b md:border-ink-300 md:bg-white md:px-6 md:shadow-card">
        {tabs.map((tab) => {
          const active = tab.key === filter;
          return (
            <button
              key={tab.key}
              role="tab"
              type="button"
              aria-selected={active}
              aria-controls={`${id}-panel`}
              onClick={() => setFilter(tab.key)}
              className={cn(
                "h-11 rounded-pill border px-4 font-semibold transition-colors md:-mb-px md:h-[52px] md:rounded-none md:border-0 md:border-b-[3px] md:px-4",
                active ? "border-primary-700 bg-primary-700 text-white md:border-primary-700 md:bg-transparent md:text-primary-700" : "border-ink-300 text-ink-700 md:border-transparent hover:text-ink-900",
              )}
            >
              {tab.label}
            </button>
          );
        })}
      </div>
      <ul id={`${id}-panel`} role="tabpanel" className="grid gap-6 sm:grid-cols-2 lg:grid-cols-4">
        {visible.map((trip) => (
          <li key={trip.slug}>
            <TripCard trip={trip} className="h-full" />
          </li>
        ))}
      </ul>
    </>
  );
}
