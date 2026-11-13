"use client";

import { buttonClasses } from "@/components/ui/Button";
import { Link } from "@/i18n/navigation";
import { api } from "@/lib/api/client";
import { useAuth } from "@/lib/auth";
import { useCurrency } from "@/lib/currency";
import { CalendarCheck, FileClock, PiggyBank, TrendingUp, Wallet } from "lucide-react";
import { useTranslations } from "next-intl";
import { PartnerBookingsTable } from "./PartnerBookings";
import { PartnerData } from "./PartnerStates";
import { usePartnerQuery } from "./usePartnerQuery";

/** /partner dashboard: KPI cards (SRS 9.3) + recent bookings from GET /partner/dashboard. */
export function PartnerDashboard() {
  const t = useTranslations("partnerPanel.dashboard");
  const { user } = useAuth();
  const { format } = useCurrency();
  const query = usePartnerQuery(["dashboard"], api.partnerDashboard);
  const company = user?.partner?.company ?? "none";

  return (
    <div className="flex flex-col gap-8">
      <div className="flex flex-wrap items-end justify-between gap-4">
        <div>
          <h1 className="text-h2 text-ink-900">{t("title", { company })}</h1>
          <p className="mt-1 text-ink-700">{t("subtitle")}</p>
        </div>
        <Link href="/trips" className={buttonClasses("cta", "md")}>{t("startBooking")}</Link>
      </div>
      <PartnerData query={query} skeleton="h-40">
        {(d) => {
          const kpis = [
            { key: "bookings", value: String(d.kpis.bookings_month), Icon: CalendarCheck },
            { key: "sales", value: format(d.kpis.sales_month), Icon: TrendingUp },
            { key: "commission", value: format(d.kpis.commission_saved_month), Icon: PiggyBank },
            { key: "balance", value: format(d.kpis.balance_due), Icon: Wallet },
            { key: "proofs", value: String(d.kpis.pending_proofs), Icon: FileClock },
          ] as const;
          return (
            <>
              <ul className="grid gap-4 sm:grid-cols-2 xl:grid-cols-5">
                {kpis.map(({ key, value, Icon }) => (
                  <li key={key} className="rounded-card border border-ink-100 bg-white p-5 shadow-card">
                    <p className="flex items-center gap-3 text-sm text-ink-700">
                      <span className="grid size-10 place-items-center rounded-full bg-partner-100 text-partner-600">
                        <Icon className="size-5" aria-hidden />
                      </span>
                      {t(`kpis.${key}`)}
                    </p>
                    <p className="mt-3 text-[28px] font-semibold leading-9 text-ink-900">{value}</p>
                  </li>
                ))}
              </ul>
              <section aria-labelledby="recent-title" className="rounded-card border border-ink-100 bg-white p-5 shadow-card">
                <div className="mb-4 flex items-center justify-between gap-4">
                  <h2 id="recent-title" className="text-h4 text-ink-900">{t("recent")}</h2>
                  <Link href="/partner/bookings" className="font-semibold text-partner-600 hover:underline">{t("viewAll")}</Link>
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
