"use client";

import { Button } from "@/components/ui/Button";
import { Field, Input } from "@/components/ui/Field";
import { Link, useRouter } from "@/i18n/navigation";
import { api, ApiError } from "@/lib/api/client";
import type { Locale } from "@/lib/api/types";
import { useAuth } from "@/lib/auth";
import { useLocationSearch } from "@/lib/hooks";
import { cn } from "@/lib/utils";
import { zodResolver } from "@hookform/resolvers/zod";
import { Check, Circle } from "lucide-react";
import { useLocale, useTranslations } from "next-intl";
import { useMemo, useState } from "react";
import { useForm, useWatch } from "react-hook-form";
import { z } from "zod";

/** The API's rule (Laravel Password::defaults()): at least 8 characters, confirmed. */
const MIN_LENGTH = 8;

/**
 * /reset-password?token=&email=: the link of the "Welcome, set your password" (7 days) and "Forgot password"
 * (60 minutes) e-mails. POST /auth/reset-password (public, called directly) returns no token: on success the visitor
 * goes to sign-in with a notice. A signed-in session of the same account is cleared (the API revokes its tokens);
 * nobody is signed in by this page.
 */
export function ResetPasswordForm() {
  const t = useTranslations("passwordReset");
  const tv = useTranslations("validation");
  const locale = useLocale() as Locale;
  const router = useRouter();
  const { user, hasSession, signOut } = useAuth();
  const search = new URLSearchParams(useLocationSearch());
  const token = search.get("token") ?? "";
  const email = search.get("email") ?? "";
  const [serverError, setServerError] = useState<string | null>(null);
  const [linkError, setLinkError] = useState<string | null>(null);

  const schema = useMemo(
    () =>
      z
        .object({ password: z.string().min(MIN_LENGTH, tv("password")), password_confirmation: z.string() })
        .refine((v) => v.password === v.password_confirmation, { path: ["password_confirmation"], message: tv("passwordMatch") }),
    [tv],
  );
  type Values = z.infer<typeof schema>;
  const { register, handleSubmit, formState, control, setError } = useForm<Values>({ resolver: zodResolver(schema), mode: "onTouched", defaultValues: { password: "", password_confirmation: "" } });
  const e = formState.errors;
  const password = useWatch({ control, name: "password" }) ?? "";
  const confirmation = useWatch({ control, name: "password_confirmation" }) ?? "";
  const rules = [
    { key: "length", ok: password.length >= MIN_LENGTH, text: t("ruleLength", { count: MIN_LENGTH }) },
    { key: "match", ok: password.length > 0 && password === confirmation, text: t("ruleMatch") },
  ];

  const forgotHref = { pathname: "/forgot-password", query: email ? { email } : undefined } as const;

  if (!token || !email) {
    return (
      <div className="flex flex-col gap-4">
        <p className="rounded-image bg-error-100 p-3 text-sm text-error" role="alert">
          {t("incomplete")}
        </p>
        <Link href={forgotHref} className="text-center font-semibold text-primary-700 underline">
          {t("askNewLink")}
        </Link>
      </div>
    );
  }

  const onSubmit = handleSubmit(async (v) => {
    setServerError(null);
    setLinkError(null);
    try {
      await api.resetPassword({ token, email, password: v.password, password_confirmation: v.password_confirmation }, { locale });
      // The API revoked every token of the account: drop a session of the same account; never sign anybody in.
      if (hasSession && user?.email?.toLowerCase() === email.toLowerCase()) await signOut().catch(() => {});
      router.replace({ pathname: "/sign-in", query: { reset: "1" } });
    } catch (err) {
      if (err instanceof ApiError && err.isValidation) {
        const { token: tokenErrors, email: emailErrors, password: passwordErrors, password_confirmation: confirmErrors, ...rest } = err.errors;
        if (tokenErrors?.length || emailErrors?.length) setLinkError(t("expired"));
        if (passwordErrors?.length) setError("password", { message: passwordErrors[0] });
        if (confirmErrors?.length) setError("password_confirmation", { message: confirmErrors[0] });
        const other = Object.values(rest).flat();
        if (other.length) setServerError(other[0]);
      } else if (err instanceof ApiError && err.status === 429) setServerError(t("tooMany"));
      else setServerError(t("error"));
    }
  });

  return (
    <form onSubmit={onSubmit} noValidate className="flex flex-col gap-4">
      <p className="text-sm text-ink-700">{t("forAccount", { email })}</p>
      {/* Lets password managers save the new password for the right account. */}
      <input type="email" name="email" autoComplete="username" value={email} readOnly hidden />
      <Field label={t("newPassword")} htmlFor="reset-password" error={e.password?.message} required>
        <Input id="reset-password" type="password" autoComplete="new-password" invalid={Boolean(e.password)} aria-describedby="reset-rules" {...register("password")} />
      </Field>
      <Field label={t("confirmPassword")} htmlFor="reset-password-confirmation" error={e.password_confirmation?.message} required>
        <Input id="reset-password-confirmation" type="password" autoComplete="new-password" invalid={Boolean(e.password_confirmation)} {...register("password_confirmation")} />
      </Field>
      <ul id="reset-rules" className="flex flex-col gap-1 text-sm" aria-label={t("rulesTitle")}>
        {rules.map((r) => (
          <li key={r.key} className={cn("flex items-center gap-2", r.ok ? "text-success" : "text-ink-700")} data-ok={r.ok}>
            {r.ok ? <Check className="size-4 shrink-0" aria-hidden /> : <Circle className="size-4 shrink-0 text-ink-300" aria-hidden />}
            <span>
              {r.text}
              <span className="sr-only">{r.ok ? ` (${t("ruleMet")})` : ""}</span>
            </span>
          </li>
        ))}
      </ul>
      {linkError && (
        <div className="flex flex-col gap-2 rounded-image bg-error-100 p-3 text-sm text-error" role="alert" data-testid="reset-link-error">
          <p>{linkError}</p>
          <Link href={forgotHref} className="font-semibold underline">
            {t("askNewLink")}
          </Link>
        </div>
      )}
      {serverError && (
        <p className="rounded-image bg-error-100 p-3 text-sm text-error" role="alert">
          {serverError}
        </p>
      )}
      <Button type="submit" size="lg" className="mt-2 w-full" loading={formState.isSubmitting}>
        {t("save")}
      </Button>
    </form>
  );
}
