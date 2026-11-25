"use client";

import { Badge } from "@/components/ui/Badge";
import { Button } from "@/components/ui/Button";
import { Field, Input } from "@/components/ui/Field";
import { api, ApiError } from "@/lib/api/client";
import type { Locale, PartnerProfile } from "@/lib/api/types";
import { useAuth } from "@/lib/auth";
import { zodResolver } from "@hookform/resolvers/zod";
import { CheckCircle2, Info } from "lucide-react";
import { useLocale, useTranslations } from "next-intl";
import { useMemo, useState } from "react";
import { useForm, useWatch } from "react-hook-form";
import { z } from "zod";
import { usePartnerQuery } from "./usePartnerQuery";
import { NationalityCombobox } from "@/components/nationality/NationalityCombobox";
import { useNationality } from "@/lib/nationality";
import { PartnerPageHeader, useCommissionSource } from "./shared";

/** /partner/profile (Figma 256:35977): GET/PATCH /partner/profile; commissions per type are read-only (set by the admin). */
export function PartnerProfileForm() {
  const t = useTranslations("partnerPanel.profile");
  const tv = useTranslations("validation");
  const locale = useLocale() as Locale;
  const { user, signOut } = useAuth();
  const query = usePartnerQuery(["profile"], api.partnerProfile);
  const remote = query.data ?? null;
  const { data: nationalities } = useNationality();
  const source = useCommissionSource();
  const ts = useTranslations("partnerPanel.services");
  const tc = useTranslations("partnerPanel.commission");
  const [notice, setNotice] = useState<{ tone: "success" | "info" | "error"; text: string } | null>(null);

  const schema = useMemo(
    () =>
      z.object({
        company: z.string().trim().min(2, tv("min", { count: 2 })),
        tax_number: z.string().trim(),
        website: z.string().trim().regex(/^$|^https?:\/\/\S+\.\S+/, tv("url")),
        country: z.string().trim().regex(/^$|^[A-Za-z]{2}$/, tv("required")),
        contact_name: z.string().trim().min(2, tv("min", { count: 2 })),
        email: z.email(tv("email")),
        phone: z.string().trim().regex(/^$|^\+?[0-9 ()-]{6,20}$/, tv("phone")),
        notify_bookings: z.boolean(),
        notify_statements: z.boolean(),
      }),
    [tv],
  );
  type Values = z.infer<typeof schema>;
  const values: Values = {
    company: remote?.company ?? user?.partner?.company ?? "",
    tax_number: remote?.tax_number ?? "",
    website: remote?.website ?? "",
    country: remote?.country ?? "",
    contact_name: remote?.contact_name ?? user?.name ?? "",
    email: remote?.email ?? user?.email ?? "",
    phone: remote?.phone ?? user?.phone ?? "",
    notify_bookings: remote?.notify_bookings ?? true,
    notify_statements: remote?.notify_statements ?? true,
  };
  const form = useForm<Values>({ resolver: zodResolver(schema), mode: "onTouched", values, resetOptions: { keepDirtyValues: true } });
  const e = form.formState.errors;
  const countryValue = useWatch({ control: form.control, name: "country" });
  const status: PartnerProfile["status"] = remote?.status ?? user?.partner?.status ?? "pending";

  const onSubmit = form.handleSubmit(async (v) => {
    setNotice(null);
    try {
      await api.updatePartnerProfile(
        {
          contact_name: v.contact_name,
          tax_number: v.tax_number || null,
          website: v.website || null,
          country: v.country ? v.country.toUpperCase() : null,
          phone: v.phone || null,
          notify_bookings: v.notify_bookings,
          notify_statements: v.notify_statements,
        },
        { locale },
      );
      void query.refetch();
      setNotice({ tone: "success", text: t("saved") });
    } catch (err) {
      if (err instanceof ApiError && err.status === 401) return void signOut();
      if (err instanceof ApiError && err.isValidation) {
        for (const [k, msgs] of Object.entries(err.errors)) if (k in v) form.setError(k as keyof Values, { message: msgs[0] });
      } else setNotice({ tone: "error", text: String(err instanceof Error ? err.message : err) });
    }
  });

  const text = (name: keyof Values, label: string, opts: { type?: string; autoComplete?: string; required?: boolean; className?: string } = {}) => (
    <Field label={label} htmlFor={`pp-${name}`} error={e[name]?.message} required={opts.required} className={opts.className}>
      <Input id={`pp-${name}`} type={opts.type ?? "text"} autoComplete={opts.autoComplete} invalid={Boolean(e[name])} {...form.register(name)} />
    </Field>
  );

  return (
    <div className="flex flex-col gap-6">
      <PartnerPageHeader
        crumbs={[{ label: t("crumb") }]}
        title={t("title")}
        subtitle={t("subtitle")}
        actions={
          <p className="flex items-center gap-2 text-sm text-ink-700">
            {t("statusLabel")}
            <Badge tone={status === "active" ? "success" : status === "pending" ? "partner" : "warning"}>{t(`status.${status}`)}</Badge>
          </p>
        }
      />
      <form onSubmit={onSubmit} noValidate className="flex flex-col gap-6">
        <section className="rounded-card border border-ink-100 bg-white p-5 shadow-card md:p-6" aria-labelledby="pp-company-title">
          <h2 id="pp-company-title" className="text-h4 text-ink-900">{t("companyTitle")}</h2>
          <div className="mt-4 grid grid-cols-1 gap-4 sm:grid-cols-2">
            <Field label={t("company")} htmlFor="pp-company" hint={t("companyHint")} className="sm:col-span-2">
              <Input id="pp-company" value={values.company} readOnly aria-readonly aria-describedby="pp-company-hint" />
            </Field>
            {text("tax_number", t("taxNumber"))}
            <div className="flex flex-col gap-1">
              <NationalityCombobox
                id="pp-country"
                label={t("country")}
                countries={nationalities?.countries ?? []}
                value={countryValue || null}
                onChange={(c) => form.setValue("country", c ?? "", { shouldDirty: true, shouldValidate: true })}
                invalid={Boolean(e.country)}
              />
              {e.country && <p className="text-sm text-error" role="alert">{e.country.message}</p>}
            </div>
            {text("website", t("website"), { type: "url", autoComplete: "url", className: "sm:col-span-2" })}
          </div>
        </section>
        <section className="rounded-card border border-ink-100 bg-white p-5 shadow-card md:p-6" aria-labelledby="pp-contact-title">
          <h2 id="pp-contact-title" className="text-h4 text-ink-900">{t("contactTitle")}</h2>
          <div className="mt-4 grid grid-cols-1 gap-4 sm:grid-cols-2">
            {text("contact_name", t("contactName"), { autoComplete: "name", required: true, className: "sm:col-span-2" })}
            <Field label={t("email")} htmlFor="pp-email">
              <Input id="pp-email" type="email" value={values.email} readOnly aria-readonly />
            </Field>
            {text("phone", t("phone"), { type: "tel", autoComplete: "tel" })}
          </div>
          <p className="mt-3 text-xs text-ink-500">{t("passwordNote")}</p>
        </section>
        <section className="rounded-card border border-ink-100 bg-white p-5 shadow-card md:p-6" aria-labelledby="pp-commission-title">
          <h2 id="pp-commission-title" className="text-h4 text-ink-900">{t("commissionsTitle")}</h2>
          <p className="mt-1 text-sm text-ink-500">{t("commissionsText")}</p>
          {remote?.commissions?.length ? (
            <ul className="mt-4 grid grid-cols-2 gap-3 md:grid-cols-3">
              {remote.commissions.map((c) => (
                <li key={c.category} className="rounded-image bg-partner-100 p-3">
                  <p className="text-sm text-ink-700">{ts(c.category)}</p>
                  <p className="text-2xl font-bold text-partner-600">{c.percent}%</p>
                  <p className="text-xs text-ink-500">
                    {source(c.source)}
                    {c.override_percent != null && c.standard_percent !== c.percent ? ` · ${t("standardWas", { percent: c.standard_percent })}` : ""}
                  </p>
                </li>
              ))}
              <li className="rounded-image bg-ink-100 p-3">
                <p className="text-sm text-ink-700">{ts("accommodation")}</p>
                <p className="font-semibold text-ink-700">{tc("none")}</p>
              </li>
            </ul>
          ) : (
            <div className="mt-4 h-24 animate-pulse rounded-image bg-ink-100" aria-hidden />
          )}
        </section>
        <fieldset className="rounded-card border border-ink-100 bg-white p-5 shadow-card md:p-6">
          <legend className="sr-only">{t("notificationsTitle")}</legend>
          <h2 className="text-h4 text-ink-900" aria-hidden>{t("notificationsTitle")}</h2>
          <div className="mt-4 flex flex-col gap-3">
            <label className="flex items-start gap-3 text-ink-700">
              <input type="checkbox" className="mt-0.5 size-5 shrink-0 accent-partner-600" {...form.register("notify_bookings")} />
              {t("notifyBookings")}
            </label>
            <label className="flex items-start gap-3 text-ink-700">
              <input type="checkbox" className="mt-0.5 size-5 shrink-0 accent-partner-600" {...form.register("notify_statements")} />
              {t("notifyStatements")}
            </label>
          </div>
        </fieldset>
        {notice && (
          <p className={`flex items-start gap-2 rounded-image p-3 text-sm ${notice.tone === "success" ? "bg-success-100" : notice.tone === "info" ? "bg-partner-100 text-partner-600" : "bg-error-100 text-error"}`} role={notice.tone === "error" ? "alert" : "status"}>
            {notice.tone === "success" ? <CheckCircle2 className="mt-0.5 size-4 shrink-0 text-success" aria-hidden /> : <Info className="mt-0.5 size-4 shrink-0" aria-hidden />}
            {notice.text}
          </p>
        )}
        <Button type="submit" variant="partner" className="self-start" loading={form.formState.isSubmitting}>
          {t("save")}
        </Button>
      </form>
    </div>
  );
}
