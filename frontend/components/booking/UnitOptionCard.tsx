"use client";

import { QuantityStepper } from "@/components/ui/QuantityStepper";
import type { UnitOption } from "@/lib/api/types";
import { Price } from "@/lib/currency";
import { cn } from "@/lib/utils";
import { CarFront } from "lucide-react";
import { useTranslations } from "next-intl";

/** Figma UnitOptionCard (227:20894): name, seats, price per unit, QuantityStepper. */
export function UnitOptionCard({
  option,
  price,
  quantity,
  onChange,
  unitLabel,
}: {
  option: UnitOption;
  price: number;
  quantity: number;
  onChange: (qty: number) => void;
  unitLabel: string;
}) {
  const t = useTranslations("booking");
  const selected = quantity > 0;
  return (
    <div className={cn("flex items-center gap-3 rounded-image border p-3 transition-colors", selected ? "border-2 border-primary-700 bg-primary-100/60" : "border-ink-300 bg-white")}>
      <span className="grid size-10 shrink-0 place-items-center rounded-control bg-brand-100 text-ink-900" aria-hidden>
        <CarFront className="size-5" />
      </span>
      <div className="min-w-0 flex-1">
        <p className="font-semibold text-ink-900">{option.name}</p>
        <p className="text-xs text-ink-500">{t("seatsPerUnit", { count: option.seats, unit: unitLabel })}</p>
        <p className="text-sm">
          <Price amount={price} className="font-bold text-ink-900" /> <span className="text-xs text-ink-500">{t("perUnit", { unit: unitLabel })}</span>
        </p>
        {!option.children_allowed && <p className="text-xs text-ink-500">{t("adultsOnly")}</p>}
      </div>
      <QuantityStepper
        value={quantity}
        onChange={onChange}
        min={0}
        max={option.max_quantity}
        size="sm"
        label={t("quantityOf", { name: option.name })}
        decreaseLabel={t("decrease", { item: option.name })}
        increaseLabel={t("increase", { item: option.name })}
      />
    </div>
  );
}
