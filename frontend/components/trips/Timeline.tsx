import type { TimelineDay } from "@/lib/api/types";
import { cn } from "@/lib/utils";
import { Car, ChevronDown, Sun } from "lucide-react";
import { useTranslations } from "next-intl";

/** Single-day: hour by hour. Multi-day: "Day 1", "Day 2" without calendar dates (SRS 3.3). */
export function Timeline({ days }: { days: TimelineDay[] }) {
  const t = useTranslations("trip");
  const multi = days.length > 1 || days.some((d) => d.day_number > 1);
  return (
    <div className="flex flex-col gap-8">
      {days.map((day) => (
        <div key={day.day_number}>
          {multi && (
            <h3 className="mb-4 flex items-center gap-3 text-lg font-semibold text-ink-900">
              <span className="rounded-pill bg-primary-700 px-3 py-1 text-sm text-white">{t("day", { n: day.day_number })}</span>
              {day.title}
            </h3>
          )}
          <ol className="relative">
            {day.items.map((item, i) => (
              <li key={i} className="grid grid-cols-[52px_36px_1fr] gap-x-3 md:grid-cols-[64px_36px_1fr]">
                <span className="pt-1.5 text-end font-semibold text-ink-700 tabular-nums">{item.time_from ?? ""}</span>
                <span className="relative flex justify-center">
                  <span className="z-10 grid size-9 place-items-center rounded-full bg-primary-100 text-primary-700" aria-hidden>
                    {item.transfer_included ? <Car className="size-4" /> : <Sun className="size-4" />}
                  </span>
                  {i < day.items.length - 1 && <span className="absolute bottom-0 top-9 w-0.5 bg-ink-300" aria-hidden />}
                </span>
                <details open className={cn("group pb-6", i === day.items.length - 1 && "pb-0")}>
                  <summary className="flex cursor-pointer list-none items-start justify-between gap-3 pt-1.5 [&::-webkit-details-marker]:hidden">
                    <span className="flex flex-wrap items-center gap-2">
                      <span className="font-semibold text-ink-900">{item.title}</span>
                      {item.transfer_included && (
                        <span className="inline-flex items-center gap-1.5 rounded-pill bg-primary-100 px-3 py-1 text-sm text-ink-900">
                          <Car className="size-4" aria-hidden /> {t("transferIncluded")}
                          <span className="text-xs text-ink-700">{t("noExtraCharge")}</span>
                        </span>
                      )}
                    </span>
                    <ChevronDown className="mt-1 size-4 shrink-0 text-ink-700 transition-transform group-open:rotate-180" aria-hidden />
                  </summary>
                  <p className="mt-2 text-sm text-ink-700">{item.description}</p>
                  {item.place && <p className="mt-1 text-xs text-ink-500">{item.place}</p>}
                </details>
              </li>
            ))}
          </ol>
        </div>
      ))}
    </div>
  );
}
