"use client";

import { Badge } from "@/components/ui/Badge";
import { api } from "@/lib/api/client";
import { useCurrency } from "@/lib/currency";
import { parseISODate } from "@/lib/utils";
import { Download } from "lucide-react";
import { useFormatter, useTranslations } from "next-intl";
import { PartnerData } from "./PartnerStates";
import { PartnerPageHeader } from "./shared";
import { usePartnerQuery } from "./usePartnerQuery";

/** /partner/statements (Figma 255:35614): GET /partner/statements per month with retail, commission and partner totals. */
export function PartnerStatements() {
  const t = useTranslations("partnerPanel.statements");
  const format = useFormatter();
  const { format: money } = useCurrency();
  const query = usePartnerQuery(["statements"], api.partnerStatements);
  const period = (start: string, fallback: string) => (start ? format.dateTime(parseISODate(start), { month: "long", year: "numeric" }) : fallback);
  const tone = { open: "info", paid: "success", overdue: "warning" } as const;

  return (
    <div className="flex flex-col gap-6">
      <PartnerPageHeader crumbs={[{ label: t("crumb") }]} title={t("title")} subtitle={t("subtitle")} />
      <PartnerData query={query}>
        {(rows) => {
          if (!rows.length) return <p className="rounded-card bg-white p-6 text-center text-ink-700">{t("empty")}</p>;
          const sum = (k: "retail_total" | "commission_amount" | "partner_total" | "paid" | "balance") => rows.reduce((s, r) => s + r[k], 0);
          return (
            <>
              <ul className="grid grid-cols-2 gap-3 md:grid-cols-4">
                {(
                  [
                    ["retail", sum("retail_total")],
                    ["commission", sum("commission_amount")],
                    ["total", sum("partner_total")],
                    ["balance", sum("balance")],
                  ] as const
                ).map(([k, v]) => (
                  <li key={k} className="rounded-card border border-ink-100 bg-white p-4 shadow-card">
                    <p className="text-sm text-ink-700">{t(`totals.${k}`)}</p>
                    <p className={`mt-1 text-xl font-semibold ${k === "commission" ? "text-success" : k === "balance" && v > 0 ? "text-warning" : "text-ink-900"}`}>
                      {money(v, { decimals: true })}
                    </p>
                  </li>
                ))}
              </ul>
              <ul className="flex flex-col gap-3">
                {rows.map((s) => (
                  <li key={s.id} className="grid grid-cols-2 gap-4 rounded-card border border-ink-100 bg-white p-5 shadow-card md:grid-cols-[1.3fr_repeat(5,1fr)_auto] md:items-center">
                    <div className="col-span-2 md:col-span-1">
                      <p className="font-semibold text-ink-900">{period(s.period_start, s.period)}</p>
                      <p className="text-xs text-ink-500">{t("bookingsCount", { count: s.bookings_count })}</p>
                      <Badge tone={tone[s.status]} className="mt-1">
                        {t(`status.${s.status}`)}
                      </Badge>
                    </div>
                    <Cell label={t("columns.retail")} value={money(s.retail_total, { decimals: true })} muted />
                    <Cell label={t("columns.commission")} value={money(s.commission_amount, { decimals: true })} tone="text-success" />
                    <Cell label={t("columns.total")} value={money(s.partner_total, { decimals: true })} strong />
                    <Cell label={t("columns.paid")} value={money(s.paid, { decimals: true })} />
                    <Cell label={t("columns.balance")} value={money(s.balance, { decimals: true })} strong />
                    {(s.pdf_url || s.excel_url) && (
                      <div className="col-span-2 flex gap-2 md:col-span-1" aria-label={t("columns.export")}>
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
                    )}
                  </li>
                ))}
              </ul>
              <p className="text-xs text-ink-500">{t("exportNote")}</p>
            </>
          );
        }}
      </PartnerData>
    </div>
  );
}

function Cell({ label, value, strong, muted, tone }: { label: string; value: string; strong?: boolean; muted?: boolean; tone?: string }) {
  return (
    <div>
      <p className="text-xs text-ink-500">{label}</p>
      <p className={tone ?? (strong ? "font-semibold text-ink-900" : muted ? "text-ink-500" : "text-ink-900")}>{value}</p>
    </div>
  );
}
