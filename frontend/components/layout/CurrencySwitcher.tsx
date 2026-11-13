"use client";

import { SelectMenu } from "@/components/ui/SelectMenu";
import type { CurrencyCode } from "@/lib/api/types";
import { useCurrency } from "@/lib/currency";
import { cn } from "@/lib/utils";
import { useTranslations } from "next-intl";

const KNOWN = ["EUR", "USD", "GBP", "EGP"] as const;

/** Header currency picker: compact "EUR €" pill, menu with symbol badge and full currency name. */
export function CurrencySwitcher({ className, tone = "light" }: { className?: string; tone?: "light" | "dark" }) {
  const t = useTranslations("header");
  const { currency, currencies, setCurrency } = useCurrency();
  const symbol = (code: string) => currencies.find((c) => c.code === code)?.symbol ?? "";

  return (
    <SelectMenu
      aria-label={t("currency")}
      appearance="pill"
      align="end"
      menuMinWidth={220}
      value={currency}
      onChange={(e) => setCurrency(e.target.value as CurrencyCode)}
      renderValue={(o) => `${o.value} ${symbol(o.value)}`}
      renderOptionPrefix={(o) => (
        <span className="grid h-7 min-w-9 place-items-center rounded-control bg-ink-100 px-1.5 text-xs font-bold text-ink-700">{symbol(o.value) || o.value}</span>
      )}
      className={cn(tone === "light" ? "bg-white text-ink-900 hover:bg-ink-100" : "bg-white/10 text-white hover:bg-white/20", className)}
    >
      {currencies.map((c) => (
        <option key={c.code} value={c.code}>
          {KNOWN.includes(c.code as (typeof KNOWN)[number]) ? `${c.code} · ${t(`currencyNames.${c.code as (typeof KNOWN)[number]}`)}` : c.code}
        </option>
      ))}
    </SelectMenu>
  );
}
