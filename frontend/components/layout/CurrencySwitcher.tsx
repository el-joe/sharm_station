"use client";

import { useCurrency } from "@/lib/currency";
import type { CurrencyCode } from "@/lib/api/types";
import { cn } from "@/lib/utils";
import { useTranslations } from "next-intl";

export function CurrencySwitcher({ className, tone = "light" }: { className?: string; tone?: "light" | "dark" }) {
  const t = useTranslations("header");
  const { currency, currencies, setCurrency } = useCurrency();
  return (
    <label className={cn("relative inline-flex h-9 items-center rounded-pill px-3 text-sm font-semibold", tone === "light" ? "bg-white text-ink-900" : "bg-white/10 text-white", className)}>
      <span className="sr-only">{t("currency")}</span>
      <select
        value={currency}
        onChange={(e) => setCurrency(e.target.value as CurrencyCode)}
        className="cursor-pointer appearance-none bg-transparent focus:outline-none [&>option]:text-ink-900"
      >
        {currencies.map((c) => (
          <option key={c.code} value={c.code}>
            {c.code} {c.symbol}
          </option>
        ))}
      </select>
    </label>
  );
}
