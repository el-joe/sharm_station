"use client";

import { AppImage } from "@/components/ui/AppImage";
import { useAuth } from "@/lib/auth";
import { useNationalityCode } from "@/lib/nationality";
import { Button, buttonClasses } from "@/components/ui/Button";
import { Field, Input, Select, Textarea } from "@/components/ui/Field";
import { Link, useRouter } from "@/i18n/navigation";
import { api, ApiError } from "@/lib/api/client";
import type { CouponServiceType, CreateBookingRequest, Locale } from "@/lib/api/types";
import { saveBooking, type CheckoutDraft } from "@/lib/checkout";
import { useCurrency } from "@/lib/currency";
import { parseISODate } from "@/lib/utils";
import { zodResolver } from "@hookform/resolvers/zod";
import { CalendarDays, Lock, ShoppingBag, Users } from "lucide-react";
import { useFormatter, useLocale, useTranslations } from "next-intl";
import { useCallback, useMemo, useState, useSyncExternalStore } from "react";
import { useFieldArray, useForm, useWatch } from "react-hook-form";
import { z } from "zod";
import { CouponField, type AppliedCoupon } from "./CouponField";
import { PriceSummary } from "./PriceSummary";

const DRAFT_KEY = "ssv_checkout_draft";

function useDraftRaw(): string | null | undefined {
  // undefined = not hydrated yet (server), null = no draft in this tab.
  return useSyncExternalStore(
    () => () => {},
    () => {
      try {
        return sessionStorage.getItem(DRAFT_KEY);
      } catch {
        return null;
      }
    },
    () => undefined,
  );
}

/** Coupon rules use the backend service types. */
function couponService(draft: CheckoutDraft): CouponServiceType {
  if (draft.service_type === "room") return "accommodation";
  if (draft.product.href.includes("/packages/")) return "package";
  if (draft.product.href.includes("/nile/")) return "nile_trip";
  return "trip";
}

const COUNTRIES = ["IT", "GB", "DE", "FR", "ES", "US", "EG", "CH", "NL", "OTHER"] as const;

/** Checkout step 1: traveller and contact details (RHF + Zod) -> POST /bookings. */
export function CheckoutForm() {
  const raw = useDraftRaw();
  const draft = useMemo<CheckoutDraft | null>(() => {
    if (!raw) return null;
    try {
      return JSON.parse(raw) as CheckoutDraft;
    } catch {
      return null;
    }
  }, [raw]);
  const t = useTranslations("checkout");

  if (raw === undefined) return <div className="h-[600px] animate-pulse rounded-card bg-ink-100" aria-hidden />;
  if (!draft) {
    return (
      <div className="flex flex-col items-center rounded-card border border-dashed border-ink-300 px-6 py-16 text-center">
        <ShoppingBag className="size-10 text-ink-500" aria-hidden />
        <h2 className="mt-4 text-xl font-semibold text-ink-900">{t("emptyTitle")}</h2>
        <p className="mt-1 text-ink-700">{t("emptyText")}</p>
        <Link href="/trips" className={buttonClasses("cta", "md", "mt-6")}>{t("browse")}</Link>
      </div>
    );
  }
  return <CheckoutWithDraft draft={draft} />;
}

function CheckoutWithDraft({ draft }: { draft: CheckoutDraft }) {
  const t = useTranslations("checkout");
  const tv = useTranslations("validation");
  const format = useFormatter();
  const locale = useLocale() as Locale;
  const { currency } = useCurrency();
  const { token: authToken } = useAuth();
  const nationality = useNationalityCode();
  const router = useRouter();
  const [serverError, setServerError] = useState<string | null>(null);
  const [coupon, setCoupon] = useState<AppliedCoupon | null>(null);
  const [couponError, setCouponError] = useState<string | null>(null);
  const onCouponChange = useCallback((c: AppliedCoupon | null) => {
    setCoupon(c);
    setCouponError(null);
  }, []);

  const schema = useMemo(
    () =>
      z.object({
        first_name: z.string().trim().min(2, tv("min", { count: 2 })),
        last_name: z.string().trim().min(2, tv("min", { count: 2 })),
        email: z.email(tv("email")),
        phone: z
          .string()
          .trim()
          .regex(/^\+?[0-9\s().-]{7,20}$/, tv("phone")),
        country: z.string().min(1, tv("required")),
        pickup_hotel: z.string().trim().max(120).optional(),
        notes: z.string().max(1000).optional(),
        travellers: z.array(
          z.object({
            first_name: z.string().trim().min(1, tv("required")),
            last_name: z.string().trim().min(1, tv("required")),
            type: z.enum(["adult", "child"]),
            age: z.number().nullable().optional(),
          }),
        ),
        terms: z.literal(true, { error: tv("terms") }),
      }),
    [tv],
  );
  type FormValues = z.infer<typeof schema>;

  const defaultTravellers = [
    ...Array.from({ length: Math.max(0, draft.adults - 1) }, () => ({ first_name: "", last_name: "", type: "adult" as const, age: null })),
    ...Array.from({ length: draft.children }, (_, i) => ({ first_name: "", last_name: "", type: "child" as const, age: draft.child_ages[i] ?? null })),
  ];

  const { register, control, handleSubmit, formState } = useForm<FormValues>({
    resolver: zodResolver(schema),
    defaultValues: { first_name: "", last_name: "", email: "", phone: "", country: "", pickup_hotel: "", notes: "", travellers: defaultTravellers, terms: false as unknown as true },
    mode: "onTouched",
  });
  const { fields } = useFieldArray({ control, name: "travellers" });
  const e = formState.errors;
  const leadEmail = useWatch({ control, name: "email" });

  const onSubmit = handleSubmit(async (values) => {
    setServerError(null);
    setCouponError(null);
    if (!nationality) {
      setServerError(t("nationalityMissing"));
      return;
    }
    const lead = { name: `${values.first_name} ${values.last_name}`.trim(), email: values.email, phone: values.phone, country: values.country };
    const body: CreateBookingRequest = {
      service_type: draft.service_type,
      product_slug: draft.product.slug,
      product_type: draft.product.href.includes("/packages/") ? "package" : undefined,
      room_id: draft.product.room_id,
      booking_type: draft.booking_type,
      travel_date: draft.travel_date,
      check_in: draft.check_in,
      check_out: draft.check_out,
      adults: draft.adults,
      children: draft.children,
      child_ages: draft.child_ages,
      units: draft.units.map(({ unit_option_id, quantity }) => ({ unit_option_id, quantity })),
      addons: draft.addons.map(({ addon_id, quantity }) => ({ addon_id, quantity })),
      lead,
      travellers: [{ first_name: values.first_name, last_name: values.last_name, type: "adult" }, ...values.travellers],
      notes: values.notes || undefined,
      pickup_hotel: values.pickup_hotel || undefined,
      locale,
      currency,
      nationality_code: nationality,
      coupon_code: coupon?.code,
    };
    try {
      // Signed-in customers send their token so the booking is linked to their account (My bookings).
      const res = await api.createBooking(body, { locale, currency, token: authToken });
      saveBooking({ ...res, draft, lead });
      router.push(`/booking/${res.reference}`);
    } catch (err) {
      if (err instanceof ApiError && err.isValidation) {
        const { coupon_code: couponErrors, ...rest } = err.errors;
        if (couponErrors?.length) setCouponError(couponErrors[0]);
        const other = Object.values(rest).flat().join(" ");
        setServerError(other || (couponErrors?.length ? t("couponRejected") : err.message));
      } else setServerError(t("genericError"));
    }
  });

  const dateLabel = draft.travel_date
    ? format.dateTime(parseISODate(draft.travel_date), { weekday: "short", day: "numeric", month: "short", year: "numeric" })
    : draft.check_in && draft.check_out
      ? `${format.dateTime(parseISODate(draft.check_in), { day: "numeric", month: "short" })} – ${format.dateTime(parseISODate(draft.check_out), { day: "numeric", month: "short", year: "numeric" })}`
      : "";

  return (
    <div className="grid gap-8 lg:grid-cols-[minmax(0,1fr)_400px]">
      <form onSubmit={onSubmit} noValidate className="flex flex-col gap-8" aria-describedby={serverError ? "checkout-error" : undefined}>
        <fieldset className="rounded-card border border-ink-100 bg-white p-5 shadow-card md:p-8">
          <legend className="sr-only">{t("leadTitle")}</legend>
          <h2 className="text-h4 text-ink-900" aria-hidden>{t("leadTitle")}</h2>
          <p className="mt-1 text-sm text-ink-700">{t("leadText")}</p>
          <div className="mt-6 grid gap-4 md:grid-cols-2">
            <Field label={t("firstName")} htmlFor="first_name" error={e.first_name?.message} required>
              <Input id="first_name" autoComplete="given-name" invalid={Boolean(e.first_name)} aria-describedby={e.first_name ? "first_name-error" : undefined} {...register("first_name")} />
            </Field>
            <Field label={t("lastName")} htmlFor="last_name" error={e.last_name?.message} required>
              <Input id="last_name" autoComplete="family-name" invalid={Boolean(e.last_name)} aria-describedby={e.last_name ? "last_name-error" : undefined} {...register("last_name")} />
            </Field>
            <Field label={t("email")} htmlFor="email" error={e.email?.message} required hint={t("emailHint")}>
              <Input id="email" type="email" autoComplete="email" invalid={Boolean(e.email)} aria-describedby={e.email ? "email-error" : "email-hint"} {...register("email")} />
            </Field>
            <Field label={t("phone")} htmlFor="phone" error={e.phone?.message} required hint={t("phoneHint")}>
              <Input id="phone" type="tel" autoComplete="tel" placeholder="+39 333 123 4567" invalid={Boolean(e.phone)} aria-describedby={e.phone ? "phone-error" : "phone-hint"} {...register("phone")} />
            </Field>
            <Field label={t("country")} htmlFor="country" error={e.country?.message} required>
              <Select id="country" autoComplete="country" invalid={Boolean(e.country)} {...register("country")}>
                <option value="">{t("selectCountry")}</option>
                {COUNTRIES.map((c) => (
                  <option key={c} value={c}>{t(`countries.${c}`)}</option>
                ))}
              </Select>
            </Field>
            {draft.service_type === "trip" && (
              <Field label={t("pickupHotel")} htmlFor="pickup_hotel" hint={t("pickupHint")}>
                <Input id="pickup_hotel" aria-describedby="pickup_hotel-hint" {...register("pickup_hotel")} />
              </Field>
            )}
          </div>
        </fieldset>

        {fields.length > 0 && (
          <fieldset className="rounded-card border border-ink-100 bg-white p-5 shadow-card md:p-8">
            <legend className="sr-only">{t("travellersTitle")}</legend>
            <h2 className="text-h4 text-ink-900" aria-hidden>{t("travellersTitle")}</h2>
            <p className="mt-1 text-sm text-ink-700">{t("travellersText")}</p>
            <ol className="mt-6 flex flex-col gap-6">
              {fields.map((f, i) => (
                <li key={f.id} className="grid gap-4 md:grid-cols-2">
                  <p className="font-semibold text-ink-900 md:col-span-2">
                    {f.type === "adult" ? t("adultN", { n: i + 2 }) : t("childN", { n: i + 2, age: f.age ?? "?" })}
                  </p>
                  <Field label={t("firstName")} htmlFor={`t-${i}-first`} error={e.travellers?.[i]?.first_name?.message} required>
                    <Input id={`t-${i}-first`} invalid={Boolean(e.travellers?.[i]?.first_name)} {...register(`travellers.${i}.first_name`)} />
                  </Field>
                  <Field label={t("lastName")} htmlFor={`t-${i}-last`} error={e.travellers?.[i]?.last_name?.message} required>
                    <Input id={`t-${i}-last`} invalid={Boolean(e.travellers?.[i]?.last_name)} {...register(`travellers.${i}.last_name`)} />
                  </Field>
                </li>
              ))}
            </ol>
          </fieldset>
        )}

        <fieldset className="rounded-card border border-ink-100 bg-white p-5 shadow-card md:p-8">
          <legend className="sr-only">{t("notesTitle")}</legend>
          <Field label={t("notes")} htmlFor="notes" hint={t("notesHint")}>
            <Textarea id="notes" aria-describedby="notes-hint" {...register("notes")} />
          </Field>
          <div className="mt-6">
            <label className="flex items-start gap-3 text-sm text-ink-900">
              <input type="checkbox" className="mt-0.5 size-5 shrink-0 accent-primary-700" aria-invalid={Boolean(e.terms) || undefined} aria-describedby={e.terms ? "terms-error" : undefined} {...register("terms")} />
              <span>
                {t.rich("terms", {
                  terms: (c) => <Link href="/booking-terms" target="_blank" className="font-medium text-primary-700 underline">{c}</Link>,
                  privacy: (c) => <Link href="/privacy" target="_blank" className="font-medium text-primary-700 underline">{c}</Link>,
                })}
              </span>
            </label>
            {e.terms && <p id="terms-error" className="mt-1 text-sm text-error" role="alert">{e.terms.message}</p>}
          </div>
        </fieldset>

        {serverError && (
          <p id="checkout-error" className="rounded-image bg-error-100 p-4 text-error" role="alert">{serverError}</p>
        )}

        <div className="flex flex-col items-stretch gap-3 md:flex-row md:items-center md:justify-between">
          <Link href={draft.product.href} className="font-semibold text-primary-700 hover:underline">{t("back")}</Link>
          <Button type="submit" size="lg" loading={formState.isSubmitting} icon={<Lock className="size-4" aria-hidden />}>
            {t("continue")}
          </Button>
        </div>
      </form>

      <aside className="lg:sticky lg:top-28 lg:self-start" aria-label={t("summaryTitle")}>
        <div className="flex flex-col gap-5 rounded-card border border-ink-100 bg-white p-5 shadow-card md:p-6">
          <div className="flex gap-4">
            <span className="relative size-20 shrink-0 overflow-hidden rounded-image bg-ink-100">
              <AppImage src={draft.product.image} alt="" fill sizes="80px" className="object-cover" />
            </span>
            <div>
              <h2 className="font-semibold text-ink-900">{draft.product.title}</h2>
              {draft.product.subtitle && <p className="text-sm text-ink-700">{draft.product.subtitle}</p>}
            </div>
          </div>
          <ul className="flex flex-col gap-2 text-sm text-ink-700">
            <li className="flex items-center gap-2"><CalendarDays className="size-4" aria-hidden />{dateLabel}</li>
            <li className="flex items-center gap-2"><Users className="size-4" aria-hidden />{t("party", { adults: draft.adults, children: draft.children })}{draft.booking_type ? ` · ${t(`type.${draft.booking_type}`)}` : ""}</li>
            {draft.season_note && <li className="text-xs font-medium text-ink-900">{t("season", { season: draft.season_note })}</li>}
          </ul>
          <PriceSummary quote={draft.quote} discount={coupon ? { code: coupon.code, amount: coupon.discount_eur } : null} />
          <CouponField
            serviceType={couponService(draft)}
            totalEur={draft.quote.total}
            email={leadEmail}
            token={authToken}
            applied={coupon}
            onChange={onCouponChange}
            serverError={couponError}
          />
          <p className="text-xs text-ink-500">{t("currencyNote")}</p>
        </div>
      </aside>
    </div>
  );
}
