"use client";

import { buttonClasses } from "@/components/ui/Button";
import { Link } from "@/i18n/navigation";
import { api } from "@/lib/api/client";
import { StatusBadge } from "@/components/ui/StatusBadge";
import type { BookingStatus, Locale } from "@/lib/api/types";
import { useAuth } from "@/lib/auth";
import { clearDraft, readBooking, type StoredBooking } from "@/lib/checkout";
import { parseISODate } from "@/lib/utils";
import { useQuery } from "@tanstack/react-query";
import { CheckCircle2, Mail } from "lucide-react";
import { useFormatter, useLocale, useTranslations } from "next-intl";
import { use, useEffect, useMemo, useSyncExternalStore } from "react";
import { PaymentSection } from "./PaymentSection";
import { PriceSummary } from "./PriceSummary";

/** Booking confirmation (status: awaiting payment) with deposit due and payment options. */
export function BookingConfirmation({ params }: { params: Promise<{ reference: string }> }) {
  const { reference } = use(params);
  const t = useTranslations("confirmation");
  const format = useFormatter();
  const locale = useLocale() as Locale;

  const storedRaw = useSyncExternalStore(
    () => () => {},
    () => {
      try {
        return sessionStorage.getItem(`ssv_booking_${reference}`);
      } catch {
        return null;
      }
    },
    () => undefined,
  );
  const stored: StoredBooking | null = useMemo(() => (storedRaw ? readBooking(reference) : null), [storedRaw, reference]);
  // Booking links (e.g. from the confirmation email) carry the access token as ?token=, so the
  // page also works on another device or after the checkout session has ended.
  const urlToken = useSyncExternalStore(
    () => () => {},
    () => new URLSearchParams(window.location.search).get("token"),
    () => null,
  );
  const storedToken = stored && "access_token" in stored ? (stored.access_token ?? null) : null;
  const accessToken = storedToken ?? urlToken;
  const { token: authToken, status: authStatus } = useAuth();
  const remote = useQuery({
    queryKey: ["booking", reference, locale, accessToken, authToken],
    queryFn: () => api.booking(reference, { locale, accessToken, token: authToken }),
    enabled: storedRaw !== undefined && authStatus !== "loading",
  });

  useEffect(() => {
    if (stored) clearDraft();
  }, [stored]);

  if (storedRaw === undefined || (remote.isPending && !stored)) {
    return <div className="h-[500px] animate-pulse rounded-card bg-ink-100" aria-hidden />;
  }

  const title = remote.data?.product_title ?? stored?.draft.product.title;
  const deposit = remote.data?.totals.deposit ?? stored?.totals.deposit;
  const total = remote.data?.totals.total ?? stored?.totals.total;
  const email = remote.data?.lead.email ?? stored?.lead.email;
  const options = remote.data?.payment_options ?? stored?.payment_options ?? [];
  const status: BookingStatus = remote.data?.status ?? "awaiting_payment";
  const isConfirmed = status === "confirmed" || status === "fully_paid" || status === "completed";
  const date = stored?.draft.travel_date ?? stored?.draft.check_in ?? remote.data?.travel_date ?? remote.data?.check_in;

  if (!title) {
    return (
      <div className="rounded-card border border-ink-100 bg-white p-8 text-center shadow-card">
        <h1 className="text-h2 text-ink-900">{t("lookupTitle")}</h1>
        <p className="mt-2 text-ink-700">{t("lookupText", { reference })}</p>
        <Link href="/" className={buttonClasses("cta", "md", "mt-6")}>{t("home")}</Link>
      </div>
    );
  }

  return (
    <div className="grid gap-8 lg:grid-cols-[minmax(0,1fr)_400px]">
      <div className="flex flex-col gap-6">
        <div className="rounded-card border border-ink-100 bg-white p-6 shadow-card md:p-8">
          <span className="grid size-14 place-items-center rounded-full bg-success-100 text-success">
            <CheckCircle2 className="size-8" aria-hidden />
          </span>
          <h1 className="text-h2 mt-4 text-ink-900">{isConfirmed ? t("titleConfirmed") : t("title")}</h1>
          <p className="mt-2 text-ink-700">{isConfirmed ? t("textConfirmed") : t("text")}</p>
          <dl className="mt-6 grid gap-4 sm:grid-cols-2">
            <div className="rounded-image bg-sand-50 p-4">
              <dt className="text-eyebrow text-ink-500">{t("reference")}</dt>
              <dd className="mt-1 font-mono text-xl font-semibold text-ink-900">{reference}</dd>
            </div>
            <div className="rounded-image bg-sand-50 p-4">
              <dt className="text-eyebrow text-ink-500">{t("status")}</dt>
              <dd className="mt-1">
                <StatusBadge status={status} />
              </dd>
            </div>
            <div className="rounded-image bg-sand-50 p-4 sm:col-span-2">
              <dt className="text-eyebrow text-ink-500">{t("product")}</dt>
              <dd className="mt-1 font-semibold text-ink-900">
                {title}
                {date && <span className="font-normal text-ink-700"> · {format.dateTime(parseISODate(date), { day: "numeric", month: "long", year: "numeric" })}</span>}
              </dd>
            </div>
          </dl>
          {email && (
            <p className="mt-6 flex items-center gap-2 text-sm text-ink-700">
              <Mail className="size-4" aria-hidden /> {t("emailSent", { email })}
            </p>
          )}
        </div>

        <PaymentSection
          reference={reference}
          accessToken={accessToken}
          authToken={authToken}
          status={status}
          deposit={deposit ?? 0}
          total={total ?? 0}
          bookingCurrency={remote.data?.totals.currency ?? stored?.totals.currency ?? "EUR"}
          dueAt={remote.data?.payment_due_at ?? null}
          createdAt={remote.data?.created_at ?? (stored?.draft.created_at ? new Date(stored.draft.created_at).toISOString() : null)}
          fallbackOptions={options}
        />
      </div>
      {stored && (
        <aside className="lg:sticky lg:top-28 lg:self-start">
          <div className="rounded-card border border-ink-100 bg-white p-6 shadow-card">
            <PriceSummary quote={stored.draft.quote} />
          </div>
        </aside>
      )}
    </div>
  );
}
