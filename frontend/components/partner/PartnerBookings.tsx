"use client";

import { Field, Select } from "@/components/ui/Field";
import { StatusBadge } from "@/components/ui/StatusBadge";
import { api } from "@/lib/api/client";
import type { BookingStatus, PartnerBooking, PaymentStatus } from "@/lib/api/types";
import { useCurrency } from "@/lib/currency";
import { parseISODate } from "@/lib/utils";
import { useFormatter, useTranslations } from "next-intl";
import { useState } from "react";
import { PartnerData } from "./PartnerStates";
import { usePartnerQuery } from "./usePartnerQuery";

const SERVICES = ["trip", "nile", "package", "accommodation", "transfer"] as const;
const STATUSES: BookingStatus[] = ["awaiting_payment", "awaiting_receipt_review", "confirmed", "fully_paid", "completed", "cancelled", "refunded"];
const PAYMENTS: PaymentStatus[] = ["awaiting_payment", "awaiting_receipt_review", "proof_rejected", "deposit_paid", "fully_paid", "refunded"];

/** Bookings table (desktop) / card list (mobile). */
export function PartnerBookingsTable({ rows }: { rows: PartnerBooking[] }) {
  const t = useTranslations("partnerPanel.bookings.columns");
  const ts = useTranslations("service");
  const format = useFormatter();
  const { format: money } = useCurrency();
  const date = (d: string | null) => (d ? format.dateTime(parseISODate(d), { day: "numeric", month: "short", year: "numeric" }) : "–");
  const service = (s: string) => (SERVICES.includes(s as never) || s === "room" ? ts(s as "trip") : s);
  return (
    <>
      <div className="hidden overflow-x-auto md:block">
        <table className="w-full min-w-[820px] text-start text-sm">
          <thead>
            <tr className="bg-sand-50 text-ink-700">
              {(["reference", "service", "product", "date", "lead", "total", "paid", "balance", "status"] as const).map((k) => (
                <th key={k} scope="col" className={`px-3 py-3 font-semibold ${["total", "paid", "balance"].includes(k) ? "text-end" : "text-start"}`}>
                  {t(k)}
                </th>
              ))}
            </tr>
          </thead>
          <tbody className="divide-y divide-ink-100">
            {rows.map((r) => (
              <tr key={r.reference}>
                <th scope="row" className="px-3 py-3 text-start font-mono font-semibold text-ink-900">{r.reference}</th>
                <td className="px-3 py-3">{service(r.service_type)}</td>
                <td className="max-w-56 truncate px-3 py-3">{r.product_title}</td>
                <td className="px-3 py-3 whitespace-nowrap">{date(r.travel_date)}</td>
                <td className="px-3 py-3">{r.lead_name}</td>
                <td className="px-3 py-3 text-end">{money(r.partner_total)}</td>
                <td className="px-3 py-3 text-end">{money(r.paid)}</td>
                <td className="px-3 py-3 text-end">{money(r.balance)}</td>
                <td className="px-3 py-3"><StatusBadge status={r.status} /></td>
              </tr>
            ))}
          </tbody>
        </table>
      </div>
      <ul className="flex flex-col gap-3 md:hidden">
        {rows.map((r) => (
          <li key={r.reference} className="rounded-image border border-ink-100 p-4">
            <div className="flex items-start justify-between gap-3">
              <p className="font-mono font-semibold text-ink-900">{r.reference}</p>
              <StatusBadge status={r.status} />
            </div>
            <p className="mt-1 font-semibold text-ink-900">{r.product_title}</p>
            <p className="text-sm text-ink-700">{[service(r.service_type), date(r.travel_date), r.lead_name].filter(Boolean).join(" · ")}</p>
            <dl className="mt-2 grid grid-cols-3 gap-2 text-sm">
              <div><dt className="text-ink-500">{t("total")}</dt><dd className="font-semibold">{money(r.partner_total)}</dd></div>
              <div><dt className="text-ink-500">{t("paid")}</dt><dd>{money(r.paid)}</dd></div>
              <div><dt className="text-ink-500">{t("balance")}</dt><dd>{money(r.balance)}</dd></div>
            </dl>
          </li>
        ))}
      </ul>
    </>
  );
}

/** /partner/bookings: GET /partner/bookings with filters (SRS 9.3). */
export function PartnerBookings() {
  const t = useTranslations("partnerPanel.bookings");
  const ts = useTranslations("service");
  const tst = useTranslations("status");
  const [filters, setFilters] = useState<{ service?: string; status?: string; payment_status?: string }>({});
  const query = usePartnerQuery(["bookings", filters], (o) => api.partnerBookings(filters, o));
  const set = (k: keyof typeof filters) => (e: React.ChangeEvent<HTMLSelectElement>) => setFilters((f) => ({ ...f, [k]: e.target.value || undefined }));

  return (
    <div className="flex flex-col gap-6">
      <div>
        <h1 className="text-h2 text-ink-900">{t("title")}</h1>
        <p className="mt-1 text-ink-700">{t("subtitle")}</p>
      </div>
      <fieldset className="grid gap-4 sm:grid-cols-3">
        <legend className="sr-only">{t("filtersLabel")}</legend>
        <Field label={t("service")} htmlFor="f-service">
          <Select id="f-service" value={filters.service ?? ""} onChange={set("service")}>
            <option value="">{t("all")}</option>
            {SERVICES.map((s) => <option key={s} value={s}>{ts(s)}</option>)}
          </Select>
        </Field>
        <Field label={t("status")} htmlFor="f-status">
          <Select id="f-status" value={filters.status ?? ""} onChange={set("status")}>
            <option value="">{t("all")}</option>
            {STATUSES.map((s) => <option key={s} value={s}>{tst(s)}</option>)}
          </Select>
        </Field>
        <Field label={t("payment")} htmlFor="f-payment">
          <Select id="f-payment" value={filters.payment_status ?? ""} onChange={set("payment_status")}>
            <option value="">{t("all")}</option>
            {PAYMENTS.map((s) => <option key={s} value={s}>{tst(s)}</option>)}
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
            <p className="rounded-card bg-sand-50 p-6 text-center text-ink-700">{t("empty")}</p>
          )
        }
      </PartnerData>
    </div>
  );
}
