"use client";

import { QuantityStepper } from "@/components/ui/QuantityStepper";
import { Users } from "lucide-react";
import { useTranslations } from "next-intl";
import { useEffect, useId, useRef, useState } from "react";

/** Field that opens a small popover with adults/children steppers. */
export function TravellersPicker({
  adults,
  childCount,
  onChange,
  label,
}: {
  adults: number;
  childCount: number;
  onChange: (adults: number, children: number) => void;
  label: string;
}) {
  const t = useTranslations("search");
  const tb = useTranslations("booking");
  const [open, setOpen] = useState(false);
  const ref = useRef<HTMLDivElement>(null);
  const id = useId();

  useEffect(() => {
    if (!open) return;
    const onDoc = (e: MouseEvent) => {
      if (ref.current && !ref.current.contains(e.target as Node)) setOpen(false);
    };
    const onKey = (e: KeyboardEvent) => e.key === "Escape" && setOpen(false);
    document.addEventListener("mousedown", onDoc);
    document.addEventListener("keydown", onKey);
    return () => {
      document.removeEventListener("mousedown", onDoc);
      document.removeEventListener("keydown", onKey);
    };
  }, [open]);

  const summary = t("travellersSummary", { adults, children: childCount });
  return (
    <div ref={ref} className="relative flex flex-col gap-1.5">
      <span id={`${id}-label`} className="text-xs font-medium uppercase tracking-[0.06em] text-ink-500">
        {label}
      </span>
      <button
        type="button"
        aria-labelledby={`${id}-label ${id}-value`}
        aria-expanded={open}
        aria-controls={`${id}-panel`}
        onClick={() => setOpen((o) => !o)}
        className="flex h-14 w-full items-center gap-3 rounded-image border border-ink-300 bg-ink-100 px-4 text-start hover:border-ink-500"
      >
        <span className="grid size-8 place-items-center rounded-full bg-brand-100 text-brand-700">
          <Users className="size-4" aria-hidden />
        </span>
        <span id={`${id}-value`} className="flex-1 text-base text-ink-900">
          {summary}
        </span>
      </button>
      {open && (
        <div id={`${id}-panel`} className="absolute inset-x-0 top-full z-30 mt-2 flex flex-col gap-4 rounded-card border border-ink-100 bg-white p-4 shadow-raised">
          <div className="flex items-center justify-between gap-4">
            <div>
              <p className="font-medium text-ink-900">{tb("adults")}</p>
              <p className="text-sm text-ink-500">{tb("adultsHint")}</p>
            </div>
            <QuantityStepper value={adults} min={1} max={16} onChange={(v) => onChange(v, childCount)} label={tb("adults")} decreaseLabel={tb("decrease", { item: tb("adults") })} increaseLabel={tb("increase", { item: tb("adults") })} size="sm" />
          </div>
          <div className="flex items-center justify-between gap-4">
            <div>
              <p className="font-medium text-ink-900">{tb("children")}</p>
              <p className="text-sm text-ink-500">{tb("childrenHint")}</p>
            </div>
            <QuantityStepper value={childCount} min={0} max={10} onChange={(v) => onChange(adults, v)} label={tb("children")} decreaseLabel={tb("decrease", { item: tb("children") })} increaseLabel={tb("increase", { item: tb("children") })} size="sm" />
          </div>
          <button type="button" onClick={() => setOpen(false)} className="self-end font-semibold text-primary-700 hover:underline">
            {t("done")}
          </button>
        </div>
      )}
    </div>
  );
}
