"use client";

import { lineLabel } from "@/components/booking/PriceSummary";
import { AppImage } from "@/components/ui/AppImage";
import { Breadcrumbs } from "@/components/ui/Breadcrumbs";
import { buttonClasses } from "@/components/ui/Button";
import { EmptyState } from "@/components/ui/EmptyState";
import { StatusBadge } from "@/components/ui/StatusBadge";
import { Link } from "@/i18n/navigation";
import { api } from "@/lib/api/client";
import type { Locale } from "@/lib/api/types";
import { useAuth } from "@/lib/auth";
import { readBooking } from "@/lib/checkout";
import { useCurrency } from "@/lib/currency";
import { whatsappHref } from "@/lib/site";
import { parseISODate } from "@/lib/utils";
import { useQuery } from "@tanstack/react-query";
import { ArrowLeft, FileSearch, MessageCircle } from "lucide-react";
import { useFormatter, useLocale, useTranslations } from "next-intl";
import { use } from "react";
import { bookingDate, bookingImage } from "./bookingVisuals";

/** /account/bookings/[reference]: GET /bookings/{reference} with the user's bearer token (+ access token if this device made the booking). */
export function AccountBookingDetail({ params }: { params: Promise<{ reference: string }> }) {
  const { reference } = use(params);
  const t = useTranslations("account.detail");
  const ts = useTranslations("summary");
  const tsv = useTranslations("service");
  const tc = useTranslations("common");
  const ta = useTranslations("account");
  const format = useFormatter();
  const locale = useLocale() as Locale;
  const { token } = useAuth();
  const { format: money } = useCurrency();

  const query = useQuery({
    queryKey: ["account", "booking", reference, token, locale],
    queryFn: ({ signal }) => {
      const stored = readBooking(reference);
      return api.booking(reference, { locale, token, signal, accessToken: stored?.access_token ?? null });
    },
    enabled: Boolean(token),
  });

  const back = (
    <div className="mt-4 flex flex-wrap items-center justify-between gap-3">
      <Breadcrumbs
        className="text-ink-700"
        label={tc("breadcrumbs")}
        items={[{ label: tc("home"), href: "/" }, { label: ta("crumb"), href: "/account/bookings" }, { label: ta("nav.bookings"), href: "/account/bookings" }, { label: reference }]}
      />
      <Link href="/account/bookings" className="inline-flex items-center gap-1 text-sm font-semibold text-primary-700 hover:underline">
        <ArrowLeft className="size-4 rtl:rotate-180" aria-hidden /> {t("back")}
      </Link>
    </div>
  );

  if (query.isPending) return <div className="mt-6 h-[480px] animate-pulse rounded-card bg-ink-100" aria-busy="true" />;

  const b = query.data;
  if (!b) {
    return (
      <div className="mt-6 flex flex-col gap-6">
        {back}
        <EmptyState
          icon={<FileSearch aria-hidden />}
          title={t("notFoundTitle")}
          text={t("notFoundText", { reference })}
          action={<Link href="/contact" className={buttonClasses("outline", "md")}>{t("contact")}</Link>}
        />
      </div>
    );
  }

  const date = bookingDate(b);
  const fmt = (d: string) => format.dateTime(parseISODate(d), { weekday: "short", day: "numeric", month: "long", year: "numeric" });
  const money2 = (n: number) => money(n);

  return (
    <div className="mt-2 flex flex-col gap-6">
      {back}
      <div>
        <p className="text-sm text-ink-500">
          {[b.reference, tsv(b.service_type), b.created_at ? t("bookedOn", { date: format.dateTime(new Date(b.created_at), { day: "numeric", month: "short", year: "numeric" }) }) : null]
            .filter(Boolean)
            .join(" · ")}
        </p>
        <h1 className="text-h1 mt-1 text-ink-900">{b.product_title}</h1>
        <StatusBadge status={b.status} className="mt-3" />
      </div>
      <div className="grid gap-6 lg:grid-cols-[minmax(0,1fr)_380px]">
        <div className="flex flex-col gap-6">
          <div className="relative aspect-[16/8] overflow-hidden rounded-card bg-ink-100">
            <AppImage src={bookingImage(b)} alt="" fill sizes="(min-width: 1024px) 760px, 100vw" className="object-cover" />
          </div>
          <section className="rounded-card border border-ink-100 bg-white p-5 shadow-card md:p-6" aria-labelledby="party-title">
            <h3 id="party-title" className="text-h4 text-ink-900">{t("travellersTitle")}</h3>
            <dl className="mt-4 grid gap-4 sm:grid-cols-2">
              {b.check_in && b.check_out ? (
                <>
                  <Item label={t("checkIn")} value={fmt(b.check_in)} />
                  <Item label={t("checkOut")} value={fmt(b.check_out)} />
                </>
              ) : date ? (
                <Item label={t("date")} value={fmt(date)} />
              ) : null}
              <Item label={t("lead")} value={[b.lead.name, b.lead.email, b.lead.phone].filter(Boolean).join(" · ")} />
            </dl>
            {b.travellers && b.travellers.length > 0 && (
              <ul className="mt-4 divide-y divide-ink-100 border-t border-ink-100">
                {b.travellers.map((tr, i) => (
                  <li key={i} className="flex justify-between gap-4 py-2 text-sm">
                    <span className="text-ink-900">{`${tr.first_name} ${tr.last_name}`.trim()}</span>
                    <span className="text-ink-500">{tr.type === "child" ? t("child", { age: tr.age ?? 0 }) : t("adult")}</span>
                  </li>
                ))}
              </ul>
            )}
          </section>
          <p className="rounded-card bg-sand-50 p-5 text-ink-700">{t("cancelInfo")}</p>
        </div>
        <aside className="flex flex-col gap-4 lg:sticky lg:top-28 lg:self-start">
          <section className="rounded-card border border-ink-100 bg-white p-5 shadow-card" aria-labelledby="price-title">
            <h3 id="price-title" className="text-h4 text-ink-900">{t("priceTitle")}</h3>
            {b.lines.length > 0 && (
              <ul className="mt-3 flex flex-col gap-2 text-sm">
                {b.lines.map((l, i) => (
                  <li key={i} className="flex justify-between gap-3">
                    <span className="text-ink-700">{lineLabel(l, ts as never, money2)}</span>
                    <span className="text-ink-900">{money(l.total)}</span>
                  </li>
                ))}
              </ul>
            )}
            <dl className="mt-4 flex flex-col gap-2 border-t border-ink-100 pt-4">
              <Row label={t("total")} value={money(b.totals.total)} strong />
              <Row label={t("deposit")} value={money(b.totals.deposit)} />
              <Row label={t("balance")} value={money(b.totals.balance)} />
            </dl>
          </section>
          {b.status === "awaiting_payment" && (
            <section className="rounded-card bg-primary-100 p-5" aria-labelledby="pay-title">
              <h3 id="pay-title" className="font-semibold text-ink-900">{t("payTitle")}</h3>
              <p className="mt-1 text-sm text-ink-700">{t("payText")}</p>
              <Link href={`/booking/${b.reference}`} className={buttonClasses("cta", "md", "mt-4 w-full")}>{t("payNow")}</Link>
            </section>
          )}
          <a href={whatsappHref(`${b.reference}: `)} target="_blank" rel="noopener noreferrer" className={buttonClasses("outline", "md", "w-full")}>
            <MessageCircle className="size-5" aria-hidden /> {t("contact")}
          </a>
        </aside>
      </div>
    </div>
  );
}

function Item({ label, value }: { label: string; value: string }) {
  return (
    <div className="rounded-image bg-sand-50 p-3">
      <dt className="text-eyebrow text-ink-500">{label}</dt>
      <dd className="mt-1 font-semibold text-ink-900">{value}</dd>
    </div>
  );
}

function Row({ label, value, strong }: { label: string; value: string; strong?: boolean }) {
  return (
    <div className="flex justify-between gap-3">
      <dt className={strong ? "font-semibold text-ink-900" : "text-ink-700"}>{label}</dt>
      <dd className={strong ? "text-lg font-semibold text-ink-900" : "text-ink-900"}>{value}</dd>
    </div>
  );
}
