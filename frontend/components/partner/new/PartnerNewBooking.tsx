"use client";

import { api, ApiError } from "@/lib/api/client";
import type { CatalogueService, CouponServiceType, Locale, PartnerBookingDetail, PartnerCatalogueItem } from "@/lib/api/types";
import { useAuth } from "@/lib/auth";
import { useCurrency } from "@/lib/currency";
import { useQueryClient } from "@tanstack/react-query";
import { useLocale, useTranslations } from "next-intl";
import { useEffect, useRef, useState } from "react";
import { BookingStepper, PartnerPageHeader } from "../shared";
import { useClientNationality } from "../useClientNationality";
import { CatalogueStep, type CatalogueFilters } from "./CatalogueStep";
import { ClientStep, type ClientValues } from "./ClientStep";
import { ConfigureStep, type Configured } from "./ConfigureStep";
import { ConfirmationStep } from "./ConfirmationStep";

/**
 * /partner/new (D10, CR-15): the partner booking flow. Catalogue at partner prices for the client's
 * nationality -> configure with a live POST /partner/quotes -> client & payment -> POST /partner/bookings
 * -> confirmation with vouchers. The public website is never used for partner bookings.
 */
const COUPON_SERVICE: Record<CatalogueService, CouponServiceType> = {
  single_day_trips: "trip",
  multi_day_trips: "trip",
  nile_trips: "nile_trip",
  packages: "package",
  transfers: "transfer",
  accommodation: "accommodation",
};

export function PartnerNewBooking() {
  const t = useTranslations("partnerPanel.newBooking");
  const ts = useTranslations("partnerPanel.services");
  const locale = useLocale() as Locale;
  const { currency } = useCurrency();
  const { token, signOut } = useAuth();
  const queryClient = useQueryClient();
  const [nationality, setNationality] = useClientNationality();
  const [filters, setFilters] = useState<CatalogueFilters>({ service: "single_day_trips", q: "", destination: "", date: "" });
  const [item, setItem] = useState<PartnerCatalogueItem | null>(null);
  const [config, setConfig] = useState<Configured | null>(null);
  const [client, setClient] = useState<ClientValues | null>(null);
  const [booking, setBooking] = useState<PartnerBookingDetail | null>(null);
  const [error, setError] = useState<string | null>(null);
  const [couponError, setCouponError] = useState<string | null>(null);
  const [editing, setEditing] = useState(false);
  const step: 1 | 2 | 3 | 4 = booking ? 4 : config && item && !editing ? 3 : item ? 2 : 1;
  const titleRef = useRef<HTMLDivElement>(null);

  // Move focus and scroll to the top of the flow on every step change.
  const first = useRef(true);
  useEffect(() => {
    if (first.current) {
      first.current = false;
      return;
    }
    titleRef.current?.querySelector("h1")?.focus();
    window.scrollTo({ top: 0, behavior: "smooth" });
  }, [step]);

  const serviceLabel = (s: CatalogueService) => ts(s);
  const titles = { 1: t("title"), 2: t("configure.title"), 3: t("client.title"), 4: t("confirmation.heading") } as const;

  const changeNationality = (code: string) => {
    setNationality(code);
    // A configured quote was priced for the previous nationality.
    setConfig(null);
  };

  const submit = async (v: ClientValues) => {
    if (!item || !config || !nationality || !token) return;
    setError(null);
    setCouponError(null);
    setClient(v);
    try {
      const res = await api.partnerCreateBooking(
        {
          ...config.request,
          nationality_code: nationality,
          lead: { name: `${v.first_name} ${v.last_name}`.trim(), email: v.email, phone: v.phone },
          travelers: item.product_type === "transfer" ? undefined : [{ first_name: v.first_name, last_name: v.last_name, type: "adult" }, ...v.travellers],
          payment_method: v.payment,
          coupon_code: v.coupon_code,
          notes: [v.notes.trim(), v.voucher === "without_prices" ? "Voucher: without prices" : "Voucher: with prices"].filter(Boolean).join("\n"),
        },
        { locale, currency, token },
      );
      setBooking(res);
      void queryClient.invalidateQueries({ queryKey: ["partner"] });
    } catch (err) {
      if (err instanceof ApiError && err.status === 401) return void signOut();
      if (err instanceof ApiError && err.isValidation) {
        const { coupon_code: couponErrors, ...rest } = err.errors;
        if (couponErrors?.length) setCouponError(couponErrors[0]);
        setError([...new Set(Object.values(rest).flat())].join(" ") || (couponErrors?.length ? t("client.couponRejected") : err.message));
      }
      else setError(t("client.error"));
    }
  };

  const restart = () => {
    setItem(null);
    setConfig(null);
    setClient(null);
    setBooking(null);
    setError(null);
  };

  return (
    <div className="flex flex-col gap-6">
      <div ref={titleRef} className="flex flex-col gap-4 [&_h1]:outline-none">
        <PartnerPageHeader
          crumbs={step === 1 ? [{ label: t("crumb") }] : [{ label: t("crumb"), href: "/partner/new" }, { label: titles[step] }]}
          title={titles[step]}
          subtitle={step === 1 ? t("subtitle") : undefined}
        />
        <BookingStepper step={step} />
      </div>

      {step === 1 && (
        <CatalogueStep
          nationality={nationality}
          onNationality={changeNationality}
          filters={filters}
          onFilters={setFilters}
          onSelect={(i) => {
            setItem(i);
            setConfig(null);
            setEditing(false);
          }}
        />
      )}
      {step === 2 && item && nationality && (
        <ConfigureStep
          item={item}
          nationality={nationality}
          onNationality={changeNationality}
          serviceLabel={serviceLabel(item.service)}
          initial={config}
          onBack={() => setItem(null)}
          onContinue={(c) => {
            setConfig(c);
            setEditing(false);
          }}
        />
      )}
      {step === 3 && item && config && nationality && (
        <ClientStep
          title={item.title}
          config={config}
          nationality={nationality}
          serviceLabel={serviceLabel(item.service)}
          isTransfer={item.product_type === "transfer"}
          initial={client}
          error={error}
          couponError={couponError}
          couponService={COUPON_SERVICE[item.service]}
          onBack={() => setEditing(true)}
          onSubmit={submit}
        />
      )}
      {step === 4 && booking && item && (
        <ConfirmationStep booking={booking} subtitle={config?.subtitle ?? ""} voucher={client?.voucher ?? "without_prices"} serviceLabel={serviceLabel(item.service)} onNew={restart} />
      )}
    </div>
  );
}
