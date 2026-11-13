"use client";

import { useLocale } from "next-intl";
import { createContext, useCallback, useContext, useMemo, useSyncExternalStore, type ReactNode } from "react";
import type { Currency, CurrencyCode } from "./api/types";

export const CURRENCY_COOKIE = "ssv_currency";
const CURRENCY_EVENT = "ssv:currency";
const SUPPORTED: CurrencyCode[] = ["EUR", "USD", "GBP", "EGP"];

function readCookie(): CurrencyCode {
  if (typeof document === "undefined") return "EUR";
  const match = document.cookie.match(new RegExp(`(?:^|; )${CURRENCY_COOKIE}=([^;]+)`));
  const value = match?.[1] as CurrencyCode | undefined;
  return value && SUPPORTED.includes(value) ? value : "EUR";
}

function subscribe(callback: () => void) {
  window.addEventListener(CURRENCY_EVENT, callback);
  window.addEventListener("focus", callback);
  return () => {
    window.removeEventListener(CURRENCY_EVENT, callback);
    window.removeEventListener("focus", callback);
  };
}

interface CurrencyContextValue {
  currency: CurrencyCode;
  currencies: Currency[];
  setCurrency: (code: CurrencyCode) => void;
  convert: (eur: number) => number;
  format: (eur: number, options?: { decimals?: boolean }) => string;
}

const CurrencyContext = createContext<CurrencyContextValue | null>(null);

export function CurrencyProvider({ currencies, children }: { currencies: Currency[]; children: ReactNode }) {
  const locale = useLocale();
  // Server render and hydration use EUR; the visitor's cookie is applied right after hydration.
  const currency = useSyncExternalStore(subscribe, readCookie, () => "EUR" as CurrencyCode);

  const setCurrency = useCallback((code: CurrencyCode) => {
    document.cookie = `${CURRENCY_COOKIE}=${code}; path=/; max-age=31536000; SameSite=Lax`;
    window.dispatchEvent(new Event(CURRENCY_EVENT));
  }, []);

  const value = useMemo<CurrencyContextValue>(() => {
    const rate = currencies.find((c) => c.code === currency)?.rate_to_eur ?? 1;
    const convert = (eur: number) => eur * rate;
    const format = (eur: number, options?: { decimals?: boolean }) => {
      const amount = convert(eur);
      const whole = Math.abs(amount - Math.round(amount)) < 0.005;
      const decimals = options?.decimals ?? !whole;
      return new Intl.NumberFormat(locale, {
        style: "currency",
        currency,
        currencyDisplay: currency === "EGP" ? "code" : "narrowSymbol",
        minimumFractionDigits: decimals ? 2 : 0,
        maximumFractionDigits: decimals ? 2 : 0,
      }).format(amount);
    };
    return { currency, currencies: currencies.filter((c) => c.is_enabled), setCurrency, convert, format };
  }, [currencies, currency, locale, setCurrency]);

  return <CurrencyContext.Provider value={value}>{children}</CurrencyContext.Provider>;
}

export function useCurrency() {
  const ctx = useContext(CurrencyContext);
  if (!ctx) throw new Error("useCurrency must be used inside <CurrencyProvider>");
  return ctx;
}

/** Renders a EUR amount in the visitor's currency. */
export function Price({ amount, className, decimals }: { amount: number; className?: string; decimals?: boolean }) {
  const { format } = useCurrency();
  return <span className={className}>{format(amount, { decimals })}</span>;
}
