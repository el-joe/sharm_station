"use client";

import type { PaymentReturn, PaymentStatus } from "@/lib/api/types";
import { cn } from "@/lib/utils";
import { CheckCircle2, Clock, Info, X, XCircle } from "lucide-react";
import { useTranslations } from "next-intl";

/** Payment statuses that still expect the deposit: a "success" return is not settled until the webhook moves it on. */
const DEPOSIT_DUE: (PaymentStatus | undefined)[] = [undefined, "awaiting_payment", "failed", "proof_rejected"];

export const PAYMENT_RETURNS: PaymentReturn[] = ["success", "cancel", "pending", "failed"];

/** `?payment=` from the hosted checkout (Stripe) or the PayPal return endpoint; null when absent or unknown. */
export function parsePaymentReturn(search: string): PaymentReturn | null {
  const value = new URLSearchParams(search).get("payment");
  return PAYMENT_RETURNS.includes(value as PaymentReturn) ? (value as PaymentReturn) : null;
}

/** The booking's payment status shows the online payment has landed (webhook / capture processed). */
export function paymentSettled(paymentStatus: PaymentStatus | undefined) {
  return !DEPOSIT_DUE.includes(paymentStatus);
}

/**
 * Banner shown on /booking/[reference] after returning from the payment provider:
 * success (settled, or still confirming while the page polls the booking), pending, cancel and failed.
 */
export function PaymentReturnBanner({
  state,
  settled,
  polling,
  onCheck,
  onDismiss,
}: {
  state: PaymentReturn;
  settled: boolean;
  polling: boolean;
  onCheck: () => void;
  onDismiss: () => void;
}) {
  const t = useTranslations("paymentReturn");
  const waiting = (state === "success" || state === "pending") && !settled;
  const kind: "success" | "confirming" | "pending" | "cancel" | "failed" =
    (state === "success" || state === "pending") && settled ? "success" : waiting ? (polling ? "confirming" : "pending") : state === "cancel" ? "cancel" : "failed";

  const look = {
    success: { box: "border-success/30 bg-success-100", icon: <CheckCircle2 className="size-6 text-success" aria-hidden /> },
    confirming: { box: "border-info/30 bg-primary-100", icon: <span className="size-5 animate-spin rounded-full border-2 border-primary-500 border-t-transparent" aria-hidden /> },
    pending: { box: "border-warning/30 bg-warning-100", icon: <Clock className="size-6 text-warning" aria-hidden /> },
    cancel: { box: "border-ink-100 bg-sand-50", icon: <Info className="size-6 text-ink-700" aria-hidden /> },
    failed: { box: "border-error/30 bg-error-100", icon: <XCircle className="size-6 text-error" aria-hidden /> },
  }[kind];

  return (
    <div className={cn("flex items-start gap-3 rounded-card border p-5", look.box)} role={kind === "failed" ? "alert" : "status"} data-payment-return={kind}>
      <span className="grid size-8 shrink-0 place-items-center">{look.icon}</span>
      <div className="min-w-0 flex-1">
        <p className="font-semibold text-ink-900">{t(`${kind}Title`)}</p>
        <p className="mt-1 text-sm text-ink-700">{t(`${kind}Text`)}</p>
        {kind === "pending" && (
          <button type="button" onClick={onCheck} className="mt-2 text-sm font-semibold text-primary-700 underline">
            {t("checkAgain")}
          </button>
        )}
      </div>
      {kind !== "confirming" && (
        <button type="button" onClick={onDismiss} className="grid size-9 shrink-0 place-items-center rounded-full text-ink-700 hover:bg-white/60" aria-label={t("dismiss")}>
          <X className="size-5" aria-hidden />
        </button>
      )}
    </div>
  );
}
