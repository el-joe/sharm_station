"use client";

import { Button, buttonClasses } from "@/components/ui/Button";
import { Field, Input, Select, Textarea } from "@/components/ui/Field";
import { Link } from "@/i18n/navigation";
import { api, ApiError } from "@/lib/api/client";
import type { ContactRequest, Locale } from "@/lib/api/types";
import { useAuth } from "@/lib/auth";
import { useLocationSearch } from "@/lib/hooks";
import { whatsappHref } from "@/lib/site";
import { zodResolver } from "@hookform/resolvers/zod";
import { CheckCircle2, Mail, MessageCircle } from "lucide-react";
import { useLocale, useTranslations } from "next-intl";
import { useMemo, useState } from "react";
import { useForm } from "react-hook-form";
import { z } from "zod";

const SUBJECTS = ["booking", "trip", "partner", "other"] as const;

/** Contact form: POST /contact (PENDING BACKEND). On 404/network error it offers WhatsApp and a prefilled email instead. */
export function ContactForm({ email, whatsapp }: { email: string; whatsapp: string }) {
  const t = useTranslations("contact");
  const tv = useTranslations("validation");
  const tn = useTranslations("nav");
  const tf = useTranslations("footer");
  const locale = useLocale() as Locale;
  const { user } = useAuth();
  const search = useLocationSearch();
  const params = new URLSearchParams(search);
  const [result, setResult] = useState<{ kind: "sent"; name: string } | { kind: "fallback"; values: ContactRequest } | null>(null);
  const [serverError, setServerError] = useState<string | null>(null);

  const schema = useMemo(
    () =>
      z.object({
        name: z.string().trim().min(2, tv("min", { count: 2 })),
        email: z.email(tv("email")),
        phone: z.string().trim().regex(/^$|^\+?[0-9 ()-]{6,20}$/, tv("phone")),
        subject: z.enum(SUBJECTS),
        booking_reference: z.string().trim().max(32),
        message: z.string().trim().min(10, tv("min", { count: 10 })),
        consent: z.literal(true, { error: tv("terms") }),
      }),
    [tv],
  );
  type Values = z.infer<typeof schema>;
  const subjectParam = params.get("subject");
  const { register, handleSubmit, formState, setError } = useForm<Values>({
    resolver: zodResolver(schema),
    mode: "onTouched",
    values: {
      name: user?.name ?? "",
      email: user?.email ?? "",
      phone: user?.phone ?? "",
      subject: SUBJECTS.includes(subjectParam as never) ? (subjectParam as Values["subject"]) : "trip",
      booking_reference: params.get("reference") ?? "",
      message: "",
      consent: false as unknown as true,
    },
    resetOptions: { keepDirtyValues: true },
  });
  const e = formState.errors;

  const onSubmit = handleSubmit(async (v) => {
    setServerError(null);
    const body: ContactRequest = { name: v.name, email: v.email, phone: v.phone || undefined, subject: v.subject, booking_reference: v.booking_reference || undefined, message: v.message };
    try {
      const res = await api.contact(body, { locale });
      setResult(res.status === "ok" ? { kind: "sent", name: v.name.split(" ")[0] } : { kind: "fallback", values: body });
    } catch (err) {
      if (err instanceof ApiError && err.isValidation) {
        for (const [k, msgs] of Object.entries(err.errors)) if (k in v) setError(k as keyof Values, { message: msgs[0] });
      } else setServerError(t("sendError"));
    }
  });

  if (result?.kind === "sent") {
    return (
      <div className="flex flex-col items-start gap-3 rounded-card bg-success-100 p-6" role="status">
        <CheckCircle2 className="size-8 text-success" aria-hidden />
        <h2 className="text-h4 text-ink-900">{t("successTitle")}</h2>
        <p className="text-ink-700">{t("successText", { name: result.name })}</p>
        <Link href="/trips" className={buttonClasses("outline", "md")}>{tn("trips")}</Link>
      </div>
    );
  }

  if (result?.kind === "fallback") {
    const v = result.values;
    const text = [`${t(`subjects.${v.subject}`)}${v.booking_reference ? ` (${v.booking_reference})` : ""}`, "", v.message, "", `${v.name} · ${v.email}${v.phone ? ` · ${v.phone}` : ""}`].join("\n");
    const mailto = `mailto:${email}?subject=${encodeURIComponent(`${t(`subjects.${v.subject}`)}${v.booking_reference ? ` ${v.booking_reference}` : ""}`)}&body=${encodeURIComponent(text)}`;
    return (
      <div className="flex flex-col gap-4 rounded-card bg-warning-100 p-6" role="status">
        <h2 className="text-h4 text-ink-900">{t("fallbackTitle")}</h2>
        <p className="text-ink-700">{t("fallbackText")}</p>
        <div className="flex flex-wrap gap-3">
          <a href={whatsappHref(text)} target="_blank" rel="noopener noreferrer" className={buttonClasses("cta", "md")}>
            <MessageCircle className="size-5" aria-hidden /> {t("whatsapp")}
          </a>
          <a href={mailto} className={buttonClasses("outline", "md")}>
            <Mail className="size-5" aria-hidden /> {t("emailDraft")}
          </a>
        </div>
        <p className="text-xs text-ink-500">{whatsapp}</p>
      </div>
    );
  }

  return (
    <form onSubmit={onSubmit} noValidate className="flex flex-col gap-4">
      <div className="grid gap-4 sm:grid-cols-2">
        <Field label={t("name")} htmlFor="c-name" error={e.name?.message} required>
          <Input id="c-name" autoComplete="name" invalid={Boolean(e.name)} {...register("name")} />
        </Field>
        <Field label={t("email")} htmlFor="c-email" error={e.email?.message} required>
          <Input id="c-email" type="email" autoComplete="email" invalid={Boolean(e.email)} {...register("email")} />
        </Field>
        <Field label={t("phone")} htmlFor="c-phone" error={e.phone?.message}>
          <Input id="c-phone" type="tel" autoComplete="tel" invalid={Boolean(e.phone)} {...register("phone")} />
        </Field>
        <Field label={t("subject")} htmlFor="c-subject" required>
          <Select id="c-subject" {...register("subject")}>
            {SUBJECTS.map((s) => (
              <option key={s} value={s}>{t(`subjects.${s}`)}</option>
            ))}
          </Select>
        </Field>
        <Field label={t("reference")} htmlFor="c-ref" error={e.booking_reference?.message} className="sm:col-span-2">
          <Input id="c-ref" placeholder={t("referencePlaceholder")} {...register("booking_reference")} />
        </Field>
        <Field label={t("message")} htmlFor="c-message" error={e.message?.message} required className="sm:col-span-2">
          <Textarea id="c-message" rows={5} placeholder={t("messagePlaceholder")} invalid={Boolean(e.message)} {...register("message")} />
        </Field>
      </div>
      <div>
        <label className="flex items-start gap-3 text-sm text-ink-700">
          <input type="checkbox" className="mt-0.5 size-5 shrink-0 accent-primary-700" aria-invalid={Boolean(e.consent) || undefined} aria-describedby={e.consent ? "c-consent-error" : undefined} {...register("consent")} />
          <span>
            {t("consent")}{" "}
            <Link href="/privacy" className="font-medium text-primary-700 underline">{tf("privacy")}</Link>
          </span>
        </label>
        {e.consent && <p id="c-consent-error" className="mt-1 text-sm text-error" role="alert">{e.consent.message}</p>}
      </div>
      {serverError && <p className="rounded-image bg-error-100 p-3 text-sm text-error" role="alert">{serverError}</p>}
      <Button type="submit" className="self-start" loading={formState.isSubmitting}>
        {t("send")}
      </Button>
    </form>
  );
}
