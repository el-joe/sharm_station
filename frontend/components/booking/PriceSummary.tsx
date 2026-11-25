"use client";

import type { QuoteLine, QuoteResult, QuoteRoom } from "@/lib/api/types";
import { useCurrency } from "@/lib/currency";
import { cn, round2 } from "@/lib/utils";
import { Loader2 } from "lucide-react";
import { useTranslations } from "next-intl";

export type Translate = (key: string, values?: Record<string, string | number>) => string;

export function lineLabel(line: QuoteLine, t: Translate, money: (n: number) => string) {
  if (line.label) return line.label;
  switch (line.kind) {
    case "adult":
      return t("adults", { count: line.quantity, price: money(line.unit_price) });
    case "child":
      return line.age != null ? t("childAge", { age: line.age, price: money(line.unit_price) }) : t("child", { price: money(line.unit_price) });
    case "child_free":
      return t("childFree", { age: line.age ?? 0 });
    case "unit":
    case "addon":
      return line.quantity > 1 ? `${line.name} × ${line.quantity}` : `${line.name}`;
    case "night":
      return t("nights", { count: line.quantity, price: money(line.unit_price) });
    case "extra_bed":
      return t("extraBed", { count: line.quantity, price: money(line.unit_price) });
    case "cot":
      return t("cot", { count: line.quantity, price: money(line.unit_price) });
    default:
      return line.name ?? "";
  }
}

/**
 * Quote lines; multi-room stays are grouped per room (line.room_index, never the label) with a heading and
 * the room total, so checkout and confirmation show one block per room.
 */
export function QuoteLineList({ lines, rooms, className }: { lines: QuoteLine[]; rooms?: { index: number; name: string; number?: string | null; total?: number }[]; className?: string }) {
  const t = useTranslations("summary");
  const { format } = useCurrency();
  const money = (n: number) => format(n);
  const item = (l: QuoteLine, i: number) => (
    <li key={i} className="flex justify-between gap-3">
      <span>{lineLabel(l, t, money)}</span>
      <span className="tabular-nums text-ink-900">{l.total === 0 ? t("free") : money(l.total)}</span>
    </li>
  );
  const grouped = rooms && rooms.length > 1 && lines.some((l) => l.room_index != null);
  if (!grouped) return <ul className={cn("flex flex-col gap-2 text-sm text-ink-700", className)}>{lines.map(item)}</ul>;
  const rest = lines.filter((l) => l.room_index == null || !rooms.some((r) => r.index === l.room_index));
  return (
    <div className={cn("flex flex-col gap-3 text-sm text-ink-700", className)}>
      {rooms.map((r) => {
        const own = lines.filter((l) => l.room_index === r.index);
        const total = r.total ?? own.reduce((s, l) => s + l.total, 0);
        return (
          <section key={r.index} aria-label={t("roomHeading", { n: r.index + 1, name: r.name })} className="rounded-image border border-ink-100 p-3" data-testid="quote-room">
            <h4 className="flex justify-between gap-3 font-semibold text-ink-900">
              <span>{t("roomHeading", { n: r.index + 1, name: [r.name, r.number].filter(Boolean).join(" ") })}</span>
              <span className="tabular-nums">{money(total)}</span>
            </h4>
            <ul className="mt-2 flex flex-col gap-1.5">{own.map(item)}</ul>
          </section>
        );
      })}
      {rest.length > 0 && <ul className="flex flex-col gap-2">{rest.map(item)}</ul>}
    </div>
  );
}

/** Live price summary: itemised lines, add-ons, total, deposit and balance (SRS 10.1). */
export function PriceSummary({
  quote,
  loading,
  pendingNote,
  className,
  discount,
}: {
  quote: QuoteResult | undefined;
  loading?: boolean;
  pendingNote?: string | null;
  className?: string;
  /** Promo code preview (POST /coupons/validate); the server applies it again on booking. */
  discount?: { code: string; amount: number } | null;
}) {
  const t = useTranslations("summary");
  const { format } = useCurrency();
  const money = (n: number) => format(n);
  const off = quote && discount ? Math.min(discount.amount, quote.total) : 0;
  const total = quote ? round2(quote.total - off) : 0;
  const deposit = quote ? (off > 0 ? round2((total * quote.deposit_percent) / 100) : quote.deposit) : 0;
  const balance = quote ? (off > 0 ? round2(total - deposit) : quote.balance) : 0;

  return (
    <section aria-labelledby="summary-title" aria-busy={loading} className={cn("flex flex-col gap-3", className)}>
      <div className="flex items-center justify-between">
        <h3 id="summary-title" className="text-[22px] font-semibold leading-[30px] text-ink-900">{t("title")}</h3>
        {loading && (
          <span className="inline-flex items-center gap-1.5 text-sm text-ink-500" role="status">
            <Loader2 className="size-4 animate-spin" aria-hidden /> {t("updating")}
          </span>
        )}
      </div>
      {!quote ? (
        <div className="flex flex-col gap-2" aria-hidden>
          {[0, 1, 2].map((i) => (
            <div key={i} className="h-4 animate-pulse rounded bg-ink-100" />
          ))}
        </div>
      ) : (
        <div className={cn("flex flex-col gap-3 transition-opacity", loading && "opacity-60")}>
          <QuoteLineList lines={quote.lines} rooms={quote.rooms as QuoteRoom[] | undefined} />
          {quote.addon_lines.length > 0 && (
            <>
              <p className="border-t border-ink-100 pt-3 text-xs text-ink-500">{t("addons")}</p>
              <ul className="flex flex-col gap-2 text-sm text-ink-700">
                {quote.addon_lines.map((l, i) => (
                  <li key={i} className="flex justify-between gap-3">
                    <span>{lineLabel(l, t, money)}</span>
                    <span className="tabular-nums text-ink-900">{money(l.total)}</span>
                  </li>
                ))}
              </ul>
            </>
          )}
          {off > 0 && discount && (
            <div className="flex justify-between gap-3 border-t border-ink-100 pt-3 text-sm font-medium text-success">
              <span>{t("discount", { code: discount.code })}</span>
              <span className="tabular-nums">−{money(off)}</span>
            </div>
          )}
          <div className="flex items-end justify-between border-t border-ink-100 pt-3">
            <span className="font-semibold text-ink-900">{pendingNote ? t("totalPending") : t("total")}</span>
            <span className="flex flex-col items-end">
              {off > 0 && <s className="text-sm tabular-nums text-ink-500">{money(quote.total)}</s>}
              <span className="text-[26px] font-bold leading-8 tabular-nums text-ink-900">{money(total)}</span>
            </span>
          </div>
          <dl className="rounded-image bg-primary-100 px-4 py-3">
            <div className="flex justify-between font-medium text-ink-900">
              <dt>{t("payToday", { percent: quote.deposit_percent })}</dt>
              <dd className="font-bold tabular-nums">{money(deposit)}</dd>
            </div>
            <div className="mt-1 flex justify-between text-sm text-ink-700">
              <dt>{t("balanceLater")}</dt>
              <dd className="tabular-nums">{money(balance)}</dd>
            </div>
          </dl>
          {pendingNote && <p className="text-xs text-ink-500">{pendingNote}</p>}
        </div>
      )}
    </section>
  );
}
