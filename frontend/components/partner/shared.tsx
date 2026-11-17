"use client";

import { Flag } from "@/components/nationality/NationalityCombobox";
import { Breadcrumbs } from "@/components/ui/Breadcrumbs";
import type { CatalogueService, CommissionSource, PartnerQuote } from "@/lib/api/types";
import { useCurrency } from "@/lib/currency";
import { useNationality } from "@/lib/nationality";
import { cn } from "@/lib/utils";
import { Check, Info, Loader2 } from "lucide-react";
import { useTranslations } from "next-intl";
import type { ReactNode } from "react";

export const SERVICES: CatalogueService[] = ["single_day_trips", "multi_day_trips", "nile_trips", "packages", "transfers", "accommodation"];

/** Breadcrumbs + title + subtitle + optional actions, as on every partner page. */
export function PartnerPageHeader({ crumbs, title, subtitle, actions }: { crumbs?: { label: string; href?: string }[]; title: string; subtitle?: ReactNode; actions?: ReactNode }) {
  const t = useTranslations("partnerPanel");
  const tc = useTranslations("common");
  return (
    <div className="flex flex-col gap-3">
      <Breadcrumbs className="text-ink-700" label={tc("breadcrumbs")} items={[{ label: t("crumb"), href: "/partner" }, ...(crumbs ?? [])]} />
      <div className="flex flex-wrap items-end justify-between gap-4">
        <div className="min-w-0">
          <h1 className="text-h2 text-ink-900" tabIndex={-1}>
            {title}
          </h1>
          {subtitle && <p className="mt-1 text-ink-700">{subtitle}</p>}
        </div>
        {actions && <div className="flex flex-wrap gap-2">{actions}</div>}
      </div>
    </div>
  );
}

export function useCommissionSource() {
  const t = useTranslations("partnerPanel.commission");
  return (source: CommissionSource | null | undefined) => (source === "partner_override" ? t("yourRate") : source === "global_setting" ? t("standardRate") : t("none"));
}

/** "15% commission" pill (or "No commission" for accommodation). */
export function CommissionPill({ percent, className }: { percent: number; className?: string }) {
  const t = useTranslations("partnerPanel.commission");
  return (
    <span className={cn("inline-flex h-6 items-center rounded-pill px-2.5 text-xs font-semibold", percent > 0 ? "bg-partner-100 text-partner-600" : "bg-ink-100 text-ink-700", className)}>
      {percent > 0 ? t("pill", { percent }) : t("none")}
    </span>
  );
}

/** Country flag + name (+ group) for a nationality code. */
export function CountryLabel({ code, withGroup = false, className }: { code: string | null | undefined; withGroup?: boolean; className?: string }) {
  const { data } = useNationality();
  const country = data?.countries.find((c) => c.code === code);
  if (!code) return <span className={className}>–</span>;
  return (
    <span className={cn("inline-flex items-center gap-1.5", className)}>
      <Flag code={code} />
      <span>
        {country?.name ?? code}
        {withGroup && country ? ` · ${country.group.name}` : ""}
      </span>
    </span>
  );
}

/** New booking steps (Figma 257:35545): 1 Choose service, 2 Configure, 3 Client & payment, 4 Confirmation. */
export function BookingStepper({ step }: { step: 1 | 2 | 3 | 4 }) {
  const t = useTranslations("partnerPanel.newBooking.steps");
  const steps = [t("service"), t("configure"), t("client"), t("confirmation")];
  return (
    <ol className="flex flex-wrap items-center gap-x-2 gap-y-2 text-sm" aria-label={t("label")}>
      {steps.map((label, i) => {
        const n = i + 1;
        const done = n < step;
        const current = n === step;
        return (
          <li key={label} className="flex items-center gap-2" aria-current={current ? "step" : undefined}>
            <span
              className={cn(
                "grid size-6 shrink-0 place-items-center rounded-full text-xs font-bold",
                done ? "bg-success text-white" : current ? "bg-partner-600 text-white" : "border border-ink-300 text-ink-500",
              )}
            >
              {done ? <Check className="size-3.5" strokeWidth={3} aria-hidden /> : n}
            </span>
            <span className={cn(current ? "font-semibold text-ink-900" : "text-ink-700", !current && "max-sm:sr-only")}>{label}</span>
            {done && <span className="sr-only">{t("done")}</span>}
            {n < steps.length && <span className={cn("h-px w-6 sm:w-10", done ? "bg-success" : "bg-ink-300")} aria-hidden />}
          </li>
        );
      })}
    </ol>
  );
}

/** Live partner price summary (Figma "Price summary"): customer lines, customer price struck, commission, partner price, deposit, balance. */
export function PartnerPriceSummary({
  title,
  subtitle,
  quote,
  loading,
  errors,
  serviceLabel,
  footer,
}: {
  title: string;
  subtitle?: string;
  quote?: PartnerQuote | null;
  loading?: boolean;
  errors?: string[];
  serviceLabel?: string;
  footer?: ReactNode;
}) {
  const t = useTranslations("partnerPanel.summary");
  const { format } = useCurrency();
  const source = useCommissionSource();
  return (
    <section className="rounded-card border border-ink-100 bg-white p-5 shadow-card" aria-labelledby="partner-summary-title" aria-busy={loading || undefined}>
      <div className="flex items-center justify-between gap-2">
        <h2 id="partner-summary-title" className="text-h4 text-ink-900">
          {t("title")}
        </h2>
        {loading && <Loader2 className="size-5 animate-spin text-partner-600" aria-label={t("updating")} />}
      </div>
      <div className="mt-3 border-b border-ink-100 pb-3">
        <p className="font-semibold text-ink-900">{title}</p>
        {subtitle && <p className="text-xs text-ink-500">{subtitle}</p>}
      </div>
      {errors && errors.length > 0 && (
        <ul className="mt-3 flex flex-col gap-1 rounded-image bg-warning-100 p-3 text-sm text-ink-900" role="alert">
          {errors.map((e) => (
            <li key={e}>{e}</li>
          ))}
        </ul>
      )}
      {quote ? (
        <div aria-live="polite">
          <ul className="mt-3 flex flex-col gap-2 border-b border-ink-100 pb-3 text-sm">
            {quote.lines.map((l, i) => (
              <li key={`${l.label}-${i}`} className="flex justify-between gap-3">
                <span className="text-ink-700">
                  {l.label} × {l.quantity} · {format(l.unit_price, { decimals: true })}
                </span>
                <span className="shrink-0 font-semibold text-ink-900">{format(l.total, { decimals: true })}</span>
              </li>
            ))}
          </ul>
          <dl className="mt-3 flex flex-col gap-2 border-b border-ink-100 pb-3 text-sm">
            <div className="flex justify-between gap-3">
              <dt className="text-ink-700">{t("customerPrice")}</dt>
              <dd className="text-ink-500">
                <s>{format(quote.retail_total, { decimals: true })}</s>
              </dd>
            </div>
            <div className="flex justify-between gap-3">
              <dt className="text-ink-700">
                {quote.commission_percent > 0 ? t("commission", { percent: quote.commission_percent }) : t("noCommission")}
                <span className="block text-xs text-ink-500">{[serviceLabel, source(quote.commission_source)].filter(Boolean).join(" · ")}</span>
              </dt>
              <dd className="font-semibold text-success">{quote.commission_amount > 0 ? `− ${format(quote.commission_amount, { decimals: true })}` : format(0)}</dd>
            </div>
            <div className="flex items-center justify-between gap-3 pt-1">
              <dt className="font-semibold text-ink-900">{t("partnerPrice")}</dt>
              <dd className="text-[28px] font-bold leading-9 text-partner-600">{format(quote.partner_total, { decimals: true })}</dd>
            </div>
          </dl>
          <dl className="mt-3 flex flex-col gap-2 text-sm">
            <div className="flex justify-between gap-3">
              <dt className="text-ink-900">{t("deposit", { percent: quote.deposit_percent })}</dt>
              <dd className="font-semibold text-ink-900">{format(quote.deposit_amount, { decimals: true })}</dd>
            </div>
            <div className="flex justify-between gap-3">
              <dt className="text-ink-500">{t("balance")}</dt>
              <dd className="font-semibold text-ink-900">{format(quote.balance_amount, { decimals: true })}</dd>
            </div>
          </dl>
          <p className="mt-3 flex items-start gap-2 rounded-image bg-sand-50 p-3 text-xs text-ink-700">
            <Flag code={quote.nationality.code} />
            <span>
              {t("nationalityNote", { group: quote.nationality.group?.name ?? "–" })}
              {quote.season_name ? ` ${t("season", { season: quote.season_name })}` : ` ${t("noSeason")}`}
            </span>
          </p>
        </div>
      ) : (
        !loading && (
          <p className="mt-3 flex items-start gap-2 text-sm text-ink-700">
            <Info className="mt-0.5 size-4 shrink-0 text-partner-600" aria-hidden /> {t("empty")}
          </p>
        )
      )}
      {footer && <div className="mt-4">{footer}</div>}
    </section>
  );
}
