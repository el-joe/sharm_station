"use client";

import type { SeasonPrice } from "@/lib/api/types";
import { useCurrency } from "@/lib/currency";
import { useToday } from "@/lib/hooks";
import { seasonContains } from "@/lib/pricing";
import { cn, parseISODate } from "@/lib/utils";
import { useFormatter, useTranslations } from "next-intl";

/**
 * Figma SeasonPriceTable (227:21007), SRS 3.8: season, dates, price; the season
 * containing today (or the selected travel date) is highlighted with "Now".
 */
export function SeasonPriceTable({
  seasons,
  unit,
  note,
  highlightDate,
  priceKey = "price",
}: {
  seasons: SeasonPrice[];
  unit: "person" | "night" | "unit";
  note?: string;
  highlightDate?: string | null;
  priceKey?: "price" | "price_per_night";
}) {
  const t = useTranslations("seasons");
  const format = useFormatter();
  const { format: money } = useCurrency();
  const today = useToday();
  const ref = highlightDate || today;
  const dm = (iso: string) => format.dateTime(parseISODate(iso), { day: "numeric", month: "short" });
  const sorted = [...seasons].filter((s) => s.is_active).sort((a, b) => a.starts_on.localeCompare(b.starts_on));

  return (
    <section aria-labelledby="seasons-title" className="overflow-hidden rounded-card border border-ink-100 bg-white">
      <div className="bg-sand-50 px-5 py-4">
        <h2 id="seasons-title" className="text-lg font-semibold text-ink-900">{t("title")}</h2>
        <p className="text-sm text-ink-700">{t(`subtitle.${unit}`)}</p>
      </div>
      <div className="overflow-x-auto">
        <table className="w-full min-w-[420px] text-start text-sm">
          <caption className="sr-only">{t("title")}</caption>
          <thead>
            <tr className="border-b border-ink-100 text-xs text-ink-500">
              <th scope="col" className="px-5 py-3 text-start font-medium">{t("season")}</th>
              <th scope="col" className="px-3 py-3 text-start font-medium">{t("from")}</th>
              <th scope="col" className="px-3 py-3 text-start font-medium">{t("to")}</th>
              <th scope="col" className="px-5 py-3 text-end font-medium">{t("price")}</th>
            </tr>
          </thead>
          <tbody>
            {sorted.map((s) => {
              const current = ref ? seasonContains(s, parseISODate(ref)) : false;
              const price = (priceKey === "price_per_night" ? s.price_per_night : null) ?? s.price;
              return (
                <tr key={s.id} className={cn("border-b border-ink-100 last:border-0", current && "bg-primary-100")} aria-current={current ? "true" : undefined}>
                  <th scope="row" className="px-5 py-3 text-start font-normal text-ink-900">
                    <span className="inline-flex flex-wrap items-center gap-2">
                      {s.name}
                      {current && (
                        <span className="rounded-pill bg-primary-700 px-2 py-0.5 text-[11px] font-semibold text-white">
                          {highlightDate ? t("selected") : t("now")}
                        </span>
                      )}
                    </span>
                  </th>
                  <td className="px-3 py-3 text-ink-900">{dm(s.starts_on)}</td>
                  <td className="px-3 py-3 text-ink-900">{dm(s.ends_on)}</td>
                  <td className="px-5 py-3 text-end font-bold text-primary-700">{money(price)}</td>
                </tr>
              );
            })}
          </tbody>
        </table>
      </div>
      {note && <p className="border-t border-ink-100 px-5 py-3 text-xs text-ink-500">{note}</p>}
    </section>
  );
}
