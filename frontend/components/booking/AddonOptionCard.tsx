"use client";

import { AppImage } from "@/components/ui/AppImage";
import type { Addon } from "@/lib/api/types";
import { Price } from "@/lib/currency";
import { cn } from "@/lib/utils";
import { Check } from "lucide-react";
import { useTranslations } from "next-intl";
import { useId } from "react";

export function AddonOptionCard({ addon, checked, onChange, compact }: { addon: Addon; checked: boolean; onChange?: (checked: boolean) => void; compact?: boolean }) {
  const t = useTranslations("booking");
  const id = useId();
  const interactive = Boolean(onChange);
  return (
    <label
      htmlFor={interactive ? id : undefined}
      className={cn(
        "relative flex gap-3 rounded-image border p-3 transition-colors",
        interactive && "cursor-pointer has-[:focus-visible]:outline has-[:focus-visible]:outline-2 has-[:focus-visible]:outline-primary-500",
        checked ? "border-2 border-primary-700 bg-primary-100/60" : "border-ink-300 bg-white",
      )}
    >
      {addon.image && (
        <span className={cn("relative shrink-0 overflow-hidden rounded-control bg-ink-100", compact ? "size-20" : "size-24")}>
          <AppImage src={addon.image} alt="" fill sizes="96px" className="object-cover" />
        </span>
      )}
      <span className="min-w-0 flex-1 pe-7">
        <span className="block font-semibold text-ink-900">{addon.name}</span>
        <span className="block text-sm text-ink-700">{addon.description}</span>
        <span className="mt-1 block">
          <Price amount={addon.price} className="text-lg font-bold text-ink-900" />{" "}
          <span className="text-xs text-ink-500">{t(`basis.${addon.pricing_basis}`)}</span>
        </span>
        {addon.required && <span className="mt-1 block text-xs font-medium text-brand-700">{t("required")}</span>}
      </span>
      {interactive && (
        <>
          <input id={id} type="checkbox" className="sr-only" checked={checked} disabled={addon.required} onChange={(e) => onChange?.(e.target.checked)} />
          <span className={cn("absolute end-3 top-3 grid size-6 place-items-center rounded-full border", checked ? "border-primary-700 bg-primary-700 text-white" : "border-ink-300 bg-white")} aria-hidden>
            {checked && <Check className="size-4" strokeWidth={3} />}
          </span>
        </>
      )}
    </label>
  );
}
