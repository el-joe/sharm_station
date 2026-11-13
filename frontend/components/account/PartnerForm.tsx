"use client";

import { Button } from "@/components/ui/Button";
import { Field, Input, Select, Textarea } from "@/components/ui/Field";
import { api, ApiError } from "@/lib/api/client";
import type { Locale, PartnerApplication } from "@/lib/api/types";
import { zodResolver } from "@hookform/resolvers/zod";
import { CheckCircle2 } from "lucide-react";
import { useLocale, useTranslations } from "next-intl";
import { useMemo, useState } from "react";
import { useForm } from "react-hook-form";
import { z } from "zod";

// ISO-3166 alpha-2 codes (the API validates `country` as size:2).
const COUNTRIES = ["IT", "GB", "DE", "FR", "ES", "US", "EG", "CH", "NL"] as const;

/** Become a partner (POST /partners/apply). Applications are approved by the admin (SRS 9.1). */
export function PartnerForm() {
  const t = useTranslations("partner");
  const tc = useTranslations("checkout");
  const tv = useTranslations("validation");
  const locale = useLocale() as Locale;
  const [done, setDone] = useState(false);
  const [serverError, setServerError] = useState<string | null>(null);

  const schema = useMemo(
    () =>
      z.object({
        company: z.string().trim().min(2, tv("min", { count: 2 })),
        contact_name: z.string().trim().min(2, tv("min", { count: 2 })),
        email: z.email(tv("email")),
        phone: z.string().trim().regex(/^\+?[0-9\s().-]{7,20}$/, tv("phone")),
        country: z.string().length(2, tv("required")),
        password: z.string().min(8, tv("password")),
        password_confirmation: z.string(),
        website: z.union([z.literal(""), z.url(tv("url"))]).optional(),
        tax_number: z.string().trim().max(40).optional(),
        business_type: z.enum(["agency", "hotel", "tour_operator", "other"], { error: tv("required") }),
        message: z.string().max(1000).optional(),
        consent: z.literal(true, { error: tv("terms") }),
      })
      .refine((v) => v.password === v.password_confirmation, { path: ["password_confirmation"], message: tv("passwordMatch") }),
    [tv],
  );
  type Values = z.infer<typeof schema>;
  const { register, handleSubmit, formState } = useForm<Values>({ resolver: zodResolver(schema), mode: "onTouched" });
  const e = formState.errors;

  const onSubmit = handleSubmit(async (values) => {
    setServerError(null);
    const body: PartnerApplication = {
      company: values.company,
      contact_name: values.contact_name,
      email: values.email,
      phone: values.phone,
      country: values.country,
      website: values.website || undefined,
      tax_number: values.tax_number || undefined,
      business_type: values.business_type,
      message: values.message || undefined,
      password: values.password,
      password_confirmation: values.password_confirmation,
    };
    try {
      await api.applyPartner(body, { locale });
      setDone(true);
    } catch (err) {
      setServerError(err instanceof ApiError && err.isValidation ? Object.values(err.errors).flat().join(" ") || err.message : t("error"));
    }
  });

  if (done) {
    return (
      <div className="flex flex-col items-center gap-3 py-10 text-center" role="status">
        <CheckCircle2 className="size-12 text-success" aria-hidden />
        <h2 className="text-h3 text-ink-900">{t("successTitle")}</h2>
        <p className="max-w-md text-ink-700">{t("successText")}</p>
      </div>
    );
  }

  return (
    <form onSubmit={onSubmit} noValidate className="grid gap-4 md:grid-cols-2">
      <Field label={t("company")} htmlFor="company" error={e.company?.message} required>
        <Input id="company" autoComplete="organization" invalid={Boolean(e.company)} {...register("company")} />
      </Field>
      <Field label={t("businessType")} htmlFor="business_type" error={e.business_type?.message} required>
        <Select id="business_type" defaultValue="" invalid={Boolean(e.business_type)} {...register("business_type")}>
          <option value="" disabled>{t("select")}</option>
          {(["agency", "tour_operator", "hotel", "other"] as const).map((b) => (
            <option key={b} value={b}>{t(`types.${b}`)}</option>
          ))}
        </Select>
      </Field>
      <Field label={t("contactName")} htmlFor="contact_name" error={e.contact_name?.message} required>
        <Input id="contact_name" autoComplete="name" invalid={Boolean(e.contact_name)} {...register("contact_name")} />
      </Field>
      <Field label={t("email")} htmlFor="p-email" error={e.email?.message} required>
        <Input id="p-email" type="email" autoComplete="email" invalid={Boolean(e.email)} {...register("email")} />
      </Field>
      <Field label={t("phone")} htmlFor="p-phone" error={e.phone?.message} required>
        <Input id="p-phone" type="tel" autoComplete="tel" invalid={Boolean(e.phone)} {...register("phone")} />
      </Field>
      <Field label={t("country")} htmlFor="p-country" error={e.country?.message} required>
        <Select id="p-country" defaultValue="" autoComplete="country" invalid={Boolean(e.country)} {...register("country")}>
          <option value="" disabled>{t("select")}</option>
          {COUNTRIES.map((c) => (
            <option key={c} value={c}>{tc(`countries.${c}`)}</option>
          ))}
        </Select>
      </Field>
      <Field label={t("website")} htmlFor="website" error={e.website?.message}>
        <Input id="website" type="url" placeholder="https://" invalid={Boolean(e.website)} {...register("website")} />
      </Field>
      <Field label={t("taxNumber")} htmlFor="tax_number">
        <Input id="tax_number" {...register("tax_number")} />
      </Field>
      <Field label={t("password")} htmlFor="p-password" error={e.password?.message} required hint={t("passwordHint")}>
        <Input id="p-password" type="password" autoComplete="new-password" invalid={Boolean(e.password)} {...register("password")} />
      </Field>
      <Field label={t("confirmPassword")} htmlFor="p-password2" error={e.password_confirmation?.message} required>
        <Input id="p-password2" type="password" autoComplete="new-password" invalid={Boolean(e.password_confirmation)} {...register("password_confirmation")} />
      </Field>
      <Field label={t("message")} htmlFor="message" className="md:col-span-2">
        <Textarea id="message" {...register("message")} />
      </Field>
      <div className="md:col-span-2">
        <label className="flex items-start gap-3 text-sm text-ink-900">
          <input type="checkbox" className="mt-0.5 size-5 shrink-0 accent-primary-700" aria-invalid={Boolean(e.consent) || undefined} {...register("consent")} />
          <span>{t("consent")}</span>
        </label>
        {e.consent && <p className="mt-1 text-sm text-error" role="alert">{e.consent.message}</p>}
      </div>
      {serverError && <p className="rounded-image bg-error-100 p-3 text-sm text-error md:col-span-2" role="alert">{serverError}</p>}
      <div className="md:col-span-2">
        <Button type="submit" size="lg" loading={formState.isSubmitting} className="w-full md:w-auto">{t("submit")}</Button>
      </div>
    </form>
  );
}
