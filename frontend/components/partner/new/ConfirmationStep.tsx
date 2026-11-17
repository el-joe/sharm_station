"use client";

import { PaymentSection } from "@/components/booking/PaymentSection";
import { buttonClasses } from "@/components/ui/Button";
import { StatusBadge } from "@/components/ui/StatusBadge";
import { Link } from "@/i18n/navigation";
import type { PartnerBookingDetail } from "@/lib/api/types";
import { useAuth } from "@/lib/auth";
import { useCurrency } from "@/lib/currency";
import { CheckCircle2 } from "lucide-react";
import { useTranslations } from "next-intl";
import { CountryLabel, useCommissionSource } from "../shared";

/** Step 4 (Figma 257:36435): reference, status, totals, voucher buttons, payment. */
export function ConfirmationStep({
  booking,
  subtitle,
  voucher,
  serviceLabel,
  onNew,
}: {
  booking: PartnerBookingDetail;
  subtitle: string;
  voucher: "with_prices" | "without_prices";
  serviceLabel: string;
  onNew: () => void;
}) {
  const t = useTranslations("partnerPanel.newBooking.confirmation");
  const ts = useTranslations("partnerPanel.summary");
  const { token } = useAuth();
  const { format } = useCurrency();
  const source = useCommissionSource();
  const tot = booking.totals;
  const voucherHref = (prices: boolean) => `/partner/bookings/${encodeURIComponent(booking.reference)}/voucher?prices=${prices ? 1 : 0}`;

  return (
    <div className="flex flex-col gap-6">
      <section className="flex flex-col items-center rounded-card border border-ink-100 bg-white p-6 text-center shadow-card md:p-10" aria-labelledby="nb-done-title">
        <span className="grid size-16 place-items-center rounded-full bg-success text-white" aria-hidden>
          <CheckCircle2 className="size-9" />
        </span>
        <h2 id="nb-done-title" className="mt-4 font-display text-[28px] font-semibold leading-9 text-ink-900" tabIndex={-1}>
          {t("title")}
        </h2>
        <p className="mt-3 flex flex-wrap items-center justify-center gap-2 text-ink-700">
          {t("reference")} <strong className="text-xl text-ink-900" data-testid="booking-reference">{booking.reference}</strong>
          <StatusBadge status={booking.payment_status ?? booking.status} />
        </p>
        <p className="mt-3 max-w-xl text-sm text-ink-700">{t("text", { email: booking.lead.email })}</p>

        <dl className="mt-6 w-full max-w-md rounded-image bg-sand-50 p-4 text-start text-sm">
          <div className="flex justify-between gap-3">
            <dt className="text-ink-700">{[booking.product_title, subtitle].filter(Boolean).join(" · ")}</dt>
          </div>
          <div className="mt-1 flex justify-between gap-3 border-b border-ink-100 pb-2">
            <dt className="text-ink-700">{t("client")}</dt>
            <dd className="text-end font-medium text-ink-900">
              {booking.lead.name} · <CountryLabel code={booking.nationality.code} />
              {booking.nationality.group ? ` (${booking.nationality.group.name})` : ""}
            </dd>
          </div>
          <div className="mt-2 flex justify-between gap-3">
            <dt className="text-ink-700">{ts("customerPrice")}</dt>
            <dd className="text-ink-500">
              <s>{format(tot.retail_total, { decimals: true })}</s>
            </dd>
          </div>
          <div className="mt-1 flex justify-between gap-3">
            <dt className="text-ink-700">
              {tot.commission_percent ? ts("commission", { percent: tot.commission_percent }) : ts("noCommission")} ({[serviceLabel, source(tot.commission_source)].join(" · ")})
            </dt>
            <dd className="font-semibold text-success">− {format(tot.commission_amount, { decimals: true })}</dd>
          </div>
          <div className="mt-1 flex justify-between gap-3 border-b border-ink-100 pb-2">
            <dt className="text-base font-semibold text-ink-900">{t("partnerTotal")}</dt>
            <dd className="text-base font-bold text-partner-600">{format(tot.partner_total, { decimals: true })}</dd>
          </div>
          <div className="mt-2 flex justify-between gap-3">
            <dt className="text-ink-700">{ts("deposit", { percent: tot.deposit_percent })}</dt>
            <dd className="font-semibold text-ink-900">{format(tot.deposit_amount, { decimals: true })}</dd>
          </div>
          <div className="mt-1 flex justify-between gap-3">
            <dt className="text-ink-700">{ts("balance")}</dt>
            <dd className="font-semibold text-ink-900">{format(tot.balance_amount, { decimals: true })}</dd>
          </div>
        </dl>

        <div className="mt-6 flex flex-wrap justify-center gap-3">
          <Link href={voucherHref(true)} target="_blank" className={buttonClasses(voucher === "with_prices" ? "partner" : "outline", "md")}>
            {t("voucherWith")}
          </Link>
          <Link href={voucherHref(false)} target="_blank" className={buttonClasses(voucher === "without_prices" ? "partner" : "outline", "md")}>
            {t("voucherWithout")}
          </Link>
        </div>
        <div className="mt-4 flex flex-wrap justify-center gap-6">
          <Link href={`/partner/bookings/${encodeURIComponent(booking.reference)}`} className="font-semibold text-primary-700 hover:underline">
            {t("view")}
          </Link>
          <button type="button" onClick={onNew} className="font-semibold text-primary-700 hover:underline">
            {t("new")}
          </button>
        </div>
      </section>

      {booking.status === "awaiting_payment" && (
        <PaymentSection
          reference={booking.reference}
          accessToken={booking.access_token ?? null}
          authToken={token}
          status={booking.status}
          deposit={tot.deposit_amount}
          total={tot.total}
          bookingCurrency="EUR"
          createdAt={booking.created_at}
          fallbackOptions={booking.payment_options.map((o) => ({ code: o.code ?? String(o.id ?? o.name), name: o.name, type: o.type === "online" ? "online" : "manual" }))}
        />
      )}
    </div>
  );
}
