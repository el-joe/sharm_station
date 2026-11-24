"use client";

import { Badge } from "@/components/ui/Badge";
import { api, ApiError } from "@/lib/api/client";
import type { Locale, PartnerStatement } from "@/lib/api/types";
import { useAuth } from "@/lib/auth";
import { useCurrency } from "@/lib/currency";
import { parseISODate } from "@/lib/utils";
import { AlertCircle, Download, Loader2 } from "lucide-react";
import { useFormatter, useLocale, useTranslations } from "next-intl";
import { useState } from "react";
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
                    <StatementDownloads statement={s} label={period(s.period_start, s.period)} />
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

/** "YYYY-MM" of a statement row (the export route key). */
function periodKey(s: PartnerStatement): string {
  if (/^\d{4}-\d{2}$/.test(s.period)) return s.period;
  return (s.period_start || s.period).slice(0, 7);
}

/** PDF / Excel buttons: fetch with the partner token -> Blob -> download, with loading and error states. */
function StatementDownloads({ statement, label }: { statement: PartnerStatement; label: string }) {
  const t = useTranslations("partnerPanel.statements");
  const locale = useLocale() as Locale;
  const { token, signOut } = useAuth();
  const [busy, setBusy] = useState<"pdf" | "xlsx" | null>(null);
  const [error, setError] = useState<string | null>(null);
  const key = periodKey(statement);

  const download = async (format: "pdf" | "xlsx") => {
    if (!token || busy) return;
    setBusy(format);
    setError(null);
    try {
      const { blob, filename } = await api.partnerStatementFile(key, format, { locale, token });
      const url = URL.createObjectURL(blob);
      const a = document.createElement("a");
      a.href = url;
      a.download = filename;
      document.body.append(a);
      a.click();
      a.remove();
      window.setTimeout(() => URL.revokeObjectURL(url), 30_000);
    } catch (err) {
      if (err instanceof ApiError && err.status === 401) return void signOut();
      setError(err instanceof ApiError && err.status === 404 ? t("downloadMissing") : t("downloadError"));
    } finally {
      setBusy(null);
    }
  };

  const button = (format: "pdf" | "xlsx", text: string) => (
    <button
      type="button"
      onClick={() => void download(format)}
      disabled={busy !== null}
      aria-busy={busy === format || undefined}
      aria-label={t("downloadLabel", { format: text, period: label })}
      className="inline-flex h-9 items-center gap-1 rounded-control border border-ink-300 px-3 text-sm font-semibold text-partner-600 hover:bg-partner-100 disabled:cursor-wait disabled:opacity-60"
    >
      {busy === format ? <Loader2 className="size-4 animate-spin" aria-hidden /> : <Download className="size-4" aria-hidden />} {text}
    </button>
  );

  return (
    <div className="col-span-2 flex flex-col gap-1 md:col-span-1">
      <div className="flex gap-2" role="group" aria-label={t("columns.export")}>
        {button("pdf", t("pdf"))}
        {button("xlsx", t("excel"))}
      </div>
      {busy && (
        <span className="sr-only" role="status">
          {t("downloading")}
        </span>
      )}
      {error && (
        <p className="flex items-center gap-1 text-xs text-error" role="alert">
          <AlertCircle className="size-3.5 shrink-0" aria-hidden /> {error}
        </p>
      )}
    </div>
  );
}
