"use client";

import { Button } from "@/components/ui/Button";
import { api, ApiError } from "@/lib/api/client";
import type { Locale } from "@/lib/api/types";
import { zodResolver } from "@hookform/resolvers/zod";
import { CheckCircle2, Mail } from "lucide-react";
import { useLocale, useTranslations } from "next-intl";
import { useState } from "react";
import { useForm } from "react-hook-form";
import { z } from "zod";

export function NewsletterBand() {
  const t = useTranslations("newsletter");
  const locale = useLocale() as Locale;
  const [done, setDone] = useState(false);
  const schema = z.object({ email: z.email(t("invalid")) });
  const { register, handleSubmit, formState, setError } = useForm<z.infer<typeof schema>>({ resolver: zodResolver(schema) });

  const onSubmit = handleSubmit(async ({ email }) => {
    try {
      await api.newsletter(email, { locale });
      setDone(true);
    } catch (err) {
      setError("email", { message: err instanceof ApiError && err.isValidation ? (Object.values(err.errors).flat()[0] ?? t("invalid")) : t("invalid") });
    }
  });

  return (
    <section className="bg-sand-50" aria-labelledby="newsletter-title">
      <div className="container-page grid gap-6 py-12 lg:grid-cols-2 lg:items-center lg:py-16">
        <div>
          <h2 id="newsletter-title" className="font-display text-2xl font-semibold text-ink-900 lg:text-[28px] lg:leading-9">
            {t("title")}
          </h2>
          <p className="mt-2 text-ink-700">{t("subtitle")}</p>
        </div>
        {done ? (
          <p className="inline-flex items-center gap-2 font-semibold text-success" role="status">
            <CheckCircle2 className="size-5" aria-hidden /> {t("success")}
          </p>
        ) : (
          <form onSubmit={onSubmit} noValidate className="flex flex-col gap-1">
            <label htmlFor="newsletter-email" className="text-sm text-ink-700">
              {t("email")}
            </label>
            <div className="flex flex-col gap-3 sm:flex-row">
              <div className="relative flex-1">
                <Mail className="pointer-events-none absolute start-4 top-1/2 size-5 -translate-y-1/2 text-ink-500" aria-hidden />
                <input
                  id="newsletter-email"
                  type="email"
                  autoComplete="email"
                  placeholder="name@example.com"
                  aria-invalid={Boolean(formState.errors.email) || undefined}
                  aria-describedby="newsletter-help"
                  className="h-12 w-full rounded-control border border-ink-300 bg-ink-100 ps-12 pe-4 focus:border-primary-500 focus:bg-white focus:outline-none aria-[invalid=true]:border-error"
                  {...register("email")}
                />
              </div>
              <Button type="submit" size="md" className="h-12" loading={formState.isSubmitting}>
                {t("subscribe")}
              </Button>
            </div>
            {formState.errors.email ? (
              <p className="text-sm text-error" role="alert">
                {formState.errors.email.message}
              </p>
            ) : (
              <p id="newsletter-help" className="text-xs text-ink-500">
                {t("consent")}
              </p>
            )}
          </form>
        )}
      </div>
    </section>
  );
}
