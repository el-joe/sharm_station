"use client";

import { buttonClasses } from "@/components/ui/Button";
import { Link } from "@/i18n/navigation";
import { api } from "@/lib/api/client";
import { useAuth } from "@/lib/auth";
import { useCurrency } from "@/lib/currency";
import { useToday } from "@/lib/hooks";
import { parseISODate } from "@/lib/utils";
import { AlertTriangle, CalendarCheck, CheckCircle2, FileClock, PiggyBank, TrendingUp, Wallet } from "lucide-react";
import { useFormatter, useTranslations } from "next-intl";
import { PartnerBookingsTable } from "./PartnerBookings";
import { PartnerData } from "./PartnerStates";
import { PartnerPageHeader, useCommissionSource } from "./shared";
import { usePartnerQuery } from "./usePartnerQuery";

/** /partner dashboard (Figma 254:35008): KPIs, commission per type, actions needed, recent bookings. */
export function PartnerDashboard() {
  const t = useTranslations("partnerPanel.dashboard");
  const ts = useTranslations("partnerPanel.services");
  const tc = useTranslations("partnerPanel.commission");
  const { user } = useAuth();
  const { format } = useCurrency();
  const formatter = useFormatter();
  const source = useCommissionSource();
  const query = usePartnerQuery(["dashboard"], api.partnerDashboard);
  const today = useToday();
  const first = user?.name?.split(" ")[0] ?? "";

  return (
    <div className="flex flex-col gap-6">
      <PartnerPageHeader
        crumbs={[{ label: t("crumb") }]}
        title={t("title", { name: first || "none" })}
        subtitle={t("subtitle", { company: user?.partner?.company ?? "", month: today ? formatter.dateTime(parseISODate(today), { month: "long", year: "numeric" }) : "" })}
        actions={
          <Link href="/partner/new" className={buttonClasses("partner", "md")}>
            {t("startBooking")}
          </Link>
        }
      />
      <PartnerData query={query} skeleton="h-96">
        {(d) => {
          const kpis = [
            { key: "bookings", value: String(d.kpis.bookings_month), Icon: CalendarCheck },
            { key: "sales", value: format(d.kpis.sales_month, { decimals: true }), Icon: TrendingUp },
            { key: "commission", value: format(d.kpis.commission_earned_month, { decimals: true }), Icon: PiggyBank },
            { key: "balance", value: format(d.kpis.balance_due, { decimals: true }), Icon: Wallet },
          ] as const;
          const actions = [
            ...(d.kpis.balance_due > 0 ? [{ key: "balance", Icon: AlertTriangle, text: t("actions.balance", { amount: format(d.kpis.balance_due, { decimals: true }) }) }] : []),
            ...(d.kpis.pending_proofs > 0 ? [{ key: "proofs", Icon: FileClock, text: t("actions.proofs", { count: d.kpis.pending_proofs }) }] : []),
          ];
          return (
            <>
              <ul className="grid grid-cols-2 gap-3 xl:grid-cols-4 xl:gap-4">
                {kpis.map(({ key, value, Icon }) => (
                  <li key={key} className="rounded-card border border-ink-100 bg-white p-4 shadow-card md:p-5">
                    <p className="flex items-center gap-2 text-sm text-ink-700">
                      <span className="grid size-9 shrink-0 place-items-center rounded-full bg-partner-100 text-partner-600">
                        <Icon className="size-5" aria-hidden />
                      </span>
                      {t(`kpis.${key}`)}
                    </p>
                    <p className="mt-3 text-2xl font-semibold text-ink-900 md:text-[28px] md:leading-9">{value}</p>
                  </li>
                ))}
              </ul>

              <div className="grid gap-6 xl:grid-cols-[minmax(0,1fr)_320px]">
                <section aria-labelledby="commission-title" className="rounded-card border border-ink-100 bg-white p-5 shadow-card">
                  <h2 id="commission-title" className="text-h4 text-ink-900">
                    {t("commissionTitle")}
                  </h2>
                  <p className="mt-1 text-sm text-ink-500">{t("commissionText")}</p>
                  <ul className="mt-4 grid grid-cols-2 gap-3 md:grid-cols-3">
                    {d.commissions.map((c) => (
                      <li key={c.category} className="rounded-image bg-partner-100 p-3">
                        <p className="text-sm text-ink-700">{ts(c.category)}</p>
                        <p className="text-2xl font-bold text-partner-600">{c.percent}%</p>
                        <p className="text-xs text-ink-500">{source(c.source)}</p>
                      </li>
                    ))}
                    <li className="rounded-image bg-ink-100 p-3">
                      <p className="text-sm text-ink-700">{ts("accommodation")}</p>
                      <p className="font-semibold text-ink-700">{tc("none")}</p>
                    </li>
                  </ul>
                </section>
                <section aria-labelledby="actions-title" className="rounded-card border border-ink-100 bg-white p-5 shadow-card">
                  <h2 id="actions-title" className="text-h4 text-ink-900">
                    {t("actions.title")}
                  </h2>
                  {actions.length ? (
                    <ul className="mt-3 flex flex-col gap-2">
                      {actions.map(({ key, Icon, text }) => (
                        <li key={key} className="flex items-start gap-2 rounded-image bg-warning-100 p-3 text-sm font-semibold text-ink-900">
                          <Icon className="mt-0.5 size-4 shrink-0 text-warning" aria-hidden /> {text}
                        </li>
                      ))}
                    </ul>
                  ) : (
                    <p className="mt-3 flex items-center gap-2 text-sm text-ink-700">
                      <CheckCircle2 className="size-4 text-success" aria-hidden /> {t("actions.none")}
                    </p>
                  )}
                  <Link href="/partner/bookings" className={buttonClasses("outline", "md", "mt-4 w-full")}>
                    {t("actions.open")}
                  </Link>
                </section>
              </div>

              <section aria-labelledby="recent-title" className="rounded-card border border-ink-100 bg-white p-5 shadow-card">
                <div className="mb-4 flex items-center justify-between gap-4">
                  <h2 id="recent-title" className="text-h4 text-ink-900">
                    {t("recent")}
                  </h2>
                  <Link href="/partner/bookings" className="font-semibold text-partner-600 hover:underline">
                    {t("viewAll")}
                  </Link>
                </div>
                {d.recent_bookings.length ? <PartnerBookingsTable rows={d.recent_bookings} /> : <p className="text-ink-700">{t("empty")}</p>}
              </section>
            </>
          );
        }}
      </PartnerData>
    </div>
  );
}
