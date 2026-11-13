"use client";

import { Badge } from "@/components/ui/Badge";
import { api } from "@/lib/api/client";
import { useCurrency } from "@/lib/currency";
import { parseISODate } from "@/lib/utils";
import { Download } from "lucide-react";
import { useFormatter, useTranslations } from "next-intl";
import { PartnerData } from "./PartnerStates";
import { usePartnerQuery } from "./usePartnerQuery";

/** /partner/statements: GET /partner/statements (per period, PDF/Excel export links). */
export function PartnerStatements() {
  const t = useTranslations("partnerPanel.statements");
  const format = useFormatter();
  const { format: money } = useCurrency();
  const query = usePartnerQuery(["statements"], api.partnerStatements);
  const period = (start: string, fallback: string) => (start ? format.dateTime(parseISODate(start), { month: "long", year: "numeric" }) : fallback);
  const tone = { open: "info", paid: "success", overdue: "warning" } as const;

  return (
    <div className="flex flex-col gap-6">
      <div>
        <h1 className="text-h2 text-ink-900">{t("title")}</h1>
        <p className="mt-1 text-ink-700">{t("subtitle")}</p>
      </div>
      <PartnerData query={query}>
        {(rows) =>
          rows.length ? (
            <ul className="flex flex-col gap-3">
              {rows.map((s) => (
                <li key={s.id} className="grid gap-4 rounded-card border border-ink-100 bg-white p-5 shadow-card md:grid-cols-[1.2fr_repeat(4,1fr)_auto] md:items-center">
                  <div>
                    <p className="font-semibold text-ink-900">{period(s.period_start, s.period)}</p>
                    <Badge tone={tone[s.status]} className="mt-1">{t(`status.${s.status}`)}</Badge>
                  </div>
                  <Cell label={t("columns.bookings")} value={String(s.bookings_count)} />
                  <Cell label={t("columns.total")} value={money(s.partner_total)} />
                  <Cell label={t("columns.paid")} value={money(s.paid)} />
                  <Cell label={t("columns.balance")} value={money(s.balance)} strong />
                  <div className="flex gap-2" aria-label={t("columns.export")}>
                    {s.pdf_url && (
                      <a href={s.pdf_url} className="inline-flex h-9 items-center gap-1 rounded-control border border-ink-300 px-3 text-sm font-semibold text-partner-600 hover:bg-partner-100">
                        <Download className="size-4" aria-hidden /> {t("pdf")}
                      </a>
                    )}
                    {s.excel_url && (
                      <a href={s.excel_url} className="inline-flex h-9 items-center gap-1 rounded-control border border-ink-300 px-3 text-sm font-semibold text-partner-600 hover:bg-partner-100">
                        <Download className="size-4" aria-hidden /> {t("excel")}
                      </a>
                    )}
                  </div>
                </li>
              ))}
            </ul>
          ) : (
            <p className="rounded-card bg-sand-50 p-6 text-center text-ink-700">{t("empty")}</p>
          )
        }
      </PartnerData>
    </div>
  );
}

function Cell({ label, value, strong }: { label: string; value: string; strong?: boolean }) {
  return (
    <div>
      <p className="text-xs text-ink-500">{label}</p>
      <p className={strong ? "font-semibold text-ink-900" : "text-ink-900"}>{value}</p>
    </div>
  );
}
