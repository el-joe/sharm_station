"use client";

import { Button } from "@/components/ui/Button";
import { Field, Input } from "@/components/ui/Field";
import { Link } from "@/i18n/navigation";
import { api, ApiError } from "@/lib/api/client";
import type { Locale } from "@/lib/api/types";
import { useLocationSearch } from "@/lib/hooks";
import { zodResolver } from "@hookform/resolvers/zod";
import { MailCheck } from "lucide-react";
import { useLocale, useTranslations } from "next-intl";
import { useMemo, useState } from "react";
import { useForm } from "react-hook-form";
import { z } from "zod";

/**
 * /forgot-password: POST /auth/forgot-password (public, called directly). The API answers 200 for any e-mail, so the
 * page always shows the same neutral message (an e-mail address cannot be probed). `?email=` pre-fills the field
 * (link from an expired reset page).
 */
export function ForgotPasswordForm() {
  const t = useTranslations("passwordReset");
  const tv = useTranslations("validation");
  const locale = useLocale() as Locale;
  const prefill = new URLSearchParams(useLocationSearch()).get("email") ?? "";
  const [sent, setSent] = useState(false);
  const [serverError, setServerError] = useState<string | null>(null);

  const schema = useMemo(() => z.object({ email: z.email(tv("email")) }), [tv]);
  const { register, handleSubmit, formState } = useForm<{ email: string }>({ resolver: zodResolver(schema), mode: "onTouched", values: { email: prefill }, resetOptions: { keepDirtyValues: true } });
  const e = formState.errors;

  const onSubmit = handleSubmit(async (v) => {
    setServerError(null);
    try {
      await api.forgotPassword(v.email.trim(), { locale });
      setSent(true);
    } catch (err) {
      if (err instanceof ApiError && err.status === 429) setServerError(t("tooMany"));
      else if (err instanceof ApiError && err.isValidation) setServerError(Object.values(err.errors).flat()[0] ?? t("error"));
      else setServerError(t("error"));
    }
  });

  if (sent) {
    return (
      <div className="flex flex-col gap-4">
        <p className="flex items-start gap-3 rounded-image border border-success bg-success-100 p-4 text-ink-900" role="status" data-testid="forgot-sent">
          <MailCheck className="mt-0.5 size-5 shrink-0 text-success" aria-hidden />
          {/* Our own text (not the API's) so the page reads the same for every address and in both languages. */}
          <span>{t("sent")}</span>
        </p>
        <p className="text-sm text-ink-700">{t("sentHint")}</p>
        <Link href="/sign-in" className="text-center font-semibold text-primary-700 underline">
          {t("backToSignIn")}
        </Link>
      </div>
    );
  }

  return (
    <form onSubmit={onSubmit} noValidate className="flex flex-col gap-4">
      <Field label={t("email")} htmlFor="forgot-email" error={e.email?.message} required>
        <Input id="forgot-email" type="email" autoComplete="email" invalid={Boolean(e.email)} {...register("email")} />
      </Field>
      {serverError && (
        <p className="rounded-image bg-error-100 p-3 text-sm text-error" role="alert">
          {serverError}
        </p>
      )}
      <Button type="submit" size="lg" className="mt-2 w-full" loading={formState.isSubmitting}>
        {t("send")}
      </Button>
      <p className="text-center text-sm text-ink-700">
        <Link href="/sign-in" className="font-semibold text-primary-700 underline">
          {t("backToSignIn")}
        </Link>
      </p>
    </form>
  );
}
