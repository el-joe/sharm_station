"use client";

import { Button } from "@/components/ui/Button";
import { CopyButton } from "@/components/ui/CopyButton";
import { Field, Input } from "@/components/ui/Field";
import { FileUpload, type UploadState } from "@/components/ui/FileUpload";
import { api, ApiError } from "@/lib/api/client";
import type { BookingStatus, CurrencyCode, Locale, PaymentMethod } from "@/lib/api/types";
import { useCurrency } from "@/lib/currency";
import { useToday } from "@/lib/hooks";
import { whatsappHref } from "@/lib/site";
import { cn } from "@/lib/utils";
import { zodResolver } from "@hookform/resolvers/zod";
import { useQuery } from "@tanstack/react-query";
import { Banknote, CheckCircle2, Clock, CreditCard, Eye, Lock, MessageCircle } from "lucide-react";
import { useFormatter, useLocale, useTranslations } from "next-intl";
import { useId, useMemo, useState, useSyncExternalStore } from "react";
import { useForm } from "react-hook-form";
import { z } from "zod";

const PROOF_KEY = (ref: string) => `ssv_proof_${ref}`;
const noop = () => () => {};

export interface PaymentSectionProps {
  reference: string;
  accessToken: string | null;
  /** Bearer token when the visitor is signed in (owners can open their booking without the access token). */
  authToken?: string | null;
  status: BookingStatus;
  /** Deposit and total in EUR. */
  deposit: number;
  total: number;
  /** Currency chosen at checkout (amounts for manual methods are shown in it when accepted). */
  bookingCurrency: CurrencyCode;
  dueAt?: string | null;
  createdAt?: string | null;
  /** payment_options from the booking, shown when the payment endpoints are not live yet. */
  fallbackOptions: { code: string; name: string; type: "online" | "manual" }[];
}

/**
 * "Pay now" step on /booking/[reference] (SRS 10):
 *  GET  /bookings/{ref}/payment-methods?token=  -> { data: PaymentMethod[] }
 *  POST /bookings/{ref}/checkout?token=        {payment_method_id} -> { redirect_url }
 *  POST /bookings/{ref}/proofs?token=          multipart files[], transaction_number, transfer_date, amount, sender_name
 * When the methods cannot be loaded (API unreachable) an error block offers a retry, WhatsApp
 * and the booking's payment options.
 */
export function PaymentSection(props: PaymentSectionProps) {
  const { reference, accessToken, authToken, status, deposit, fallbackOptions } = props;
  const t = useTranslations("payment");
  const tc = useTranslations("confirmation");
  const locale = useLocale() as Locale;
  const { format: money } = useCurrency();
  const proofSent = useSyncExternalStore(noop, () => safeGet(PROOF_KEY(reference)) === "1", () => false);
  const [submitted, setSubmitted] = useState(false);

  const payable = status === "awaiting_payment";
  const methods = useQuery({
    queryKey: ["payment-methods", reference, accessToken, authToken, locale],
    queryFn: ({ signal }) => api.paymentMethods(reference, { locale, accessToken, token: authToken, signal }),
    enabled: payable && !proofSent && !submitted,
    retry: false,
  });

  if (status === "awaiting_receipt_review" || proofSent || submitted) {
    return (
      <section className="rounded-card border border-info/30 bg-primary-100 p-6 shadow-card md:p-8" aria-labelledby="review-title" role="status">
        <span className="grid size-12 place-items-center rounded-full bg-white text-info"><Eye className="size-6" aria-hidden /></span>
        <h2 id="review-title" className="text-h4 mt-3 text-ink-900">{t("reviewTitle")}</h2>
        <p className="mt-1 text-ink-700">{t("reviewText")}</p>
      </section>
    );
  }
  if (!payable) return null;

  const shell = (children: React.ReactNode) => (
    <section className="rounded-card border border-ink-100 bg-white p-6 shadow-card md:p-8" aria-labelledby="pay-title">
      <h2 id="pay-title" className="text-h4 text-ink-900">{t("title")}</h2>
      <p className="mt-1 text-ink-700">{t("subtitle", { amount: money(deposit) })}</p>
      <div className="mt-5">{children}</div>
    </section>
  );

  if (methods.isPending) {
    return shell(
      <p className="flex items-center gap-2 text-ink-700" role="status">
        <span className="size-4 animate-spin rounded-full border-2 border-primary-500 border-t-transparent" aria-hidden />
        {t("loading")}
      </p>,
    );
  }

  const list = methods.data?.status === "ok" ? methods.data.data : [];
  if (methods.isError || list.length === 0) {
    return shell(
      <>
        <div className="rounded-image bg-sand-50 p-4">
          <p className="font-semibold text-ink-900">{list.length === 0 && !methods.isError && methods.data?.status === "ok" ? t("noMethodsTitle") : t("loadErrorTitle")}</p>
          <p className="mt-1 text-sm text-ink-700">{list.length === 0 && !methods.isError && methods.data?.status === "ok" ? t("noMethodsText") : t("loadErrorText")}</p>
          <div className="mt-3 flex flex-wrap items-center gap-4">
            {(methods.isError || methods.data?.status === "error") && (
              <button type="button" onClick={() => void methods.refetch()} className="font-semibold text-primary-700 underline">
                {t("retry")}
              </button>
            )}
            <a href={whatsappHref(`${reference}: `)} target="_blank" rel="noopener noreferrer" className="inline-flex items-center gap-2 font-semibold text-primary-700 underline">
              <MessageCircle className="size-4" aria-hidden /> WhatsApp
            </a>
          </div>
        </div>
        {fallbackOptions.length > 0 && (
          <ul className="mt-5 grid gap-3 sm:grid-cols-2">
            {fallbackOptions.map((o) => (
              <li key={o.code} className="flex items-center gap-3 rounded-image border border-ink-300 p-4">
                <span className="grid size-10 place-items-center rounded-full bg-primary-100 text-primary-700">
                  {o.type === "online" ? <CreditCard className="size-5" aria-hidden /> : <Banknote className="size-5" aria-hidden />}
                </span>
                <span>
                  <span className="block font-semibold text-ink-900">{o.name}</span>
                  <span className="text-sm text-ink-500">{o.type === "online" ? tc("online") : tc("manual")}</span>
                </span>
              </li>
            ))}
          </ul>
        )}
      </>,
    );
  }

  return shell(<MethodPicker {...props} methods={list} onProofSent={() => (safeSet(PROOF_KEY(reference), "1"), setSubmitted(true))} />);
}

function MethodPicker({ methods, onProofSent, ...p }: PaymentSectionProps & { methods: PaymentMethod[]; onProofSent: () => void }) {
  const t = useTranslations("payment");
  const [selectedId, setSelectedId] = useState(methods[0].id);
  const selected = methods.find((m) => m.id === selectedId) ?? methods[0];
  const groupId = useId();

  return (
    <div className="flex flex-col gap-6">
      <fieldset>
        <legend className="mb-3 text-sm text-ink-700">{t("methodsLabel")}</legend>
        <div className="grid gap-3 sm:grid-cols-2">
          {methods.map((m) => {
            const active = m.id === selected.id;
            return (
              <label
                key={m.id}
                className={cn(
                  "flex cursor-pointer items-start gap-3 rounded-image border p-4 transition-colors has-[:focus-visible]:ring-2 has-[:focus-visible]:ring-primary-500",
                  active ? "border-primary-700 bg-primary-100" : "border-ink-300 bg-white hover:border-primary-500",
                )}
              >
                <input type="radio" name={groupId} className="sr-only" checked={active} onChange={() => setSelectedId(m.id)} />
                <span className="grid size-10 shrink-0 place-items-center rounded-full bg-white text-primary-700">
                  {m.type === "online" ? <CreditCard className="size-5" aria-hidden /> : <Banknote className="size-5" aria-hidden />}
                </span>
                <span className="min-w-0 flex-1">
                  <span className="block font-semibold text-ink-900">{m.name}</span>
                  <span className="block text-sm text-ink-500">
                    {m.type === "online" ? t("online") : t("manual")}
                    {m.surcharge_percent > 0 && <> · {t("surcharge", { percent: m.surcharge_percent })}</>}
                  </span>
                  {m.currencies.length > 0 && <span className="block text-xs text-ink-500">{t("currencies", { list: m.currencies.join(", ") })}</span>}
                </span>
                {active && <CheckCircle2 className="size-5 shrink-0 text-primary-700" aria-hidden />}
              </label>
            );
          })}
        </div>
      </fieldset>
      {selected.type === "online" ? <OnlinePay key={selected.id} method={selected} {...p} /> : <ManualPay key={selected.id} method={selected} onProofSent={onProofSent} {...p} />}
    </div>
  );
}

/** Amount to pay with a method: deposit (+ surcharge) in the booking currency when accepted, else the method's first currency. */
function useMethodAmount(method: PaymentMethod, depositEur: number, bookingCurrency: CurrencyCode) {
  const { currencies } = useCurrency();
  const locale = useLocale();
  const due = method.amount_due;
  const currency = due?.currency ?? (method.currencies.length === 0 || method.currencies.includes(bookingCurrency) ? bookingCurrency : method.currencies[0]);
  const rate = currency === "EUR" ? 1 : (currencies.find((c) => c.code === currency)?.rate_to_eur ?? 1);
  // Prefer the server-computed amount (deposit or balance due now, surcharge included).
  const amount = due ? due.amount : Math.round(depositEur * rate * (1 + method.surcharge_percent / 100) * 100) / 100;
  const formatted = new Intl.NumberFormat(locale, { style: "currency", currency, currencyDisplay: currency === "EGP" ? "code" : "narrowSymbol" }).format(amount);
  return { currency, amount, formatted, converted: currency !== bookingCurrency };
}

function OnlinePay({ method, reference, accessToken, authToken, deposit, bookingCurrency }: PaymentSectionProps & { method: PaymentMethod }) {
  const t = useTranslations("payment");
  const locale = useLocale() as Locale;
  const { formatted, converted, currency } = useMethodAmount(method, deposit, bookingCurrency);
  const [state, setState] = useState<"idle" | "loading" | "redirecting">("idle");
  const [error, setError] = useState<string | null>(null);

  const pay = async () => {
    setError(null);
    setState("loading");
    try {
      const res = await api.checkout(reference, method.id, { locale, accessToken, token: authToken });
      if (res.status === "ok") {
        setState("redirecting");
        window.location.assign(res.data.redirect_url);
        return;
      }
      setError(t("checkoutError"));
    } catch {
      setError(t("checkoutError"));
    }
    setState("idle");
  };

  return (
    <div className="flex flex-col gap-3">
      {method.instructions && <p className="text-sm text-ink-700">{method.instructions}</p>}
      {converted && <p className="text-sm text-ink-500">{t("notInCurrency", { currency: bookingCurrency, list: currency })}</p>}
      <Button size="lg" className="w-full sm:w-auto sm:self-start" loading={state !== "idle"} onClick={pay} icon={<Lock className="size-5" aria-hidden />}>
        {t("payOnline", { amount: formatted })}
      </Button>
      <p className="text-sm text-ink-500" aria-live="polite">{state === "redirecting" ? t("redirecting") : t("secure")}</p>
      {error && <p className="rounded-image bg-error-100 p-3 text-sm text-error" role="alert">{error}</p>}
    </div>
  );
}

function ManualPay({ method, reference, accessToken, authToken, deposit, bookingCurrency, dueAt, createdAt, onProofSent }: PaymentSectionProps & { method: PaymentMethod; onProofSent: () => void }) {
  const t = useTranslations("payment");
  const tv = useTranslations("validation");
  const format = useFormatter();
  const locale = useLocale() as Locale;
  const today = useToday();
  const { formatted, amount, currency, converted } = useMethodAmount(method, deposit, bookingCurrency);
  const [files, setFiles] = useState<File[]>([]);
  const [upload, setUpload] = useState<UploadState>("idle");
  const [uploadError, setUploadError] = useState<string | null>(null);

  const deadline = dueAt ? new Date(dueAt) : createdAt ? new Date(new Date(createdAt).getTime() + (method.deadline_hours ?? 48) * 3600_000) : null;

  const schema = useMemo(
    () =>
      z.object({
        transaction_number: z.string().trim().min(3, tv("min", { count: 3 })),
        transfer_date: z.string().regex(/^\d{4}-\d{2}-\d{2}$/, tv("required")),
        amount: z.number({ error: tv("required") }).positive(tv("required")),
        sender_name: z.string().trim().min(2, tv("min", { count: 2 })),
      }),
    [tv],
  );
  type Values = z.infer<typeof schema>;
  const { register, handleSubmit, formState } = useForm<Values>({
    resolver: zodResolver(schema),
    mode: "onTouched",
    defaultValues: { transaction_number: "", transfer_date: "", amount, sender_name: "" },
  });
  const e = formState.errors;

  const onSubmit = handleSubmit(async (v) => {
    setUploadError(null);
    if (files.length === 0) {
      setUpload("error");
      setUploadError(t("filesRequired"));
      return;
    }
    setUpload("uploading");
    try {
      const res = await api.uploadProofs(reference, { ...v, files, payment_method_id: method.id }, { locale, accessToken, token: authToken });
      if (res.status === "ok") {
        setUpload("success");
        setTimeout(onProofSent, 900);
        return;
      }
      setUploadError(t("uploadError"));
    } catch (err) {
      setUploadError(err instanceof ApiError && err.isValidation ? (Object.values(err.errors).flat()[0] ?? t("uploadError")) : t("uploadError"));
    }
    setUpload("error");
  });

  const details: [string, string][] = Object.entries(method.account_details);
  return (
    <div className="flex flex-col gap-6">
      <div className="rounded-image bg-sand-50 p-4 md:p-5">
        <h3 className="font-semibold text-ink-900">{t("manualTitle")}</h3>
        {method.instructions && <p className="mt-1 text-sm text-ink-700">{method.instructions}</p>}
        <dl className="mt-4 flex flex-col divide-y divide-ink-100">
          <CopyRow label={t("amountLabel")} value={formatted} copy={amount.toFixed(2)} strong />
          {details.map(([label, value]) => (
            <CopyRow key={label} label={label} value={value} />
          ))}
          <CopyRow label={t("referenceLabel")} value={reference} hint={t("referenceHint")} mono />
          {deadline && (
            <div className="flex items-center justify-between gap-3 py-3">
              <dt className="text-sm text-ink-500">{t("deadlineLabel")}</dt>
              <dd className="flex items-center gap-1.5 font-semibold text-warning">
                <Clock className="size-4" aria-hidden />
                {format.dateTime(deadline, { day: "numeric", month: "long", hour: "2-digit", minute: "2-digit" })}
              </dd>
            </div>
          )}
        </dl>
        {converted && <p className="mt-2 text-xs text-ink-500">{t("notInCurrency", { currency: bookingCurrency, list: currency })}</p>}
      </div>

      <form onSubmit={onSubmit} noValidate className="flex flex-col gap-4" aria-labelledby="proof-title">
        <div>
          <h3 id="proof-title" className="font-semibold text-ink-900">{t("proofTitle")}</h3>
          <p className="mt-1 text-sm text-ink-700">{method.proof_required ? t("proofText") : t("proofOptional")}</p>
        </div>
        <FileUpload files={files} onChange={(f) => (setFiles(f), upload === "error" && setUpload("idle"), setUploadError(null))} state={upload} error={uploadError} />
        <div className="grid gap-4 sm:grid-cols-2">
          <Field label={t("transactionNumber")} htmlFor="pr-tx" error={e.transaction_number?.message} required>
            <Input id="pr-tx" invalid={Boolean(e.transaction_number)} {...register("transaction_number")} />
          </Field>
          <Field label={t("transferDate")} htmlFor="pr-date" error={e.transfer_date?.message} required>
            <Input id="pr-date" type="date" max={today ?? undefined} invalid={Boolean(e.transfer_date)} {...register("transfer_date")} />
          </Field>
          <Field label={t("amountPaid", { currency })} htmlFor="pr-amount" error={e.amount?.message} required>
            <Input id="pr-amount" type="number" inputMode="decimal" step="0.01" min="0" invalid={Boolean(e.amount)} {...register("amount", { valueAsNumber: true })} />
          </Field>
          <Field label={t("senderName")} htmlFor="pr-sender" error={e.sender_name?.message} required>
            <Input id="pr-sender" autoComplete="name" invalid={Boolean(e.sender_name)} {...register("sender_name")} />
          </Field>
        </div>
        <Button type="submit" className="self-start" loading={upload === "uploading"} disabled={upload === "success"}>
          {t("submit")}
        </Button>
      </form>
    </div>
  );
}

function CopyRow({ label, value, copy, hint, strong, mono }: { label: string; value: string; copy?: string; hint?: string; strong?: boolean; mono?: boolean }) {
  return (
    <div className="flex flex-wrap items-center justify-between gap-x-3 gap-y-1 py-3">
      <dt className="text-sm text-ink-500">
        {label}
        {hint && <span className="block text-xs">{hint}</span>}
      </dt>
      <dd className="flex min-w-0 items-center gap-2">
        <span className={cn("break-all text-ink-900", strong && "text-lg font-semibold", mono && "font-mono font-semibold")}>{value}</span>
        <CopyButton value={copy ?? value} label={label} />
      </dd>
    </div>
  );
}

function safeGet(key: string) {
  try {
    return window.sessionStorage.getItem(key);
  } catch {
    return null;
  }
}
function safeSet(key: string, value: string) {
  try {
    window.sessionStorage.setItem(key, value);
  } catch {
    /* ignore */
  }
}
