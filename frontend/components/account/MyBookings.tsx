"use client";

import { AppImage } from "@/components/ui/AppImage";
import { buttonClasses } from "@/components/ui/Button";
import { EmptyState } from "@/components/ui/EmptyState";
import { StatusBadge } from "@/components/ui/StatusBadge";
import { Link } from "@/i18n/navigation";
import { api, ApiError } from "@/lib/api/client";
import type { BookingDetail, Locale } from "@/lib/api/types";
import { useAuth } from "@/lib/auth";
import { useCurrency } from "@/lib/currency";
import { useToday } from "@/lib/hooks";
import { cn, parseISODate } from "@/lib/utils";
import { useQuery } from "@tanstack/react-query";
import { CalendarX2, Luggage } from "lucide-react";
import { useFormatter, useLocale, useTranslations } from "next-intl";
import { useEffect, useState } from "react";
import { bookingDate, bookingImage } from "./bookingVisuals";

type Tab = "upcoming" | "past" | "cancelled";

function tabOf(b: BookingDetail, today: string | null): Tab {
  if (b.status === "cancelled" || b.status === "refunded") return "cancelled";
  const date = bookingDate(b);
  if (b.status === "completed" || (today && date && date < today)) return "past";
  return "upcoming";
}

/** /account/bookings: GET /me/bookings with the customer session (error state with retry when the API cannot be reached). */
export function MyBookings() {
  const t = useTranslations("account.bookings");
  const tg = useTranslations("guard");
  const locale = useLocale() as Locale;
  const { hasSession, signOut } = useAuth();
  const today = useToday();
  const [tab, setTab] = useState<Tab>("upcoming");

  const query = useQuery({
    queryKey: ["account", "bookings", locale],
    queryFn: ({ signal }) => api.getMyBookings({ locale, signal }),
    enabled: hasSession,
    retry: false,
  });

  useEffect(() => {
    if (query.error instanceof ApiError && query.error.status === 401) void signOut();
  }, [query.error, signOut]);

  if (query.isPending) {
    return (
      <div className="mt-8 flex flex-col gap-4" aria-busy="true">
        {[0, 1].map((i) => (
          <div key={i} className="h-40 animate-pulse rounded-card bg-ink-100" />
        ))}
      </div>
    );
  }

  if (query.isError || query.data.status === "error") {
    return <EmptyState className="mt-8" tone="warning" icon={<CalendarX2 aria-hidden />} title={t("error")} action={<button type="button" className={buttonClasses("primary", "md")} onClick={() => query.refetch()}>{tg("retry")}</button>} />;
  }

  const bookings = query.data.data;
  const counts: Record<Tab, number> = { upcoming: 0, past: 0, cancelled: 0 };
  bookings.forEach((b) => counts[tabOf(b, today)]++);
  const shown = bookings.filter((b) => tabOf(b, today) === tab);
  const tabs: Tab[] = ["upcoming", "past", "cancelled"];

  return (
    <div className="mt-6">
      <div role="tablist" aria-label={t("tabsLabel")} className="flex flex-wrap gap-2">
        {tabs.map((key) => (
          <button
            key={key}
            role="tab"
            type="button"
            id={`tab-${key}`}
            aria-selected={tab === key}
            aria-controls="bookings-panel"
            onClick={() => setTab(key)}
            className={cn(
              "inline-flex h-10 items-center gap-2 rounded-control border px-4 text-sm font-semibold",
              tab === key ? "border-primary-700 bg-primary-100 text-primary-700" : "border-ink-300 bg-white text-ink-900 hover:bg-ink-100",
            )}
          >
            {t(`tabs.${key}`)}
            <span className="rounded-pill bg-white px-1.5 text-xs text-ink-700">{counts[key]}</span>
          </button>
        ))}
      </div>
      <div id="bookings-panel" role="tabpanel" aria-labelledby={`tab-${tab}`} className="mt-6">
        {shown.length === 0 ? (
          <EmptyState
            icon={<Luggage aria-hidden />}
            title={t("emptyTitle")}
            text={tab === "upcoming" ? t("emptyUpcoming") : tab === "past" ? t("emptyPast") : t("emptyCancelled")}
            action={tab === "upcoming" ? <Link href="/trips" className={buttonClasses("cta", "md")}>{t("browse")}</Link> : undefined}
          />
        ) : (
          <ul className="flex flex-col gap-4">
            {shown.map((b) => (
              <li key={b.reference}>
                <BookingRow booking={b} />
              </li>
            ))}
          </ul>
        )}
      </div>
    </div>
  );
}

function BookingRow({ booking: b }: { booking: BookingDetail }) {
  const t = useTranslations("account.bookings");
  const ts = useTranslations("service");
  const tb = useTranslations("booking");
  const format = useFormatter();
  const { format: money } = useCurrency();
  const date = bookingDate(b);
  const meta = [
    b.reference,
    date ? format.dateTime(parseISODate(date), { day: "numeric", month: "short", year: "numeric" }) : null,
    b.booking_type ? tb(b.booking_type) : ts(b.service_type),
    t("party", { adults: b.adults, children: b.children }),
  ].filter(Boolean);
  return (
    <article className="flex flex-col gap-4 rounded-card border border-ink-100 bg-white p-3 shadow-card sm:flex-row sm:items-center sm:p-4">
      <div className="relative aspect-[16/10] w-full shrink-0 overflow-hidden rounded-image bg-ink-100 sm:aspect-[4/3] sm:w-48">
        <AppImage src={bookingImage(b)} alt="" fill sizes="(min-width: 640px) 192px, 100vw" className="object-cover" />
      </div>
      <div className="flex min-w-0 flex-1 flex-col gap-2">
        <StatusBadge status={b.status} className="self-start" />
        <p className="text-sm text-ink-500">{meta.join(" · ")}</p>
        <h3 className="font-display text-xl font-semibold text-ink-900">{b.product_title}</h3>
        <p className="text-sm text-ink-700">
          {money(b.totals.total)} · {t("paid", { deposit: money(b.totals.deposit), balance: money(b.totals.balance) })}
        </p>
        <div className="mt-1 flex flex-wrap gap-2">
          {b.status === "awaiting_payment" && (
            <Link href={`/booking/${b.reference}`} className={buttonClasses("cta", "sm")}>{t("pay")}</Link>
          )}
          <Link href={`/account/bookings/${b.reference}`} className={buttonClasses("outline", "sm")}>{t("details")}</Link>
        </div>
      </div>
    </article>
  );
}
