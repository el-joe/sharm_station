import { Price } from "@/lib/currency";
import { cn } from "@/lib/utils";
import { BadgePercent } from "lucide-react";
import { useTranslations } from "next-intl";

/** "Partner price" pill (indigo #4b45b8), optionally with the struck-through retail price. */
export function PartnerPricePill({ amount, retail, className }: { amount: number; retail?: number | null; className?: string }) {
  const t = useTranslations("partnerPrice");
  return (
    <span className={cn("inline-flex flex-wrap items-center gap-x-2 gap-y-0.5", className)}>
      <span className="inline-flex h-7 items-center gap-1 rounded-pill bg-partner-100 px-3 text-xs font-semibold text-partner-600">
        <BadgePercent className="size-4" aria-hidden />
        <span className="sr-only">{t("label")}: </span>
        <span aria-hidden>{t("label")}</span>
        <Price amount={amount} className="text-sm" />
      </span>
      {retail != null && retail > amount && (
        <span className="text-xs text-ink-500">
          <span className="sr-only">{t("retailLabel")} </span>
          <s aria-hidden>
            <Price amount={retail} />
          </s>
        </span>
      )}
    </span>
  );
}
