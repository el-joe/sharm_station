"use client";

import type { BookingPayment } from "@/lib/api/types";
import { useCurrency } from "@/lib/currency";
import { cn, parseISODate } from "@/lib/utils";
import { CalendarClock, CreditCard, Undo2 } from "lucide-react";
import { useFormatter, useTranslations } from "next-intl";

const TONE: Record<string, string> = {
  succeeded: "bg-success-100 text-success",
  pending: "bg-warning-100 text-warning",
  failed: "bg-error-100 text-error",
  refunded: "bg-sand-50 text-ink-700",
};

/**
 * Payment history of a booking (BookingResource `payments[]`): deposit / balance payments and their refunds
 * (type "refund", shown as a negative amount), with method, status and date. Optional paid / still-to-pay
 * totals and the balance due date. Used on /booking/[reference], the account detail and the partner detail.
 */
export function PaymentHistory({
  payments,
  paid,
  due,
  balanceDueOn,
  className,
  title,
  text,
  headingLevel = 2,
}: {
  payments: BookingPayment[];
  /** EUR totals from payment_summary; hidden when null. */
  paid?: number | null;
  due?: number | null;
  balanceDueOn?: string | null;
  className?: string;
  title?: string;
  text?: string;
  headingLevel?: 2 | 3;
}) {
  const t = useTranslations("paymentHistory");
  const format = useFormatter();
  const { format: money } = useCurrency();
  const Heading = headingLevel === 3 ? "h3" : "h2";
  const date = (iso: string) => format.dateTime(iso.length === 10 ? parseISODate(iso) : new Date(iso), { day: "numeric", month: "short", year: "numeric" });
  const typeLabel = (type: string) => (type === "deposit" || type === "balance" || type === "refund" ? t(`types.${type}`) : t("types.other"));
  const statusLabel = (p: BookingPayment) => {
    const known = ["pending", "succeeded", "failed", "refunded"].includes(p.status);
    if (!known) return p.status;
    return p.type === "refund" ? t(`refundStatuses.${p.status as "pending"}`) : t(`statuses.${p.status as "pending"}`);
  };
  const showTotals = paid != null || due != null;
  const showDueDate = Boolean(balanceDueOn) && (due == null || due > 0);

  return (
    <section className={cn("rounded-card border border-ink-100 bg-white p-5 shadow-card", className)} aria-label={title ?? t("title")}>
      <Heading className="text-h4 text-ink-900">{title ?? t("title")}</Heading>
      {text && <p className="mt-1 text-sm text-ink-500">{text}</p>}
      {payments.length > 0 ? (
        <ul className="mt-4 divide-y divide-ink-100 rounded-image border border-ink-100 text-sm">
          {payments.map((p, i) => {
            const refund = p.type === "refund";
            return (
              <li key={i} className="flex flex-wrap items-center justify-between gap-x-3 gap-y-1 px-3 py-3" data-payment-type={p.type}>
                <span className="flex min-w-0 items-center gap-2.5">
                  <span className={cn("grid size-8 shrink-0 place-items-center rounded-full", refund ? "bg-sand-50 text-ink-700" : "bg-primary-100 text-primary-700")}>
                    {refund ? <Undo2 className="size-4" aria-hidden /> : <CreditCard className="size-4" aria-hidden />}
                  </span>
                  <span className="min-w-0">
                    <span className="block font-semibold text-ink-900">{typeLabel(p.type)}</span>
                    <span className="block text-xs text-ink-500">{[p.method, p.paid_at ? date(p.paid_at) : null].filter(Boolean).join(" · ")}</span>
                  </span>
                </span>
                <span className="ms-auto flex items-center gap-3">
                  <span className={cn("font-semibold", refund ? "text-ink-700" : "text-ink-900")}>
                    {refund ? "− " : ""}
                    {money(p.amount, { decimals: true })}
                  </span>
                  <span className={cn("rounded-pill px-2.5 py-0.5 text-xs font-semibold", TONE[p.status] ?? "bg-sand-50 text-ink-700")}>{statusLabel(p)}</span>
                </span>
              </li>
            );
          })}
        </ul>
      ) : (
        <p className="mt-3 text-sm text-ink-700">{t("empty")}</p>
      )}
      {showTotals && (
        <dl className="mt-4 flex flex-col gap-2 text-sm">
          {paid != null && (
            <div className="flex justify-between gap-3">
              <dt className="text-ink-700">{t("paid")}</dt>
              <dd className="font-semibold text-ink-900">{money(paid, { decimals: true })}</dd>
            </div>
          )}
          {due != null && (
            <div className="flex justify-between gap-3">
              <dt className="text-ink-700">{t("due")}</dt>
              <dd className={cn("font-semibold", due > 0 ? "text-warning" : "text-ink-900")}>{money(due, { decimals: true })}</dd>
            </div>
          )}
        </dl>
      )}
      {showDueDate && (
        <p className="mt-3 flex items-center gap-1.5 text-sm text-ink-700">
          <CalendarClock className="size-4 text-ink-500" aria-hidden /> {t("balanceDueOn", { date: date(balanceDueOn!) })}
        </p>
      )}
    </section>
  );
}
