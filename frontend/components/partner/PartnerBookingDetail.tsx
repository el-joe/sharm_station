"use client";

import { BookingRooms } from "@/components/booking/BookingRooms";
import { CancellationRequest } from "@/components/booking/CancellationRequest";
import { PaymentHistory } from "@/components/booking/PaymentHistory";
import { PaymentSection } from "@/components/booking/PaymentSection";
import { VoucherDownload } from "@/components/booking/VoucherDownload";
import { buttonClasses } from "@/components/ui/Button";
import { EmptyState } from "@/components/ui/EmptyState";
import { StatusBadge } from "@/components/ui/StatusBadge";
import { Link } from "@/i18n/navigation";
import { api } from "@/lib/api/client";
import { useCurrency } from "@/lib/currency";
import { parseISODate } from "@/lib/utils";
import { BadgePercent, FileSearch } from "lucide-react";
import { useFormatter, useTranslations } from "next-intl";
import { use, type ReactNode } from "react";
import { useServiceLabel } from "./PartnerBookings";
import { PartnerData } from "./PartnerStates";
import { CountryLabel, PartnerPageHeader, useCommissionSource } from "./shared";
import { usePartnerQuery } from "./usePartnerQuery";

function Card({ id, title, text, children, actions }: { id: string; title: string; text?: string; children: ReactNode; actions?: ReactNode }) {
  return (
    <section className="rounded-card border border-ink-100 bg-white p-5 shadow-card" aria-labelledby={id}>
      <div className="flex flex-wrap items-start justify-between gap-3">
        <div>
          <h2 id={id} className="text-h4 text-ink-900">
            {title}
          </h2>
          {text && <p className="mt-1 text-sm text-ink-500">{text}</p>}
        </div>
        {actions}
      </div>
      <div className="mt-4">{children}</div>
    </section>
  );
}

/** /partner/bookings/[reference] (Figma 255:35331): GET /bookings/{reference} with the partner session (via /api/proxy). */
export function PartnerBookingDetail({ params }: { params: Promise<{ reference: string }> }) {
  const { reference } = use(params);
  const t = useTranslations("partnerPanel.detail");
  const tb = useTranslations("partnerPanel.bookings");
  const ts = useTranslations("partnerPanel.summary");
  const tbk = useTranslations("booking");
  const tp = useTranslations("paymentHistory");
  const tv = useTranslations("voucherDownload");
  const format = useFormatter();
  const { format: money } = useCurrency();
  const service = useServiceLabel();
  const source = useCommissionSource();
  const query = usePartnerQuery(["booking", reference], (o) => api.partnerBooking(reference, o));
  const date = (d: string | null | undefined) => (d ? format.dateTime(parseISODate(d.slice(0, 10)), { day: "numeric", month: "short", year: "numeric" }) : "");

  return (
    <PartnerData query={query} skeleton="h-[600px]">
      {(b) => {
        if (!b)
          return (
            <EmptyState
              icon={<FileSearch aria-hidden />}
              title={t("notFound", { reference })}
              action={
                <Link href="/partner/bookings" className={buttonClasses("partner", "md")}>
                  {t("back")}
                </Link>
              }
            />
          );
        const tot = b.totals;
        const when = b.check_in ? `${date(b.check_in)} – ${date(b.check_out)}` : b.transfer ? b.transfer.pickup_at : date(b.travel_date);
        const voucherHref = (prices: boolean) => `/partner/bookings/${encodeURIComponent(b.reference)}/voucher?prices=${prices ? 1 : 0}`;
        return (
          <div className="flex flex-col gap-6">
            <PartnerPageHeader
              crumbs={[{ label: tb("crumb"), href: "/partner/bookings" }, { label: b.reference }]}
              title={t("title", { reference: b.reference })}
              subtitle={[b.product_title, when, t("bookedOn", { date: date(b.created_at) })].filter(Boolean).join(" · ")}
              actions={<StatusBadge status={b.payment_status ?? b.status} />}
            />
            <div className="grid gap-6 xl:grid-cols-[minmax(0,1fr)_320px]">
              <div className="flex min-w-0 flex-col gap-6">
                <Card id="pd-client" title={t("client")}>
                  <dl className="grid gap-4 text-sm sm:grid-cols-2">
                    <Item label={t("name")} value={b.lead.name} />
                    <Item label={t("nationality")} value={<CountryLabel code={b.nationality.code} />} sub={b.nationality.group ? t("group", { group: b.nationality.group.name }) : undefined} />
                    <Item label={t("email")} value={b.lead.email} />
                    <Item label={t("phone")} value={b.lead.phone || "–"} />
                    <Item label={t("travellers")} value={t("party", { adults: b.adults, children: b.children })} />
                    <Item label={t("service")} value={[service(b.service_type), b.booking_type ? tbk(b.booking_type) : null].filter(Boolean).join(" · ")} />
                  </dl>
                  {b.travellers.length > 0 && (
                    <ul className="mt-4 flex flex-wrap gap-2 text-sm">
                      {b.travellers.map((tr, i) => (
                        <li key={i} className="rounded-pill bg-sand-50 px-3 py-1 text-ink-700">
                          {tr.first_name} {tr.last_name}
                          {tr.type === "child" && tr.age != null ? ` (${tr.age})` : ""}
                        </li>
                      ))}
                    </ul>
                  )}
                </Card>

                {b.rooms.length > 0 && <BookingRooms rooms={b.rooms} />}

                <Card id="pd-items" title={t("items")} text={b.season_name ? t("season", { season: b.season_name }) : undefined}>
                  <div className="overflow-x-auto rounded-image border border-ink-100">
                    <table className="w-full min-w-[520px] text-sm">
                      <thead>
                        <tr className="bg-sand-50 text-xs uppercase tracking-wide text-ink-500">
                          <th scope="col" className="px-3 py-2.5 text-start font-semibold">{t("columns.item")}</th>
                          <th scope="col" className="px-3 py-2.5 text-end font-semibold">{t("columns.qty")}</th>
                          <th scope="col" className="px-3 py-2.5 text-end font-semibold">{t("columns.customer")}</th>
                          <th scope="col" className="px-3 py-2.5 text-end font-semibold">{t("columns.partner")}</th>
                        </tr>
                      </thead>
                      <tbody className="divide-y divide-ink-100">
                        {b.lines.map((l, i) => (
                          <tr key={i}>
                            <th scope="row" className="px-3 py-3 text-start font-normal text-ink-900">{l.label}</th>
                            <td className="px-3 py-3 text-end">{l.quantity}</td>
                            <td className="px-3 py-3 text-end text-ink-500">{money(l.total, { decimals: true })}</td>
                            <td className="px-3 py-3 text-end font-semibold">{money(l.partner_total ?? l.total, { decimals: true })}</td>
                          </tr>
                        ))}
                      </tbody>
                    </table>
                  </div>
                </Card>

                <PaymentHistory payments={b.payments} title={t("payments")} text={t("paymentsText")} />

                {b.status === "awaiting_payment" && (
                  <PaymentSection
                    reference={b.reference}
                    accessToken={null}
                    auth
                    status={b.status}
                    deposit={tot.deposit_amount}
                    total={tot.total}
                    bookingCurrency="EUR"
                    createdAt={b.created_at}
                    fallbackOptions={b.payment_options.map((o) => ({ code: o.code ?? String(o.id ?? o.name), name: o.name, type: o.type === "online" ? "online" : "manual" }))}
                  />
                )}
              </div>

              <div className="flex flex-col gap-6">
                <Card id="pd-totals" title={t("totals")}>
                  <dl className="flex flex-col gap-2 text-sm">
                    <Row label={t("customerTotal")} value={<s className="text-ink-500">{money(tot.retail_total, { decimals: true })}</s>} />
                    <Row
                      label={tot.commission_percent ? ts("commission", { percent: tot.commission_percent }) : ts("noCommission")}
                      sub={source(tot.commission_source)}
                      value={<span className="font-semibold text-success">− {money(tot.commission_amount, { decimals: true })}</span>}
                    />
                    <div className="mt-1 flex items-center justify-between gap-3 border-t border-ink-100 pt-3">
                      <dt>
                        <span className="block font-semibold text-ink-900">{t("partnerTotal")}</span>
                        <span className="mt-1 inline-flex h-6 items-center gap-1 rounded-pill bg-partner-100 px-2 text-xs font-semibold text-partner-600">
                          <BadgePercent className="size-3.5" aria-hidden /> {t("partnerPrice")}
                        </span>
                      </dt>
                      <dd className="text-[28px] font-bold leading-9 text-partner-600">{money(tot.partner_total, { decimals: true })}</dd>
                    </div>
                    <div className="mt-1 border-t border-ink-100 pt-3">
                      <Row label={t("paid")} value={money(b.paid, { decimals: true })} />
                      <Row label={t("balanceDue")} value={<span className="font-semibold text-warning">{money(b.due, { decimals: true })}</span>} />
                      {b.balance_due_on && b.due > 0 && b.status !== "awaiting_payment" && (
                        <p className="mt-1 text-xs text-ink-500">{tp("balanceDueOn", { date: date(b.balance_due_on) })}</p>
                      )}
                    </div>
                  </dl>
                </Card>
                {b.voucher_available ? (
                  <VoucherDownload reference={b.reference} auth variants={["prices", "client"]} tone="partner">
                    <p className="text-sm text-ink-500">
                      {t("printable")}:{" "}
                      <Link href={voucherHref(true)} target="_blank" className="font-semibold text-partner-600 underline">
                        {t("voucherWith")}
                      </Link>
                      {" · "}
                      <Link href={voucherHref(false)} target="_blank" className="font-semibold text-partner-600 underline">
                        {t("voucherWithout")}
                      </Link>
                    </p>
                  </VoucherDownload>
                ) : (
                  <Card id="pd-voucher" title={t("voucher")} text={t("voucherText")}>
                    <div className="flex flex-col gap-3">
                      <p className="text-sm text-ink-700">{tv("notYet")}</p>
                      <Link href={voucherHref(true)} target="_blank" className={buttonClasses("outline", "md", "w-full")}>
                        {t("voucherWith")}
                      </Link>
                      <Link href={voucherHref(false)} target="_blank" className={buttonClasses("partner", "md", "w-full")}>
                        {t("voucherWithout")}
                      </Link>
                    </div>
                  </Card>
                )}
                <CancellationRequest reference={b.reference} cancellation={b.cancellation} refunds={b.payments.filter((p) => p.type === "refund")} auth onChange={() => void query.refetch()} />
              </div>
            </div>
          </div>
        );
      }}
    </PartnerData>
  );
}

function Item({ label, value, sub }: { label: string; value: ReactNode; sub?: string }) {
  return (
    <div>
      <dt className="text-xs text-ink-500">{label}</dt>
      <dd className="font-medium break-words text-ink-900">
        {value}
        {sub && <span className="block text-xs font-normal text-ink-500">{sub}</span>}
      </dd>
    </div>
  );
}

function Row({ label, value, sub }: { label: string; value: ReactNode; sub?: string }) {
  return (
    <div className="flex items-start justify-between gap-3 py-0.5">
      <dt className="text-ink-700">
        {label}
        {sub && <span className="block text-xs text-ink-500">{sub}</span>}
      </dt>
      <dd className="text-ink-900">{value}</dd>
    </div>
  );
}
