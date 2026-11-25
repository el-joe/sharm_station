"use client";

import { Button, buttonClasses } from "@/components/ui/Button";
import { Textarea } from "@/components/ui/Field";
import { Link } from "@/i18n/navigation";
import { api, ApiError } from "@/lib/api/client";
import type { BookingCancellation, BookingPayment, Locale } from "@/lib/api/types";
import { useCurrency } from "@/lib/currency";
import { cn } from "@/lib/utils";
import { AlertCircle, CalendarX2, CheckCircle2, Clock, X, XCircle } from "lucide-react";
import { useFormatter, useLocale, useTranslations } from "next-intl";
import { useId, useRef, useState, type FormEvent } from "react";

const MIN = 5;
const MAX = 1000;

/**
 * Cancellation request (SRS 12.4) for the booking page, the account and the partner panel:
 * a "Request cancellation" button opening a dialog (reason + policy summary) ->
 * POST /bookings/{reference}/cancellation-request, then the request status
 * (pending / approved with refund / declined with note). Nothing renders while the
 * API has not sent a `cancellation` block; the button hides when `can_request` is false.
 */
export function CancellationRequest({
  reference,
  cancellation,
  refunds,
  accessToken,
  auth = false,
  onChange,
  className,
}: {
  reference: string;
  cancellation: BookingCancellation | null | undefined;
  /** Refund rows of the booking's payments (type "refund"): their outcome is shown under an approved request. */
  refunds?: BookingPayment[];
  /** Booking link token (?token=) for guests. */
  accessToken?: string | null;
  /** Signed-in customer or partner: sent through /api/proxy with the session cookie. */
  auth?: boolean;
  onChange?: (c: BookingCancellation) => void;
  className?: string;
}) {
  const t = useTranslations("cancellationRequest");
  const format = useFormatter();
  const locale = useLocale() as Locale;
  const { format: money } = useCurrency();
  const [local, setLocal] = useState<BookingCancellation | null>(null);
  const [reason, setReason] = useState("");
  const [error, setError] = useState<string | null>(null);
  const [fieldError, setFieldError] = useState<string | null>(null);
  const [busy, setBusy] = useState(false);
  const [notice, setNotice] = useState<string | null>(null);
  const dialogRef = useRef<HTMLDialogElement>(null);
  const triggerRef = useRef<HTMLButtonElement>(null);
  const id = useId();

  const c = local ?? cancellation ?? null;
  if (!c) return null;

  const dateOnly = (iso: string) => format.dateTime(new Date(iso), { day: "numeric", month: "long", year: "numeric" });
  const date = (iso: string | null) => (iso ? format.dateTime(new Date(iso), { day: "numeric", month: "long", year: "numeric", hour: "2-digit", minute: "2-digit" }) : "");

  const open = () => {
    setError(null);
    setFieldError(null);
    dialogRef.current?.showModal();
  };
  const close = () => {
    dialogRef.current?.close();
    triggerRef.current?.focus();
  };

  const submit = async (e: FormEvent) => {
    e.preventDefault();
    const value = reason.trim();
    if (value.length < MIN) {
      setFieldError(value ? t("errors.min", { count: MIN }) : t("errors.required"));
      return;
    }
    setBusy(true);
    setError(null);
    setFieldError(null);
    try {
      const res = await api.requestCancellation(reference, value, { locale, accessToken, auth });
      const next: BookingCancellation = res.cancellation ?? { ...c, can_request: false, status: "pending", requested_at: new Date().toISOString(), reason: value };
      setLocal(next);
      setNotice(t("sent"));
      onChange?.(next);
      dialogRef.current?.close();
    } catch (err) {
      if (err instanceof ApiError && err.isValidation) setFieldError(err.errors.reason?.[0] ?? (Object.values(err.errors).flat()[0] || err.message));
      else if (err instanceof ApiError && (err.status === 401 || err.status === 403 || err.status === 404)) setError(t("errors.forbidden"));
      else if (err instanceof ApiError && err.status === 429) setError(t("errors.tooMany"));
      else setError(t("errors.network"));
    } finally {
      setBusy(false);
    }
  };

  const status = c.status;
  return (
    <section className={cn("rounded-card border border-ink-100 bg-white p-5 shadow-card", className)} aria-labelledby={`${id}-title`}>
      <h2 id={`${id}-title`} className="text-h4 flex items-center gap-2 text-ink-900">
        <CalendarX2 className="size-5 text-ink-500" aria-hidden /> {t("title")}
      </h2>

      {notice && (
        <p className="mt-3 rounded-image bg-success-100 p-3 text-sm text-ink-900" role="status">
          {notice}
        </p>
      )}

      {status === "pending" && (
        <div className="mt-3 flex items-start gap-3 rounded-image bg-warning-100 p-4 text-sm">
          <Clock className="mt-0.5 size-5 shrink-0 text-warning" aria-hidden />
          <div>
            <p className="font-semibold text-ink-900">{t("pendingTitle")}</p>
            <p className="mt-1 text-ink-700">{t("pendingText", { date: date(c.requested_at) })}</p>
            {c.reason && <p className="mt-2 border-s-2 border-ink-300 ps-3 text-ink-700">{t("yourReason", { reason: c.reason })}</p>}
          </div>
        </div>
      )}
      {status === "approved" && (
        <div className="mt-3 flex items-start gap-3 rounded-image bg-success-100 p-4 text-sm">
          <CheckCircle2 className="mt-0.5 size-5 shrink-0 text-success" aria-hidden />
          <div>
            <p className="font-semibold text-ink-900">{t("approvedTitle")}</p>
            <p className="mt-1 text-ink-700">
              {c.refund_amount != null && c.refund_amount > 0 ? t("approvedRefund", { amount: money(c.refund_amount, { decimals: true }) }) : t("approvedNoRefund")}
            </p>
            {refunds && refunds.length > 0 && (
              <ul className="mt-2 flex flex-col gap-1 text-ink-700" aria-label={t("refundsLabel")}>
                {refunds.map((r, i) => {
                  const amount = money(r.amount, { decimals: true });
                  return (
                    <li key={i} data-refund-status={r.status}>
                      {r.status === "succeeded" || r.status === "refunded"
                        ? t("refundSent", { amount, date: r.paid_at ? dateOnly(r.paid_at) : "" })
                        : r.status === "failed"
                          ? t("refundFailed", { amount })
                          : t("refundPending", { amount })}
                    </li>
                  );
                })}
              </ul>
            )}
            {c.decided_at && <p className="mt-1 text-ink-500">{t("decidedAt", { date: date(c.decided_at) })}</p>}
            {c.decision_note && <p className="mt-2 border-s-2 border-ink-300 ps-3 text-ink-700">{c.decision_note}</p>}
          </div>
        </div>
      )}
      {status === "declined" && (
        <div className="mt-3 flex items-start gap-3 rounded-image bg-error-100 p-4 text-sm">
          <XCircle className="mt-0.5 size-5 shrink-0 text-error" aria-hidden />
          <div>
            <p className="font-semibold text-ink-900">{t("declinedTitle")}</p>
            {c.decided_at && <p className="mt-1 text-ink-500">{t("decidedAt", { date: date(c.decided_at) })}</p>}
            <p className="mt-2 border-s-2 border-ink-300 ps-3 text-ink-700">{c.decision_note || t("declinedNoNote")}</p>
          </div>
        </div>
      )}

      {c.can_request ? (
        <>
          <p className="mt-2 text-sm text-ink-700">{status === "declined" ? t("declinedAgainText") : t("intro")}</p>
          <button ref={triggerRef} type="button" className={buttonClasses("outline", "md", "mt-4 w-full sm:w-auto")} onClick={open} aria-haspopup="dialog">
            {status === "declined" ? t("requestAgain") : t("button")}
          </button>
        </>
      ) : (
        !status && <p className="mt-2 text-sm text-ink-700">{t("notAvailable")}</p>
      )}

      <dialog
        ref={dialogRef}
        aria-labelledby={`${id}-dialog-title`}
        aria-describedby={`${id}-dialog-text`}
        className="m-auto w-[min(560px,calc(100vw-32px))] rounded-card bg-white p-0 shadow-raised backdrop:bg-brand-navy-900/50"
        onClose={() => triggerRef.current?.focus()}
      >
        <form onSubmit={submit} noValidate className="flex flex-col gap-4 p-5 md:p-6">
          <div className="flex items-start justify-between gap-3">
            <h3 id={`${id}-dialog-title`} className="text-h4 text-ink-900">
              {t("dialogTitle", { reference })}
            </h3>
            <button type="button" onClick={close} className="grid size-10 shrink-0 place-items-center rounded-full text-ink-700 hover:bg-ink-100" aria-label={t("close")}>
              <X className="size-5" aria-hidden />
            </button>
          </div>
          <div id={`${id}-dialog-text`} className="rounded-image bg-sand-50 p-4 text-sm text-ink-700">
            <p className="font-semibold text-ink-900">{t("policyTitle")}</p>
            <p className="mt-1">{t("policyText")}</p>
            <Link href="/cancellation-policy" target="_blank" className="mt-2 inline-block font-semibold text-primary-700 underline">
              {t("policyLink")}
            </Link>
          </div>
          <div className="flex flex-col gap-1">
            <label htmlFor={`${id}-reason`} className="text-sm text-ink-700">
              {t("reason")}
              <span className="text-error" aria-hidden>
                {" "}
                *
              </span>
            </label>
            <Textarea
              id={`${id}-reason`}
              value={reason}
              onChange={(e) => {
                setReason(e.target.value);
                if (fieldError) setFieldError(null);
              }}
              required
              aria-required
              minLength={MIN}
              maxLength={MAX}
              rows={4}
              invalid={Boolean(fieldError)}
              aria-describedby={fieldError ? `${id}-reason-error` : `${id}-reason-hint`}
            />
            {fieldError ? (
              <p id={`${id}-reason-error`} className="flex items-center gap-1 text-sm text-error" role="alert">
                <AlertCircle className="size-4 shrink-0" aria-hidden /> {fieldError}
              </p>
            ) : (
              <p id={`${id}-reason-hint`} className="text-xs text-ink-500">
                {t("reasonHint", { count: reason.trim().length, max: MAX })}
              </p>
            )}
          </div>
          {error && (
            <p className="rounded-image bg-error-100 p-3 text-sm text-error" role="alert">
              {error}
            </p>
          )}
          <p className="text-xs text-ink-500">{t("notAutomatic")}</p>
          <div className="flex flex-col-reverse gap-3 sm:flex-row sm:justify-end">
            <Button type="button" variant="ghost" onClick={close}>
              {t("keep")}
            </Button>
            <Button type="submit" variant="danger" loading={busy}>
              {t("submit")}
            </Button>
          </div>
        </form>
      </dialog>
    </section>
  );
}
