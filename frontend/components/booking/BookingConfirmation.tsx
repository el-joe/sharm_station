"use client";

import { buttonClasses } from "@/components/ui/Button";
import { Link } from "@/i18n/navigation";
import { api } from "@/lib/api/client";
import { StatusBadge } from "@/components/ui/StatusBadge";
import type { BookingDetail, BookingStatus, Locale } from "@/lib/api/types";
import { useAuth } from "@/lib/auth";
import { useLocationSearch } from "@/lib/hooks";
import { clearDraft, readBooking, type StoredBooking } from "@/lib/checkout";
import { bookingTitleKind } from "@/lib/booking-title";
import { SITE_NAME } from "@/lib/site";
import { parseISODate, round2 } from "@/lib/utils";
import { useQuery, useQueryClient } from "@tanstack/react-query";
import { CheckCircle2, Mail } from "lucide-react";
import { useFormatter, useLocale, useTranslations } from "next-intl";
import { use, useEffect, useMemo, useState, useSyncExternalStore } from "react";
import { CancellationRequest } from "./CancellationRequest";
import { PaymentHistory } from "./PaymentHistory";
import { parsePaymentReturn, paymentSettled, PaymentReturnBanner } from "./PaymentReturnBanner";
import { BalancePayment, balanceDue, PaymentSection } from "./PaymentSection";
import { BookingRooms } from "./BookingRooms";
import { PriceSummary } from "./PriceSummary";
import { VoucherDownload } from "./VoucherDownload";

/** Booking refetches after a gateway return while the webhook lands (every POLL_MS, at most POLL_MAX loads). */
const POLL_MS = 2500;
const POLL_MAX = 7;

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
  const { hasSession, status: authStatus } = useAuth();
  // Back from the payment provider: ?payment=success|cancel|pending|failed (Stripe return URL / PayPal return).
  const paymentReturn = parsePaymentReturn(useLocationSearch());
  const [returnDismissed, setReturnDismissed] = useState(false);
  const awaitingWebhook = (paymentReturn === "success" || paymentReturn === "pending") && !returnDismissed;
  const queryClient = useQueryClient();
  const bookingKey = ["booking", reference, locale, accessToken, hasSession];
  const remote = useQuery({
    queryKey: bookingKey,
    queryFn: () => api.booking(reference, { locale, accessToken, auth: hasSession }),
    enabled: storedRaw !== undefined && authStatus !== "loading",
    // Poll a few times while the gateway webhook confirms the payment.
    refetchInterval: (q) => (awaitingWebhook && !paymentSettled(q.state.data?.payment_status) && q.state.dataUpdateCount < POLL_MAX ? POLL_MS : false),
  });
  const settled = paymentSettled(remote.data?.payment_status);
  // `isFetching` is read so each poll re-renders this component, also when the booking did not change.
  const loads = remote.isFetching ? 0 : (queryClient.getQueryState(bookingKey)?.dataUpdateCount ?? 0);
  const polling = awaitingWebhook && !settled && (remote.isPending || remote.isFetching || loads < POLL_MAX);
  const dismissReturn = () => {
    setReturnDismissed(true);
    try {
      const url = new URL(window.location.href);
      url.searchParams.delete("payment");
      window.history.replaceState(window.history.state, "", url);
    } catch {
      /* ignore */
    }
  };

  useEffect(() => {
    if (stored) clearDraft();
  }, [stored]);

  // The server title is neutral when it could not read the booking (no session cookie / ?token=):
  // follow the status once it is known here (received / confirmed / cancelled).
  const loadedStatus: BookingStatus | null = remote.data?.status ?? (stored ? "awaiting_payment" : null);
  useEffect(() => {
    const kind = bookingTitleKind(loadedStatus);
    if (!kind) return;
    const key = kind === "confirmed" ? "metaTitleConfirmed" : kind === "cancelled" ? "metaTitleCancelled" : "metaTitleReceived";
    document.title = `${t(key, { reference })} | ${SITE_NAME}`;
  }, [loadedStatus, reference, t]);

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
  // A promo code was applied by the server: show it against the quote the visitor saw.
  const storedDetail = stored && "service_type" in stored ? (stored as unknown as BookingDetail) : null;
  const couponCode = remote.data?.coupon_code ?? storedDetail?.coupon_code ?? null;
  const couponAmount = stored && couponCode ? round2(stored.draft.quote.total - (total ?? stored.draft.quote.total)) : 0;
  const couponDiscount = couponCode && couponAmount > 0 ? { code: couponCode, amount: couponAmount } : null;
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

  const b = remote.data ?? null;
  // Do not offer "Pay now" again while a returned payment is still being confirmed.
  const hidePayment = awaitingWebhook && !settled;

  return (
    <div className="grid gap-8 lg:grid-cols-[minmax(0,1fr)_400px]">
      <div className="flex flex-col gap-6">
        {paymentReturn && !returnDismissed && <PaymentReturnBanner state={paymentReturn} settled={settled} polling={polling} onCheck={() => void remote.refetch()} onDismiss={dismissReturn} />}
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
              <dd className="mt-1 flex flex-wrap gap-2">
                <StatusBadge status={status} />
                {b?.payment_status && b.payment_status !== status && <StatusBadge status={b.payment_status} />}
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

        {!hidePayment && (
        <PaymentSection
          reference={reference}
          accessToken={accessToken}
          auth={hasSession}
          status={status}
          deposit={deposit ?? 0}
          total={total ?? 0}
          bookingCurrency={remote.data?.totals.currency ?? stored?.totals.currency ?? "EUR"}
          dueAt={remote.data?.payment_due_at ?? null}
          createdAt={remote.data?.created_at ?? (stored?.draft.created_at ? new Date(stored.draft.created_at).toISOString() : null)}
          fallbackOptions={options}
        />
        )}

        {b && !hidePayment && balanceDue(b) > 0 && (
          <BalancePayment reference={reference} accessToken={accessToken} auth={hasSession} due={balanceDue(b)} bookingCurrency={b.totals.currency} balanceDueOn={b.balance_due_on} />
        )}

        {b?.rooms && b.rooms.length > 0 && <BookingRooms rooms={b.rooms} pricesHidden={b.prices_hidden} />}

        {b?.voucher_available && <VoucherDownload reference={reference} accessToken={accessToken} auth={hasSession} variants={[b.prices_hidden ? "client" : "prices"]} tone="cta" />}

        {b && !b.prices_hidden && (b.payments?.length || status !== "awaiting_payment") ? (
          <PaymentHistory payments={b.payments ?? []} paid={b.paid} due={b.due} balanceDueOn={b.balance_due_on} />
        ) : null}

        <CancellationRequest
          reference={reference}
          cancellation={remote.data?.cancellation ?? storedDetail?.cancellation ?? null}
          refunds={b?.payments?.filter((p) => p.type === "refund")}
          accessToken={accessToken}
          auth={hasSession}
          onChange={() => void remote.refetch()}
        />
      </div>
      {stored && (
        <aside className="lg:sticky lg:top-28 lg:self-start">
          <div className="rounded-card border border-ink-100 bg-white p-6 shadow-card">
            <PriceSummary quote={stored.draft.quote} discount={couponDiscount} />
          </div>
        </aside>
      )}
    </div>
  );
}
