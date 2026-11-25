"use client";

import { Button } from "@/components/ui/Button";
import { Input } from "@/components/ui/Field";
import { api, ApiError } from "@/lib/api/client";
import type { CouponCheck, CouponServiceType, Locale } from "@/lib/api/types";
import { useCurrency } from "@/lib/currency";
import { AlertCircle, CheckCircle2, Tag, X } from "lucide-react";
import { useLocale, useTranslations } from "next-intl";
import { useEffect, useId, useRef, useState } from "react";

export interface AppliedCoupon {
  code: string;
  discount_eur: number;
}

/** Turns the API's English coupon messages into localised text (the API message is the fallback). */
export function couponMessage(message: string, t: (key: string, values?: Record<string, string>) => string): string {
  const m = message.toLowerCase();
  if (m.includes("does not exist")) return t("errors.invalid");
  if (m.includes("no longer active") || m.includes("expired")) return t("errors.expired");
  if (m.includes("valid from")) return t("errors.notYet");
  if (m.includes("partner bookings")) return t("errors.partner");
  if (m.includes("does not apply")) return t("errors.service");
  if (m.includes("minimum total")) {
    const amount = message.match(/minimum total of (.+?)\.?$/i)?.[1] ?? "";
    return t("errors.minimum", { amount });
  }
  if (m.includes("usage limit")) return t("errors.limit");
  if (m.includes("already used")) return t("errors.used");
  return message || t("errors.invalid");
}

export const isPartnerBlocked = (message: string) => message.toLowerCase().includes("partner bookings");

/**
 * "Have a promo code?" field: POST /coupons/validate with the current service type and total,
 * apply / remove, re-checks automatically when the total changes. The server applies the code
 * again when the booking is created, so this is only a preview.
 */
export function CouponField({
  serviceType,
  totalEur,
  email,
  forPartner,
  auth = false,
  applied,
  onChange,
  serverError,
}: {
  serviceType: CouponServiceType;
  totalEur: number;
  email?: string;
  forPartner?: boolean;
  auth?: boolean;
  applied: AppliedCoupon | null;
  onChange: (coupon: AppliedCoupon | null) => void;
  /** 422 on coupon_code returned by the booking request. */
  serverError?: string | null;
}) {
  const t = useTranslations("coupon");
  const locale = useLocale() as Locale;
  const { format } = useCurrency();
  const id = useId();
  const inputId = `${id}-code`;
  const [open, setOpen] = useState(Boolean(applied));
  const [code, setCode] = useState(applied?.code ?? "");
  const [error, setError] = useState<string | null>(null);
  const [blocked, setBlocked] = useState(false);
  const [busy, setBusy] = useState(false);
  const inputRef = useRef<HTMLInputElement>(null);
  const lastTotal = useRef(totalEur);

  const check = async (value: string): Promise<CouponCheck | null> => {
    setBusy(true);
    setError(null);
    try {
      return await api.validateCoupon({ code: value, service_type: serviceType, total_eur: totalEur, email: email || undefined, for_partner: forPartner || undefined }, { locale, auth });
    } catch (err) {
      if (err instanceof ApiError && err.isValidation) setError(Object.values(err.errors).flat()[0] ?? t("errors.invalid"));
      else if (err instanceof ApiError && err.status === 429) setError(t("errors.tooMany"));
      else setError(t("errors.network"));
      return null;
    } finally {
      setBusy(false);
    }
  };

  const apply = async () => {
    const value = code.trim().toUpperCase();
    if (!value) {
      setError(t("errors.empty"));
      inputRef.current?.focus();
      return;
    }
    const res = await check(value);
    if (!res) return;
    if (res.valid && res.discount_eur > 0) {
      onChange({ code: res.code || value, discount_eur: res.discount_eur });
    } else {
      onChange(null);
      if (forPartner && isPartnerBlocked(res.message)) setBlocked(true);
      setError(couponMessage(res.message, t));
      inputRef.current?.focus();
    }
  };

  // The quote changed (partner flow): check the applied code again against the new total.
  useEffect(() => {
    const previous = lastTotal.current;
    lastTotal.current = totalEur;
    if (!applied || previous === totalEur) return;
    let cancelled = false;
    api
      .validateCoupon({ code: applied.code, service_type: serviceType, total_eur: totalEur, email: email || undefined, for_partner: forPartner || undefined }, { locale, auth })
      .then((res) => {
        if (cancelled) return;
        if (res.valid && res.discount_eur > 0) {
          if (res.discount_eur !== applied.discount_eur) onChange({ code: applied.code, discount_eur: res.discount_eur });
        } else {
          onChange(null);
          setError(couponMessage(res.message, t));
        }
      })
      .catch(() => undefined);
    return () => {
      cancelled = true;
    };
  }, [totalEur, applied, serviceType, email, forPartner, locale, auth, onChange, t]);

  const shownError = serverError ? couponMessage(serverError, t) : error;

  if (blocked) {
    return (
      <p className="flex items-start gap-2 rounded-image bg-ink-100 px-3 py-2 text-sm text-ink-700" role="status">
        <Tag className="mt-0.5 size-4 shrink-0" aria-hidden /> {t("errors.partner")}
      </p>
    );
  }

  if (applied) {
    return (
      <div className="flex flex-col gap-1">
        <div className="flex flex-wrap items-center justify-between gap-2 rounded-image bg-success-100 px-3 py-2 text-sm text-ink-900" role="status">
          <span className="flex items-center gap-2">
            <CheckCircle2 className="size-4 text-success" aria-hidden />
            {t("applied", { code: applied.code, amount: format(applied.discount_eur) })}
          </span>
          <button
            type="button"
            onClick={() => {
              onChange(null);
              setCode("");
              setError(null);
              setOpen(true);
            }}
            className="inline-flex min-h-9 items-center gap-1 rounded-control px-2 font-semibold text-primary-700 hover:underline"
          >
            <X className="size-4" aria-hidden /> {t("remove")}
          </button>
        </div>
        {shownError && (
          <p className="flex items-center gap-1 text-sm text-error" role="alert">
            <AlertCircle className="size-4 shrink-0" aria-hidden /> {shownError}
          </p>
        )}
      </div>
    );
  }

  if (!open) {
    return (
      <button
        type="button"
        onClick={() => {
          setOpen(true);
          window.requestAnimationFrame(() => inputRef.current?.focus());
        }}
        className="inline-flex min-h-11 items-center gap-2 self-start font-semibold text-primary-700 hover:underline"
        aria-expanded={false}
      >
        <Tag className="size-4" aria-hidden /> {t("toggle")}
      </button>
    );
  }

  return (
    <div className="flex flex-col gap-1">
      <label htmlFor={inputId} className="text-sm text-ink-700">{t("label")}</label>
      <div className="flex gap-2">
        <Input
          ref={inputRef}
          id={inputId}
          value={code}
          onChange={(e) => {
            setCode(e.target.value);
            if (error) setError(null);
          }}
          onKeyDown={(e) => {
            if (e.key === "Enter") {
              e.preventDefault();
              void apply();
            }
          }}
          autoComplete="off"
          autoCapitalize="characters"
          spellCheck={false}
          maxLength={40}
          className="h-11 min-w-0 flex-1 uppercase"
          invalid={Boolean(shownError)}
          aria-describedby={shownError ? `${inputId}-error` : undefined}
        />
        <Button type="button" variant="outline" size="md" className="h-11 shrink-0" loading={busy} onClick={() => void apply()}>
          {t("apply")}
        </Button>
      </div>
      {shownError && (
        <p id={`${inputId}-error`} className="flex items-center gap-1 text-sm text-error" role="alert">
          <AlertCircle className="size-4 shrink-0" aria-hidden /> {shownError}
        </p>
      )}
    </div>
  );
}
