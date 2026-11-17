"use client";

import { PriceSummary } from "@/components/booking/PriceSummary";
import { useNationalityCode } from "@/lib/nationality";
import { Badge, BadgeIcons } from "@/components/ui/Badge";
import { BottomSheet } from "@/components/ui/BottomSheet";
import { Button } from "@/components/ui/Button";
import { QuantityStepper } from "@/components/ui/QuantityStepper";
import { useRouter } from "@/i18n/navigation";
import { api, ApiError } from "@/lib/api/client";
import type { Locale, RoomDetail, RoomQuoteRequest } from "@/lib/api/types";
import { saveDraft } from "@/lib/checkout";
import { Price, useCurrency } from "@/lib/currency";
import { useDebounced, useLocationSearch, useToday } from "@/lib/hooks";
import { addDays, nightsBetween, parseISODate, toISODate } from "@/lib/utils";
import { keepPreviousData, useQuery } from "@tanstack/react-query";
import { AlertCircle, Baby, Lock } from "lucide-react";
import { useLocale, useTranslations } from "next-intl";
import { useState } from "react";

/** Room booking widget (SRS 6.3): dates, guests, live price night by night, tax badge, deposit, Reserve. */
export function RoomBookingWidget({ room }: { room: RoomDetail }) {
  const t = useTranslations("roomBooking");
  const tb = useTranslations("booking");
  const locale = useLocale() as Locale;
  const { currency } = useCurrency();
  const nationality = useNationalityCode();
  const router = useRouter();
  const today = useToday();
  // Dates/guests chosen on the listing are carried over in the query string.
  const search = new URLSearchParams(useLocationSearch());
  const isoOrNull = (v: string | null) => (v && /^\d{4}-\d{2}-\d{2}$/.test(v) ? v : null);
  const [inState, setIn] = useState<string | null>(null);
  const [outState, setOut] = useState<string | null>(null);
  const [adultsState, setAdults] = useState<number | null>(null);
  const [childrenState, setChildren] = useState<number | null>(null);
  const adults = adultsState ?? Math.min(Math.max(1, Number(search.get("adults")) || 2), room.max_adults);
  const children = childrenState ?? Math.min(Math.max(0, Number(search.get("children")) || 0), room.max_children);
  const [sheet, setSheet] = useState(false);

  const checkIn = inState ?? isoOrNull(search.get("check_in")) ?? (today ? toISODate(addDays(parseISODate(today), 14)) : "");
  const checkOut = outState ?? isoOrNull(search.get("check_out")) ?? (checkIn ? toISODate(addDays(parseISODate(checkIn), Math.max(3, room.min_nights))) : "");
  const nights = checkIn && checkOut ? nightsBetween(checkIn, checkOut) : 0;

  const errors: string[] = [];
  if (checkIn && checkOut) {
    if (nights < room.min_nights) errors.push(t("minNights", { count: room.min_nights }));
    if (nights > room.max_nights) errors.push(t("maxNights", { count: room.max_nights }));
  }
  if (adults + children > room.max_total) errors.push(t("maxGuests", { count: room.max_total }));

  const request: RoomQuoteRequest = { room_id: room.id, check_in: checkIn, check_out: checkOut, adults, children, child_ages: [] };
  const debounced = useDebounced(request, 300);
  const quote = useQuery({
    queryKey: ["room-quote", debounced, currency, locale, nationality],
    queryFn: ({ signal }) => api.quoteRoom(debounced, { locale, currency, signal, nationality }),
    enabled: Boolean(debounced.check_in && debounced.check_out) && errors.length === 0 && nights > 0,
    placeholderData: keepPreviousData,
  });
  const serverErrors = quote.error instanceof ApiError && quote.error.isValidation ? Object.values(quote.error.errors).flat() : [];

  const reserve = () => {
    if (!quote.data || errors.length) return;
    saveDraft({
      service_type: "room",
      product: { room_id: room.id, title: `${room.name} · ${room.building.name}`, image: room.image.url, href: `/rooms/${room.id}`, subtitle: room.building.location },
      check_in: checkIn,
      check_out: checkOut,
      adults,
      children,
      child_ages: [],
      units: [],
      addons: [],
      quote: quote.data,
      season_note: quote.data.season?.name ?? null,
      created_at: Date.now(),
    });
    router.push("/checkout");
  };

  const body = (prefix: string) => (
    <div className="flex flex-col gap-5">
      <div className="grid grid-cols-2 gap-3">
        <div className="flex flex-col gap-1">
          <label htmlFor={`${prefix}-in`} className="text-sm text-ink-700">{t("checkIn")}</label>
          <input id={`${prefix}-in`} type="date" value={checkIn} min={today ?? undefined} onChange={(e) => {
            setIn(e.target.value);
            if (checkOut <= e.target.value) setOut(toISODate(addDays(parseISODate(e.target.value), room.min_nights)));
          }} className="h-12 rounded-control border border-ink-300 bg-ink-100 px-3 text-ink-900 focus:border-primary-500 focus:outline-none" />
        </div>
        <div className="flex flex-col gap-1">
          <label htmlFor={`${prefix}-out`} className="text-sm text-ink-700">{t("checkOut")}</label>
          <input id={`${prefix}-out`} type="date" value={checkOut} min={checkIn || undefined} onChange={(e) => setOut(e.target.value)} className="h-12 rounded-control border border-ink-300 bg-ink-100 px-3 text-ink-900 focus:border-primary-500 focus:outline-none" />
        </div>
      </div>
      <p className="text-sm text-ink-700" aria-live="polite">{t("nights", { count: nights })}</p>
      <div className="flex items-center justify-between gap-3 rounded-image border border-ink-300 px-4 py-3">
        <div><p className="font-medium text-ink-900">{tb("adults")}</p><p className="text-sm text-ink-700">{t("maxAdults", { count: room.max_adults })}</p></div>
        <QuantityStepper value={adults} min={1} max={room.max_adults} onChange={setAdults} label={tb("adults")} decreaseLabel={tb("decrease", { item: tb("adults") })} increaseLabel={tb("increase", { item: tb("adults") })} />
      </div>
      <div className="flex items-center justify-between gap-3 rounded-image border border-ink-300 px-4 py-3">
        <div><p className="font-medium text-ink-900">{tb("children")}</p><p className="text-sm text-ink-700">{t("maxChildren", { count: room.max_children })}</p></div>
        <QuantityStepper value={children} min={0} max={room.max_children} onChange={setChildren} label={tb("children")} decreaseLabel={tb("decrease", { item: tb("children") })} increaseLabel={tb("increase", { item: tb("children") })} />
      </div>
      <p className="flex items-start gap-2 text-xs text-ink-700"><Baby className="size-4 shrink-0" aria-hidden />{room.child_policy}</p>
      {[...errors, ...serverErrors].length > 0 && (
        <ul className="rounded-image bg-error-100 p-3 text-sm text-error" role="alert">
          {[...errors, ...serverErrors].map((e) => (<li key={e} className="flex items-center gap-1"><AlertCircle className="size-4" aria-hidden />{e}</li>))}
        </ul>
      )}
      <PriceSummary quote={quote.data} loading={quote.isFetching} />
      {room.tax_included ? (
        <Badge tone="success" icon={BadgeIcons.taxIncluded} className="self-start">{t("taxIncluded")}</Badge>
      ) : (
        <p className="text-xs text-ink-700">{t("taxNotIncluded")}</p>
      )}
      <Button size="lg" className="w-full" onClick={reserve} disabled={!quote.data || errors.length > 0}>{tb("reserve")}</Button>
      <p className="inline-flex items-center justify-center gap-1.5 text-xs text-ink-700"><Lock className="size-3.5" aria-hidden /> {tb("secure")}</p>
    </div>
  );

  return (
    <>
      <div className="hidden rounded-card border border-ink-100 bg-white p-6 shadow-card lg:block">
        <p className="mb-5 flex items-baseline gap-2 border-b border-ink-100 pb-5">
          <Price amount={room.price_per_night} className="text-[32px] font-bold leading-9 text-ink-900" />
          <span className="text-sm text-ink-500">{t("perNight")}</span>
        </p>
        {body("rd")}
      </div>
      <div className="fixed inset-x-0 bottom-0 z-30 border-t border-ink-100 bg-white px-4 pb-[max(12px,env(safe-area-inset-bottom))] pt-3 lg:hidden">
        <div className="mx-auto flex max-w-xl items-center justify-between gap-4">
          <p>
            {quote.data ? <Price amount={quote.data.total} className="text-xl font-bold text-ink-900" /> : <Price amount={room.price_per_night} className="text-xl font-bold text-ink-900" />}{" "}
            <span className="text-xs text-ink-500">{quote.data ? t("nights", { count: nights }) : t("perNight")}</span>
          </p>
          <Button size="lg" onClick={() => setSheet(true)} aria-haspopup="dialog">{t("checkDates")}</Button>
        </div>
      </div>
      <BottomSheet open={sheet} onClose={() => setSheet(false)} title={t("title")} closeLabel={tb("close")} className="lg:hidden">
        {body("rm")}
      </BottomSheet>
    </>
  );
}
