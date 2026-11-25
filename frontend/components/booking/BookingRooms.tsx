"use client";

import type { BookingRoom } from "@/lib/api/types";
import { useCurrency } from "@/lib/currency";
import { BedDouble } from "lucide-react";
import { useTranslations } from "next-intl";

/** BookingResource `rooms[]`: one line per room (multi-room stays and optional trip / package rooms). */
export function BookingRooms({ rooms, pricesHidden, className }: { rooms: BookingRoom[]; pricesHidden?: boolean; className?: string }) {
  const t = useTranslations("bookingRooms");
  const { format: money } = useCurrency();
  if (!rooms.length) return null;
  return (
    <section className={className ?? "rounded-card border border-ink-100 bg-white p-5 shadow-card md:p-6"} aria-labelledby="booking-rooms-title" data-testid="booking-rooms">
      <h2 id="booking-rooms-title" className="flex items-center gap-2 text-h4 text-ink-900">
        <BedDouble className="size-5 text-primary-700" aria-hidden />
        {t("title")}
      </h2>
      <ol className="mt-4 flex flex-col divide-y divide-ink-100">
        {rooms.map((r, i) => {
          const extras = r.extra_beds || r.cots ? t("extras", { beds: r.extra_beds, cots: r.cots }).replace(/^ · /, "") : "";
          return (
            <li key={i} className="flex flex-wrap items-start justify-between gap-x-4 gap-y-1 py-3">
              <div className="min-w-0">
                <p className="font-semibold text-ink-900">{t("room", { n: i + 1, name: r.name })}</p>
                <p className="text-sm text-ink-700">
                  {[r.stage_position ? t("stage", { n: r.stage_position }) : null, r.number ? t("number", { number: r.number }) : null, t("guests", { adults: r.adults, children: r.children_ages.length }), extras || null]
                    .filter(Boolean)
                    .join(" · ")}
                </p>
              </div>
              {!pricesHidden && r.total > 0 && <p className="font-semibold tabular-nums text-ink-900">{money(r.total)}</p>}
            </li>
          );
        })}
      </ol>
    </section>
  );
}
