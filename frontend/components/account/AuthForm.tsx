"use client";

import { Button } from "@/components/ui/Button";
import { Field, Input } from "@/components/ui/Field";
import { Link, useRouter } from "@/i18n/navigation";
import { api, ApiError } from "@/lib/api/client";
import { safeReturnTo, useAuth } from "@/lib/auth";
import { useLocationSearch } from "@/lib/hooks";
import type { Locale } from "@/lib/api/types";
import { zodResolver } from "@hookform/resolvers/zod";
import { useLocale, useTranslations } from "next-intl";
import { useMemo, useState } from "react";
import { useForm } from "react-hook-form";
import { z } from "zod";

/** Sign in / sign up (POST /auth/login, /auth/register; token handed to AuthProvider, see lib/auth.tsx). */
export function AuthForm({ mode }: { mode: "sign-in" | "sign-up" }) {
  const t = useTranslations("auth");
  const tv = useTranslations("validation");
  const locale = useLocale() as Locale;
  const router = useRouter();
  const { signIn } = useAuth();
  const search = useLocationSearch();
  const returnTo = safeReturnTo(new URLSearchParams(search).get("returnTo"));
  const [serverError, setServerError] = useState<string | null>(null);

  const schema = useMemo(() => {
    const base = z.object({
      name: mode === "sign-up" ? z.string().trim().min(2, tv("min", { count: 2 })) : z.string().optional(),
      email: z.email(tv("email")),
      password: z.string().min(8, tv("password")),
      password_confirmation: z.string().optional(),
    });
    return mode === "sign-up"
      ? base.refine((v) => v.password === v.password_confirmation, { path: ["password_confirmation"], message: tv("passwordMatch") })
      : base;
  }, [mode, tv]);
  type Values = z.infer<typeof schema>;
  const { register, handleSubmit, formState } = useForm<Values>({ resolver: zodResolver(schema), mode: "onTouched" });
  const e = formState.errors;

  const onSubmit = handleSubmit(async (v) => {
    setServerError(null);
    try {
      const res =
        mode === "sign-in"
          ? await api.login({ email: v.email, password: v.password }, { locale })
          : await api.register({ name: v.name ?? "", email: v.email, password: v.password, password_confirmation: v.password_confirmation ?? "" }, { locale });
      signIn(res.token, res.user);
      const isPartner = res.user.role === "partner" || Boolean(res.user.partner);
      router.push(returnTo ?? (isPartner ? "/partner" : "/account/bookings"));
    } catch (err) {
      setServerError(err instanceof ApiError && (err.isValidation || err.status === 401) ? Object.values(err.errors).flat()[0] ?? t("invalid") : t("error"));
    }
  });

  return (
    <form onSubmit={onSubmit} noValidate className="flex flex-col gap-4">
      {returnTo && (
        <p className="rounded-image bg-primary-100 p-3 text-sm text-primary-700" role="status">
          {t("continueNote")}
        </p>
      )}
      {mode === "sign-up" && (
        <Field label={t("name")} htmlFor="name" error={e.name?.message} required>
          <Input id="name" autoComplete="name" invalid={Boolean(e.name)} {...register("name")} />
        </Field>
      )}
      <Field label={t("email")} htmlFor="email" error={e.email?.message} required>
        <Input id="email" type="email" autoComplete="email" invalid={Boolean(e.email)} {...register("email")} />
      </Field>
      <Field label={t("password")} htmlFor="password" error={e.password?.message} required hint={mode === "sign-up" ? t("passwordHint") : undefined}>
        <Input id="password" type="password" autoComplete={mode === "sign-in" ? "current-password" : "new-password"} invalid={Boolean(e.password)} {...register("password")} />
      </Field>
      {mode === "sign-up" && (
        <Field label={t("confirmPassword")} htmlFor="password_confirmation" error={e.password_confirmation?.message} required>
          <Input id="password_confirmation" type="password" autoComplete="new-password" invalid={Boolean(e.password_confirmation)} {...register("password_confirmation")} />
        </Field>
      )}
      {serverError && <p className="rounded-image bg-error-100 p-3 text-sm text-error" role="alert">{serverError}</p>}
      <Button type="submit" size="lg" className="mt-2 w-full" loading={formState.isSubmitting}>
        {mode === "sign-in" ? t("signIn") : t("createAccount")}
      </Button>
      <p className="text-center text-sm text-ink-700">
        {mode === "sign-in" ? t("noAccount") : t("haveAccount")}{" "}
        <Link href={{ pathname: mode === "sign-in" ? "/sign-up" : "/sign-in", query: returnTo ? { returnTo } : undefined }} className="font-semibold text-primary-700 underline">
          {mode === "sign-in" ? t("signUpLink") : t("signInLink")}
        </Link>
      </p>
    </form>
  );
}
