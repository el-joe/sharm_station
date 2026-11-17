"use client";

import { Flag } from "@/components/nationality/NationalityCombobox";
import { Field, Select } from "@/components/ui/Field";
import { StatusBadge } from "@/components/ui/StatusBadge";
import { Link } from "@/i18n/navigation";
import { api } from "@/lib/api/client";
import type { BookingStatus, PartnerBooking, PaymentStatus } from "@/lib/api/types";
import { useCurrency } from "@/lib/currency";
import { parseISODate } from "@/lib/utils";
import { ChevronRight } from "lucide-react";
import { useFormatter, useTranslations } from "next-intl";
import { useState } from "react";
import { PartnerData } from "./PartnerStates";
import { PartnerPageHeader } from "./shared";
import { usePartnerQuery } from "./usePartnerQuery";

const SERVICES = ["trip", "nile_trip", "package", "accommodation", "transfer"] as const;
const STATUSES: BookingStatus[] = ["awaiting_payment", "awaiting_receipt_review", "confirmed", "fully_paid", "completed", "cancelled", "refunded"];
const PAYMENTS: PaymentStatus[] = ["awaiting_payment", "awaiting_receipt_review", "proof_rejected", "deposit_paid", "fully_paid", "refunded"];

export function useServiceLabel() {
  const ts = useTranslations("service");
  return (s: string) => (s === "nile_trip" ? ts("nile") : ["trip", "nile", "package", "accommodation", "room", "transfer"].includes(s) ? ts(s as "trip") : s);
}

/** Bookings table (desktop) / card list (mobile), Figma 254:35395. Rows open the booking detail. */
export function PartnerBookingsTable({ rows }: { rows: PartnerBooking[] }) {
  const t = useTranslations("partnerPanel.bookings.columns");
  const format = useFormatter();
  const { format: money } = useCurrency();
  const service = useServiceLabel();
  const date = (d: string | null) => (d ? format.dateTime(parseISODate(d.slice(0, 10)), { day: "numeric", month: "short", year: "numeric" }) : "–");
  const href = (r: PartnerBooking) => `/partner/bookings/${encodeURIComponent(r.reference)}`;
  return (
    <>
      <div className="hidden overflow-x-auto rounded-image border border-ink-100 md:block">
        <table className="w-full min-w-[760px] text-sm">
          <thead>
            <tr className="bg-sand-50 text-xs uppercase tracking-wide text-ink-500">
              {(["reference", "client", "service", "date", "total", "status"] as const).map((k) => (
                <th key={k} scope="col" className={`px-3 py-2.5 font-semibold ${k === "total" ? "text-end" : "text-start"}`}>
                  {t(k)}
                </th>
              ))}
            </tr>
          </thead>
          <tbody className="divide-y divide-ink-100">
            {rows.map((r) => (
              <tr key={r.reference} className="hover:bg-sand-50">
                <th scope="row" className="px-3 py-3 text-start font-semibold text-ink-900">
                  <Link href={href(r)} className="hover:underline">
                    {r.reference}
                  </Link>
                </th>
                <td className="px-3 py-3">
                  <span className="block font-medium text-ink-900">{r.lead_name}</span>
                  {r.nationality_code && (
                    <span className="text-xs text-ink-500">
                      <Flag code={r.nationality_code} /> {r.nationality_code}
                    </span>
                  )}
                </td>
                <td className="max-w-64 px-3 py-3">
                  <span className="line-clamp-2 text-ink-900">{r.product_title}</span>
                  <span className="text-xs text-ink-500">{service(r.service_type)}</span>
                </td>
                <td className="px-3 py-3 whitespace-nowrap">{date(r.travel_date)}</td>
                <td className="px-3 py-3 text-end font-semibold whitespace-nowrap text-ink-900">{money(r.partner_total, { decimals: true })}</td>
                <td className="px-3 py-3">
                  <StatusBadge status={r.payment_status ?? r.status} />
                </td>
              </tr>
            ))}
          </tbody>
        </table>
      </div>
      <ul className="flex flex-col gap-3 md:hidden">
        {rows.map((r) => (
          <li key={r.reference}>
            <Link href={href(r)} className="flex items-center gap-3 rounded-image border border-ink-100 bg-white p-4 hover:bg-sand-50">
              <span className="min-w-0 flex-1">
                <span className="flex items-start justify-between gap-3">
                  <span className="font-semibold text-ink-900">{r.reference}</span>
                  <StatusBadge status={r.payment_status ?? r.status} />
                </span>
                <span className="mt-1 block font-medium text-ink-900">{r.product_title}</span>
                <span className="block text-sm text-ink-700">
                  {r.nationality_code && <Flag code={r.nationality_code} />} {[r.lead_name, date(r.travel_date)].filter(Boolean).join(" · ")}
                </span>
                <span className="mt-1 block text-sm">
                  <span className="text-ink-500">{t("total")}: </span>
                  <span className="font-semibold text-ink-900">{money(r.partner_total, { decimals: true })}</span>
                </span>
              </span>
              <ChevronRight className="size-5 shrink-0 text-ink-500 rtl:rotate-180" aria-hidden />
            </Link>
          </li>
        ))}
      </ul>
    </>
  );
}

/** /partner/bookings: GET /partner/bookings with filters (SRS 9.3). */
export function PartnerBookings() {
  const t = useTranslations("partnerPanel.bookings");
  const tst = useTranslations("status");
  const service = useServiceLabel();
  const [filters, setFilters] = useState<{ service?: string; status?: string; payment_status?: string }>({});
  const query = usePartnerQuery(["bookings", filters], (o) => api.partnerBookings(filters, o));
  const set = (k: keyof typeof filters) => (e: React.ChangeEvent<HTMLSelectElement>) => setFilters((f) => ({ ...f, [k]: e.target.value || undefined }));

  return (
    <div className="flex flex-col gap-6">
      <PartnerPageHeader crumbs={[{ label: t("crumb") }]} title={t("title")} subtitle={t("subtitle")} />
      <fieldset className="grid gap-4 sm:grid-cols-3">
        <legend className="sr-only">{t("filtersLabel")}</legend>
        <Field label={t("service")} htmlFor="f-service">
          <Select id="f-service" value={filters.service ?? ""} onChange={set("service")}>
            <option value="">{t("all")}</option>
            {SERVICES.map((s) => (
              <option key={s} value={s}>
                {service(s)}
              </option>
            ))}
          </Select>
        </Field>
        <Field label={t("status")} htmlFor="f-status">
          <Select id="f-status" value={filters.status ?? ""} onChange={set("status")}>
            <option value="">{t("all")}</option>
            {STATUSES.map((s) => (
              <option key={s} value={s}>
                {tst(s)}
              </option>
            ))}
          </Select>
        </Field>
        <Field label={t("payment")} htmlFor="f-payment">
          <Select id="f-payment" value={filters.payment_status ?? ""} onChange={set("payment_status")}>
            <option value="">{t("all")}</option>
            {PAYMENTS.map((s) => (
              <option key={s} value={s}>
                {tst(s)}
              </option>
            ))}
          </Select>
        </Field>
      </fieldset>
      <PartnerData query={query}>
        {(rows) =>
          rows.length ? (
            <div className="rounded-card border border-ink-100 bg-white p-3 shadow-card md:p-5">
              <PartnerBookingsTable rows={rows} />
            </div>
          ) : (
            <p className="rounded-card bg-white p-6 text-center text-ink-700">{t("empty")}</p>
          )
        }
      </PartnerData>
    </div>
  );
}
