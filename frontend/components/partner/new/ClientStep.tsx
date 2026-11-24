"use client";

import { CouponField, type AppliedCoupon } from "@/components/booking/CouponField";
import { Button } from "@/components/ui/Button";
import type { CouponServiceType } from "@/lib/api/types";
import { useAuth } from "@/lib/auth";
import { Field, Input, Textarea } from "@/components/ui/Field";
import { useCurrency } from "@/lib/currency";
import { cn } from "@/lib/utils";
import { zodResolver } from "@hookform/resolvers/zod";
import { Lock } from "lucide-react";
import { useTranslations } from "next-intl";
import { useCallback, useMemo, useState, type ReactNode } from "react";
import { useFieldArray, useForm, useWatch } from "react-hook-form";
import { z } from "zod";
import { CountryLabel, PartnerPriceSummary } from "../shared";
import type { Configured } from "./ConfigureStep";

export interface ClientValues {
  first_name: string;
  last_name: string;
  email: string;
  phone: string;
  travellers: { first_name: string; last_name: string; type: "adult" | "child"; age: number | null }[];
  payment: "online" | "bank_transfer";
  voucher: "with_prices" | "without_prices";
  notes: string;
  terms: true;
  /** Applied promo code (partner bookings accept only partner-enabled codes). */
  coupon_code?: string;
}

function Section({ id, title, text, children }: { id: string; title: string; text?: string; children: ReactNode }) {
  return (
    <section className="rounded-card border border-ink-100 bg-white p-5 shadow-card" aria-labelledby={id}>
      <h2 id={id} className="text-h4 text-ink-900">
        {title}
      </h2>
      {text && <p className="mt-1 text-sm text-ink-500">{text}</p>}
      <div className="mt-4">{children}</div>
    </section>
  );
}

function Choice({ name, value, checked, title, text, register }: { name: string; value: string; checked: boolean; title: string; text: string; register: object }) {
  return (
    <label className={cn("flex cursor-pointer items-start gap-3 rounded-image border p-4 has-[:focus-visible]:outline has-[:focus-visible]:outline-2 has-[:focus-visible]:outline-partner-600", checked ? "border-2 border-partner-600 bg-partner-100" : "border-ink-300 bg-white")}>
      <input type="radio" name={name} value={value} className="mt-1 size-4 shrink-0 accent-partner-600" {...register} />
      <span>
        <span className="block font-semibold text-ink-900">{title}</span>
        <span className="block text-sm text-ink-700">{text}</span>
      </span>
    </label>
  );
}

/** Step 3 (Figma 257:36136): lead client, other travellers, payment method, client voucher, confirm. */
export function ClientStep({
  title,
  config,
  nationality,
  serviceLabel,
  isTransfer,
  initial,
  error,
  couponError,
  couponService,
  onBack,
  onSubmit,
}: {
  title: string;
  config: Configured;
  nationality: string;
  serviceLabel: string;
  isTransfer: boolean;
  initial?: ClientValues | null;
  error: string | null;
  /** 422 on coupon_code from POST /partner/bookings. */
  couponError?: string | null;
  couponService: CouponServiceType;
  onBack: () => void;
  onSubmit: (v: ClientValues) => Promise<void>;
}) {
  const t = useTranslations("partnerPanel.newBooking.client");
  const tv = useTranslations("validation");
  const { format } = useCurrency();
  const q = config.quote;
  const { token } = useAuth();
  const [coupon, setCoupon] = useState<AppliedCoupon | null>(null);
  const onCouponChange = useCallback((c: AppliedCoupon | null) => setCoupon(c), []);

  const schema = useMemo(
    () =>
      z.object({
        first_name: z.string().trim().min(1, tv("required")),
        last_name: z.string().trim().min(1, tv("required")),
        email: z.email(tv("email")),
        phone: z.string().trim().regex(/^\+?[0-9 ()-]{6,20}$/, tv("phone")),
        travellers: z.array(
          z.object({
            first_name: z.string().trim().min(1, tv("required")),
            last_name: z.string().trim().min(1, tv("required")),
            type: z.enum(["adult", "child"]),
            age: z.number().nullable(),
          }),
        ),
        payment: z.enum(["online", "bank_transfer"]),
        voucher: z.enum(["with_prices", "without_prices"]),
        notes: z.string().max(2000),
        terms: z.literal(true, { error: tv("terms") }),
      }),
    [tv],
  );

  const defaults: ClientValues = initial ?? {
    first_name: "",
    last_name: "",
    email: "",
    phone: "",
    travellers: isTransfer
      ? []
      : [
          ...Array.from({ length: Math.max(0, config.adults - 1) }, () => ({ first_name: "", last_name: "", type: "adult" as const, age: null })),
          ...config.childAges.map((age) => ({ first_name: "", last_name: "", type: "child" as const, age })),
        ],
    payment: "bank_transfer",
    voucher: "without_prices",
    notes: "",
    terms: false as unknown as true,
  };
  const form = useForm<ClientValues>({ resolver: zodResolver(schema), defaultValues: defaults, mode: "onTouched" });
  const { fields } = useFieldArray({ control: form.control, name: "travellers" });
  const e = form.formState.errors;
  const payment = useWatch({ control: form.control, name: "payment" });
  const voucher = useWatch({ control: form.control, name: "voucher" });

  const input = (name: "first_name" | "last_name" | "email" | "phone", label: string, type = "text", autoComplete?: string) => (
    <Field label={label} htmlFor={`cl-${name}`} error={e[name]?.message} required>
      <Input id={`cl-${name}`} type={type} autoComplete={autoComplete} invalid={Boolean(e[name])} aria-describedby={e[name] ? `cl-${name}-error` : undefined} {...form.register(name)} />
    </Field>
  );

  return (
    <div className="grid gap-6 xl:grid-cols-[minmax(0,1fr)_340px]">
      <form onSubmit={form.handleSubmit((v) => onSubmit({ ...v, coupon_code: coupon?.code }))} noValidate className="flex min-w-0 flex-col gap-5">
        <Section id="cl-lead" title={t("leadTitle")} text={t("leadText")}>
          <div className="grid gap-4 sm:grid-cols-2">
            {input("first_name", t("firstName"), "text", "off")}
            {input("last_name", t("lastName"), "text", "off")}
            {input("email", t("email"), "email", "off")}
            {input("phone", t("phone"), "tel", "off")}
            <div className="flex flex-col gap-1 sm:col-span-2">
              <span className="text-sm text-ink-700" id="cl-nat-label">
                {t("nationality")}
              </span>
              <p aria-labelledby="cl-nat-label" className="flex h-12 items-center gap-2 rounded-control border border-ink-300 bg-ink-100 px-4 text-ink-700">
                <Lock className="size-4 shrink-0" aria-hidden />
                <CountryLabel code={nationality} withGroup />
              </p>
              <p className="text-xs text-ink-500">{t("nationalityHint")}</p>
            </div>
          </div>
        </Section>

        {fields.length > 0 && (
          <Section id="cl-travellers" title={t("travellersTitle")}>
            <ol className="flex flex-col gap-4">
              {fields.map((f, i) => (
                <li key={f.id} className="grid gap-3 sm:grid-cols-2">
                  <p className="text-sm font-semibold text-ink-900 sm:col-span-2">
                    {f.type === "adult" ? t("travellerAdult", { n: i + 2 }) : t("travellerChild", { n: i + 2, age: f.age ?? "?" })}
                  </p>
                  <Field label={t("firstName")} htmlFor={`tr-${i}-first`} error={e.travellers?.[i]?.first_name?.message} required>
                    <Input id={`tr-${i}-first`} autoComplete="off" invalid={Boolean(e.travellers?.[i]?.first_name)} {...form.register(`travellers.${i}.first_name`)} />
                  </Field>
                  <Field label={t("lastName")} htmlFor={`tr-${i}-last`} error={e.travellers?.[i]?.last_name?.message} required>
                    <Input id={`tr-${i}-last`} autoComplete="off" invalid={Boolean(e.travellers?.[i]?.last_name)} {...form.register(`travellers.${i}.last_name`)} />
                  </Field>
                </li>
              ))}
            </ol>
          </Section>
        )}

        <Section
          id="cl-payment"
          title={t("paymentTitle")}
          text={t("paymentText", { deposit: format(q.deposit_amount, { decimals: true }), balance: format(q.balance_amount, { decimals: true }) })}
        >
          <fieldset className="flex flex-col gap-3">
            <legend className="sr-only">{t("paymentTitle")}</legend>
            <Choice name="payment" value="online" checked={payment === "online"} title={t("payOnline")} text={t("payOnlineText")} register={form.register("payment")} />
            <Choice name="payment" value="bank_transfer" checked={payment === "bank_transfer"} title={t("payTransfer")} text={t("payTransferText")} register={form.register("payment")} />
          </fieldset>
        </Section>

        <Section id="cl-voucher" title={t("voucherTitle")}>
          <fieldset className="grid gap-3 sm:grid-cols-2">
            <legend className="sr-only">{t("voucherTitle")}</legend>
            <Choice name="voucher" value="with_prices" checked={voucher === "with_prices"} title={t("voucherWith")} text={t("voucherWithText")} register={form.register("voucher")} />
            <Choice name="voucher" value="without_prices" checked={voucher === "without_prices"} title={t("voucherWithout")} text={t("voucherWithoutText")} register={form.register("voucher")} />
          </fieldset>
        </Section>

        <Section id="cl-notes" title={t("notesTitle")}>
          <Textarea id="cl-notes-input" aria-labelledby="cl-notes" rows={3} {...form.register("notes")} />
        </Section>

        <div className="flex flex-col gap-1">
          <label className="flex items-start gap-3 text-sm text-ink-700">
            <input type="checkbox" className="mt-0.5 size-5 shrink-0 accent-partner-600" aria-invalid={Boolean(e.terms) || undefined} aria-describedby={e.terms ? "cl-terms-error" : undefined} {...form.register("terms")} />
            {t("terms")}
          </label>
          {e.terms && (
            <p id="cl-terms-error" className="text-sm text-error" role="alert">
              {e.terms.message}
            </p>
          )}
        </div>

        {error && (
          <p className="rounded-image bg-error-100 p-3 text-sm text-error" role="alert">
            {error}
          </p>
        )}

        <div className="flex items-center justify-between gap-3">
          <Button variant="ghost" onClick={onBack}>
            {t("back")}
          </Button>
          <Button type="submit" size="lg" loading={form.formState.isSubmitting}>
            {t("confirm")}
          </Button>
        </div>
      </form>
      <div>
        <div className="xl:sticky xl:top-28">
          <PartnerPriceSummary
            title={title}
            subtitle={config.subtitle}
            quote={q}
            serviceLabel={serviceLabel}
            discount={coupon ? { code: coupon.code, amount: coupon.discount_eur } : null}
            footer={
              <CouponField
                serviceType={couponService}
                totalEur={q.partner_total}
                forPartner
                token={token}
                applied={coupon}
                onChange={onCouponChange}
                serverError={couponError}
              />
            }
          />
        </div>
      </div>
    </div>
  );
}
