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
import { useForm } from "react-hook-form";
import { z } from "zod";
import { usePartnerQuery } from "./usePartnerQuery";

/** /partner/profile: GET/PATCH /partner/profile (PENDING BACKEND; prefilled from /auth/me meanwhile). */
export function PartnerProfileForm() {
  const t = useTranslations("partnerPanel.profile");
  const tv = useTranslations("validation");
  const locale = useLocale() as Locale;
  const { user, token, signOut } = useAuth();
  const query = usePartnerQuery(["profile"], api.partnerProfile);
  const remote = query.data?.status === "ok" ? query.data.data : null;
  const unavailable = query.data?.status === "unavailable" || query.isError;
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
  const status: PartnerProfile["status"] = remote?.status ?? user?.partner?.status ?? "pending";

  const onSubmit = form.handleSubmit(async (v) => {
    setNotice(null);
    try {
      const res = await api.updatePartnerProfile(
        { ...v, tax_number: v.tax_number || null, website: v.website || null, country: v.country ? v.country.toUpperCase() : null, phone: v.phone || null },
        { locale, token: token! },
      );
      setNotice(res.status === "ok" ? { tone: "success", text: t("saved") } : { tone: "info", text: t("pending") });
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
      <div className="flex flex-wrap items-end justify-between gap-4">
        <div>
          <h1 className="text-h2 text-ink-900">{t("title")}</h1>
          <p className="mt-1 text-ink-700">{t("subtitle")}</p>
        </div>
        <p className="flex items-center gap-2 text-sm text-ink-700">
          {t("statusLabel")}
          <Badge tone={status === "active" ? "success" : status === "pending" ? "partner" : "warning"}>{t(`status.${status}`)}</Badge>
        </p>
      </div>
      {unavailable && (
        <p className="flex items-start gap-2 rounded-image bg-partner-100 p-3 text-sm text-partner-600" role="status">
          <Info className="mt-0.5 size-4 shrink-0" aria-hidden /> {t("pending")}
        </p>
      )}
      <form onSubmit={onSubmit} noValidate className="flex flex-col gap-6">
        <section className="rounded-card border border-ink-100 bg-white p-5 shadow-card md:p-6" aria-labelledby="pp-company-title">
          <h2 id="pp-company-title" className="text-h4 text-ink-900">{t("companyTitle")}</h2>
          <div className="mt-4 grid gap-4 sm:grid-cols-2">
            {text("company", t("company"), { autoComplete: "organization", required: true, className: "sm:col-span-2" })}
            {text("tax_number", t("taxNumber"))}
            {text("country", t("country"), { autoComplete: "country" })}
            {text("website", t("website"), { type: "url", autoComplete: "url", className: "sm:col-span-2" })}
          </div>
        </section>
        <section className="rounded-card border border-ink-100 bg-white p-5 shadow-card md:p-6" aria-labelledby="pp-contact-title">
          <h2 id="pp-contact-title" className="text-h4 text-ink-900">{t("contactTitle")}</h2>
          <div className="mt-4 grid gap-4 sm:grid-cols-2">
            {text("contact_name", t("contactName"), { autoComplete: "name", required: true, className: "sm:col-span-2" })}
            {text("email", t("email"), { type: "email", autoComplete: "email", required: true })}
            {text("phone", t("phone"), { type: "tel", autoComplete: "tel" })}
          </div>
          <p className="mt-3 text-xs text-ink-500">{t("passwordNote")}</p>
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
