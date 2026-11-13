"use client";

import { Button } from "@/components/ui/Button";
import { Field, Input, Select } from "@/components/ui/Field";
import { Link, usePathname, useRouter } from "@/i18n/navigation";
import { api, ApiError } from "@/lib/api/client";
import type { CurrencyCode, Locale } from "@/lib/api/types";
import { useAuth } from "@/lib/auth";
import { useCurrency } from "@/lib/currency";
import { zodResolver } from "@hookform/resolvers/zod";
import { CheckCircle2, Info } from "lucide-react";
import { useLocale, useTranslations } from "next-intl";
import { useMemo, useState, type ReactNode } from "react";
import { useForm } from "react-hook-form";
import { z } from "zod";

type Notice = { tone: "success" | "info" | "error"; text: string } | null;

function NoticeBox({ notice }: { notice: Notice }) {
  if (!notice) return null;
  const cls = notice.tone === "success" ? "bg-success-100 text-ink-900" : notice.tone === "info" ? "bg-primary-100 text-primary-700" : "bg-error-100 text-error";
  return (
    <p className={`flex items-start gap-2 rounded-image p-3 text-sm ${cls}`} role={notice.tone === "error" ? "alert" : "status"}>
      {notice.tone === "success" ? <CheckCircle2 className="mt-0.5 size-4 shrink-0 text-success" aria-hidden /> : <Info className="mt-0.5 size-4 shrink-0" aria-hidden />}
      {notice.text}
    </p>
  );
}

function Card({ id, title, children }: { id: string; title: string; children: ReactNode }) {
  return (
    <section className="rounded-card border border-ink-100 bg-white p-5 shadow-card md:p-6" aria-labelledby={id}>
      <h2 id={id} className="font-display text-xl font-semibold text-ink-900 lg:text-2xl">{title}</h2>
      <div className="mt-5">{children}</div>
    </section>
  );
}

function fieldErrors<T extends Record<string, unknown>>(err: unknown, setError: (name: keyof T & string, e: { message: string }) => void, names: (keyof T & string)[]) {
  if (!(err instanceof ApiError) || !err.isValidation) return false;
  let mapped = false;
  for (const [k, msgs] of Object.entries(err.errors)) {
    if (names.includes(k as keyof T & string)) {
      setError(k as keyof T & string, { message: msgs[0] });
      mapped = true;
    }
  }
  return mapped;
}

/** /account/profile: personal details + preferences (PATCH /me/profile), password (PUT /me/password), sign out. Both PENDING BACKEND. */
export function ProfileForm() {
  const t = useTranslations("account.profile");
  const tv = useTranslations("validation");
  const locale = useLocale() as Locale;
  const { user, token, setUser, signOut } = useAuth();
  const { currency, currencies, setCurrency } = useCurrency();
  const router = useRouter();
  const pathname = usePathname();
  const [notice, setNotice] = useState<Notice>(null);
  const [pwNotice, setPwNotice] = useState<Notice>(null);

  const profileSchema = useMemo(
    () =>
      z.object({
        name: z.string().trim().min(2, tv("min", { count: 2 })),
        phone: z.string().trim().regex(/^$|^\+?[0-9 ()-]{6,20}$/, tv("phone")),
        locale: z.enum(["en", "it"]),
        currency: z.enum(["EUR", "USD", "GBP", "EGP"]),
        newsletter: z.boolean(),
      }),
    [tv],
  );
  type ProfileValues = z.infer<typeof profileSchema>;
  const profile = useForm<ProfileValues>({
    resolver: zodResolver(profileSchema),
    mode: "onTouched",
    values: { name: user?.name ?? "", phone: user?.phone ?? "", locale, currency, newsletter: false },
    resetOptions: { keepDirtyValues: true },
  });

  const pwSchema = useMemo(
    () =>
      z
        .object({
          current_password: z.string().min(1, tv("required")),
          password: z.string().min(8, tv("password")),
          password_confirmation: z.string(),
        })
        .refine((v) => v.password === v.password_confirmation, { path: ["password_confirmation"], message: tv("passwordMatch") }),
    [tv],
  );
  type PwValues = z.infer<typeof pwSchema>;
  const pw = useForm<PwValues>({ resolver: zodResolver(pwSchema), mode: "onTouched", defaultValues: { current_password: "", password: "", password_confirmation: "" } });

  const applyLocal = (v: ProfileValues) => {
    if (v.currency !== currency) setCurrency(v.currency as CurrencyCode);
    if (v.locale !== locale) router.replace(pathname, { locale: v.locale });
  };

  const onProfile = profile.handleSubmit(async (v) => {
    setNotice(null);
    try {
      const res = await api.updateProfile({ name: v.name, phone: v.phone || undefined, locale: v.locale, currency: v.currency, newsletter: v.newsletter }, { locale, token: token! });
      if (res.status === "ok") {
        setUser(res.data);
        setNotice({ tone: "success", text: t("saved") });
      } else setNotice({ tone: "info", text: t("pending") });
      applyLocal(v);
    } catch (err) {
      if (err instanceof ApiError && err.status === 401) return void signOut();
      if (!fieldErrors<ProfileValues>(err, profile.setError, ["name", "phone"])) setNotice({ tone: "error", text: t("error") });
    }
  });

  const onPassword = pw.handleSubmit(async (v) => {
    setPwNotice(null);
    try {
      const res = await api.updatePassword(v, { locale, token: token! });
      if (res.status === "ok") {
        pw.reset();
        setPwNotice({ tone: "success", text: t("passwordSaved") });
      } else setPwNotice({ tone: "info", text: t("passwordPending") });
    } catch (err) {
      if (err instanceof ApiError && err.status === 401) return void signOut();
      if (!fieldErrors<PwValues>(err, pw.setError, ["current_password", "password"])) setPwNotice({ tone: "error", text: t("error") });
    }
  });

  const pe = profile.formState.errors;
  const we = pw.formState.errors;

  return (
    <div className="mt-8 grid gap-6 lg:grid-cols-[minmax(0,1fr)_380px]">
      <div className="flex flex-col gap-6">
        <Card id="personal-title" title={t("personal")}>
          <form onSubmit={onProfile} noValidate className="flex flex-col gap-4">
            <div className="grid gap-4 sm:grid-cols-2">
              <Field label={t("name")} htmlFor="p-name" error={pe.name?.message} required className="sm:col-span-2">
                <Input id="p-name" autoComplete="name" invalid={Boolean(pe.name)} {...profile.register("name")} />
              </Field>
              <Field label={t("email")} htmlFor="p-email" hint={t("emailHint")}>
                <Input id="p-email" type="email" value={user?.email ?? ""} readOnly aria-readonly />
              </Field>
              <Field label={t("phone")} htmlFor="p-phone" error={pe.phone?.message}>
                <Input id="p-phone" type="tel" autoComplete="tel" invalid={Boolean(pe.phone)} {...profile.register("phone")} />
              </Field>
            </div>
            <h3 className="mt-2 font-semibold text-ink-900">{t("preferences")}</h3>
            <div className="grid gap-4 sm:grid-cols-2">
              <Field label={t("language")} htmlFor="p-locale">
                <Select id="p-locale" {...profile.register("locale")}>
                  <option value="en">English (EN)</option>
                  <option value="it">Italiano (IT)</option>
                </Select>
              </Field>
              <Field label={t("currency")} htmlFor="p-currency">
                <Select id="p-currency" {...profile.register("currency")}>
                  {currencies.map((c) => (
                    <option key={c.code} value={c.code}>
                      {c.code} {c.symbol}
                    </option>
                  ))}
                </Select>
              </Field>
            </div>
            <label className="flex items-start gap-3 text-sm text-ink-700">
              <input type="checkbox" className="mt-0.5 size-5 shrink-0 accent-primary-700" {...profile.register("newsletter")} />
              {t("newsletter")}
            </label>
            <p className="text-xs text-ink-500">{t("preferencesNote")}</p>
            <NoticeBox notice={notice} />
            <Button type="submit" className="self-start" loading={profile.formState.isSubmitting}>
              {t("save")}
            </Button>
          </form>
        </Card>
      </div>
      <div className="flex flex-col gap-6">
        <Card id="password-title" title={t("passwordTitle")}>
          <form onSubmit={onPassword} noValidate className="flex flex-col gap-4">
            <Field label={t("currentPassword")} htmlFor="pw-current" error={we.current_password?.message} required>
              <Input id="pw-current" type="password" autoComplete="current-password" invalid={Boolean(we.current_password)} {...pw.register("current_password")} />
            </Field>
            <Field label={t("newPassword")} htmlFor="pw-new" error={we.password?.message} hint={t("passwordHint")} required>
              <Input id="pw-new" type="password" autoComplete="new-password" invalid={Boolean(we.password)} {...pw.register("password")} />
            </Field>
            <Field label={t("confirmPassword")} htmlFor="pw-confirm" error={we.password_confirmation?.message} required>
              <Input id="pw-confirm" type="password" autoComplete="new-password" invalid={Boolean(we.password_confirmation)} {...pw.register("password_confirmation")} />
            </Field>
            <NoticeBox notice={pwNotice} />
            <Button type="submit" className="w-full" loading={pw.formState.isSubmitting}>
              {t("updatePassword")}
            </Button>
          </form>
        </Card>
        <Card id="account-title" title={t("accountTitle")}>
          <Button
            variant="outline"
            className="w-full"
            onClick={async () => {
              await signOut();
              router.push("/");
            }}
          >
            {t("signOut")}
          </Button>
          <p className="mt-4 text-sm text-ink-700">{t("deleteText")}</p>
          <Link href={{ pathname: "/contact", query: { subject: "other" } }} className="mt-2 inline-block text-sm font-semibold text-primary-700 underline">
            {t("deleteLink")}
          </Link>
        </Card>
      </div>
    </div>
  );
}
