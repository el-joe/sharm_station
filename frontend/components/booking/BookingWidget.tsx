"use client";

import { Badge, BadgeIcons } from "@/components/ui/Badge";
import { BottomSheet } from "@/components/ui/BottomSheet";
import { Button } from "@/components/ui/Button";
import { QuantityStepper } from "@/components/ui/QuantityStepper";
import { SegmentedControl } from "@/components/ui/SegmentedControl";
import { useRouter } from "@/i18n/navigation";
import { api, ApiError } from "@/lib/api/client";
import type { BookingType, Locale, QuoteResult, TripDetail, TripQuoteRequest } from "@/lib/api/types";
import { saveDraft } from "@/lib/checkout";
import { Price, useCurrency } from "@/lib/currency";
import { useDebounced, useToday } from "@/lib/hooks";
import { firstAvailableDate, isDateAvailable, seasonForDate, seasonRange, validateParty } from "@/lib/pricing";
import { whatsappHref } from "@/lib/site";
import { cn, parseISODate } from "@/lib/utils";
import { keepPreviousData, useQuery } from "@tanstack/react-query";
import { AlertCircle, CheckCircle2, Info, Lock, MessageCircle, Users } from "lucide-react";
import { useFormatter, useLocale, useTranslations } from "next-intl";
import { useMemo, useState } from "react";
import { AddonOptionCard } from "./AddonOptionCard";
import { PriceSummary } from "./PriceSummary";
import { UnitOptionCard } from "./UnitOptionCard";

function useTripBooking(trip: TripDetail) {
  const today = useToday();
  const [bookingType, setBookingType] = useState<BookingType>(trip.booking_types[0] ?? "group");
  const [dateState, setDate] = useState<string | null>(null);
  const [adults, setAdults] = useState(trip.pricing_mode === "per_unit" ? 3 : 2);
  const [children, setChildrenCount] = useState(0);
  const [childAges, setChildAges] = useState<(number | null)[]>([]);
  const [units, setUnits] = useState<Record<number, number>>(() => {
    if (trip.pricing_mode !== "per_unit" || !trip.unit_options.length) return {};
    // Figma default: 1 Single + 1 Double buggy for 3 riders.
    const sorted = [...trip.unit_options].sort((a, b) => a.seats - b.seats);
    return sorted.length > 1 ? { [sorted[0].id]: 1, [sorted[1].id]: 1 } : { [sorted[0].id]: 1 };
  });
  const [addons, setAddons] = useState<number[]>(() => trip.addons.filter((a) => a.required).map((a) => a.id));

  // Date defaults to the first open day once the browser clock is known (never during prerender).
  const now = today ? parseISODate(today) : null;
  const travelDate = dateState ?? (now ? firstAvailableDate(trip, bookingType, now) : "");
  const dateOk = travelDate && now ? isDateAvailable(trip, bookingType, travelDate, now) : false;

  const setChildren = (n: number) => {
    setChildrenCount(n);
    setChildAges((prev) => Array.from({ length: n }, (_, i) => prev[i] ?? null));
  };

  const request: TripQuoteRequest = {
    booking_type: bookingType,
    travel_date: travelDate,
    adults,
    children,
    child_ages: childAges.filter((a): a is number => a != null),
    units: Object.entries(units)
      .filter(([, q]) => q > 0)
      .map(([id, quantity]) => ({ unit_option_id: Number(id), quantity })),
    addons: addons.map((id) => ({ addon_id: id, quantity: 1 })),
  };

  const validation = validateParty(trip, { ...request, child_ages: childAges.map((a) => (a == null ? NaN : a)) });

  return {
    today,
    bookingType,
    setBookingType: (bt: BookingType) => {
      setBookingType(bt);
      setDate(null);
    },
    travelDate,
    setDate,
    dateOk,
    adults,
    setAdults,
    children,
    setChildren,
    childAges,
    setChildAges,
    units,
    setUnits,
    addons,
    setAddons,
    request,
    validation,
  };
}

type BookingState = ReturnType<typeof useTripBooking>;

/**
 * Figma BookingWidget (76:16369): Private/Group toggle, travel date with season
 * note, per-person counters with max persons OR per-unit UnitOptionCards,
 * add-ons, live PriceSummary from POST /trips/{slug}/quote, Reserve CTA.
 * Desktop: sticky card. Mobile: sticky bar that opens a bottom sheet.
 */
export function BookingWidget({ trip }: { trip: TripDetail }) {
  const t = useTranslations("booking");
  const locale = useLocale() as Locale;
  const { currency } = useCurrency();
  const router = useRouter();
  const state = useTripBooking(trip);
  const [sheetOpen, setSheetOpen] = useState(false);
  const [attempted, setAttempted] = useState(false);

  const debounced = useDebounced(state.request, 350);
  // The server is the source of truth for party rules (max persons, seats…): we always ask it
  // for a quote once a valid date is chosen and show its 422 messages as they come back.
  const canQuote = Boolean(debounced.travel_date) && state.dateOk;
  const quote = useQuery({
    queryKey: ["trip-quote", trip.slug, debounced, currency, locale],
    queryFn: ({ signal }) => api.quoteTrip(trip, debounced, { locale, currency, signal }),
    enabled: canQuote,
    placeholderData: keepPreviousData,
    retry: (count, error) => !(error instanceof ApiError && error.isValidation) && count < 1,
  });

  const errors = state.validation.errors;
  const serverErrors = quote.error instanceof ApiError && quote.error.isValidation ? Object.values(quote.error.errors).flat() : [];
  const blocked = !state.dateOk || errors.length > 0 || serverErrors.length > 0 || !quote.data || quote.isError;

  const reserve = () => {
    setAttempted(true);
    if (blocked || !quote.data) return;
    saveDraft({
      service_type: "trip",
      product: {
        slug: trip.slug,
        title: trip.title,
        image: trip.image.url,
        href: trip.type === "nile" ? `/nile/${trip.slug}` : trip.type === "package" ? `/packages/${trip.slug}` : `/trips/${trip.slug}`,
        subtitle: trip.destination.name,
      },
      booking_type: state.bookingType,
      travel_date: state.travelDate,
      adults: state.adults,
      children: state.children,
      child_ages: state.request.child_ages,
      units: state.request.units.map((u) => ({ ...u, name: trip.unit_options.find((o) => o.id === u.unit_option_id)?.name ?? "" })),
      addons: state.request.addons.map((a) => ({ ...a, name: trip.addons.find((x) => x.id === a.addon_id)?.name ?? "" })),
      quote: quote.data,
      season_note: quote.data.season?.name ?? null,
      created_at: Date.now(),
    });
    router.push("/checkout");
  };

  const body = (prefix: string) => (
    <WidgetBody
      prefix={prefix}
      trip={trip}
      state={state}
      quote={quote.isError ? undefined : quote.data}
      quoteLoading={quote.isFetching}
      showErrors={attempted}
      serverErrors={serverErrors}
      onReserve={reserve}
      reserveDisabled={(blocked && attempted) || serverErrors.length > 0}
    />
  );

  return (
    <>
      <div id="book" className="hidden rounded-card border border-ink-100 bg-white p-6 shadow-card lg:block">
        <WidgetHeader trip={trip} />
        {body("d")}
      </div>

      {/* Mobile sticky bar */}
      <div className="fixed inset-x-0 bottom-0 z-30 border-t border-ink-100 bg-white px-4 pb-[max(12px,env(safe-area-inset-bottom))] pt-3 shadow-[0_-8px_24px_rgb(7_10_57/0.08)] lg:hidden">
        <div className="mx-auto flex max-w-xl items-center justify-between gap-4">
          <div>
            {quote.data ? (
              <>
                <p className="text-xs text-ink-500">{t("total")}</p>
                <Price amount={quote.data.total} className="text-xl font-bold text-ink-900" />
              </>
            ) : (
              <>
                <p className="text-xs text-ink-500">{t("from")}</p>
                <p>
                  <Price amount={trip.price_from} className="text-xl font-bold text-ink-900" />{" "}
                  <span className="text-xs text-ink-500">{trip.pricing_mode === "per_unit" ? t("perUnit", { unit: trip.unit_label ?? "" }) : t("perPerson")}</span>
                </p>
              </>
            )}
          </div>
          <Button size="lg" onClick={() => setSheetOpen(true)} aria-haspopup="dialog">
            {t("checkAvailability")}
          </Button>
        </div>
      </div>
      <BottomSheet open={sheetOpen} onClose={() => setSheetOpen(false)} title={t("title")} closeLabel={t("close")} className="lg:hidden">
        {body("m")}
      </BottomSheet>
    </>
  );
}

function WidgetHeader({ trip }: { trip: TripDetail }) {
  const t = useTranslations("booking");
  return (
    <div className="mb-6 border-b border-ink-100 pb-5">
      <p className="flex items-baseline gap-2">
        <span className="text-sm text-ink-500">{t("from")}</span>
        <Price amount={trip.price_from} className="text-[32px] font-bold leading-9 text-ink-900" />
        <span className="text-sm text-ink-500">{trip.pricing_mode === "per_unit" ? t("perUnit", { unit: trip.unit_label ?? "" }) : t("perPerson")}</span>
      </p>
      {trip.free_cancellation && (
        <Badge tone="success" icon={BadgeIcons.freeCancellation} className="mt-2">
          {t("freeCancellation")}
        </Badge>
      )}
    </div>
  );
}

function WidgetBody({
  prefix,
  trip,
  state,
  quote,
  quoteLoading,
  showErrors,
  serverErrors,
  onReserve,
  reserveDisabled,
}: {
  prefix: string;
  trip: TripDetail;
  state: BookingState;
  quote: QuoteResult | undefined;
  quoteLoading: boolean;
  showErrors: boolean;
  serverErrors: string[];
  onReserve: () => void;
  reserveDisabled: boolean;
}) {
  const t = useTranslations("booking");
  const te = useTranslations("booking.errors");
  const format = useFormatter();
  const { format: money } = useCurrency();
  const id = (s: string) => `${prefix}-${s}`;

  const season = useMemo(() => (state.travelDate ? seasonForDate(trip.price_list, state.travelDate) : null), [trip.price_list, state.travelDate]);
  const range = season && state.travelDate ? seasonRange(season, parseISODate(state.travelDate)) : null;
  const dm = (d: Date) => format.dateTime(d, { day: "numeric", month: "short" });
  const weekdayNames = (trip.availability.group?.weekdays ?? []).map((wd) =>
    // 2023-01-01 was a Sunday: build a date for each weekday index.
    format.dateTime(new Date(2023, 0, 1 + wd), { weekday: "short" }),
  );
  const persons = state.adults + state.children;
  const maxReached = trip.pricing_mode === "per_person" && persons > trip.max_persons;
  const unitLabel = trip.unit_label || t("unit");
  const visibleAddons = trip.addons.filter((a) => a.booking_types.includes(state.bookingType));
  const childAgeMissing = state.childAges.some((a) => a == null);

  const seasonForUnit = (optionId: number, base: number) => season?.unit_prices?.[String(optionId)] ?? base;

  return (
    <div className="flex flex-col gap-6">
      {trip.booking_types.length > 0 && (
        <div className="flex flex-col gap-2">
          <h3 className="text-[22px] font-semibold leading-[30px] text-ink-900">{t("bookingType")}</h3>
          <SegmentedControl
            label={t("bookingType")}
            value={state.bookingType}
            onChange={state.setBookingType}
            options={(["private", "group"] as const).map((bt) => ({ value: bt, label: t(bt), disabled: !trip.booking_types.includes(bt) }))}
          />
          <p className="text-sm text-ink-700">{state.bookingType === "private" ? t("privateHint") : t("groupHint")}</p>
        </div>
      )}

      <div className="flex flex-col gap-1.5">
        <label htmlFor={id("date")} className="text-sm text-ink-700">
          {t("travelDate")}
        </label>
        <input
            id={id("date")}
            type="date"
            value={state.travelDate}
            min={state.today ?? undefined}
            onChange={(e) => state.setDate(e.target.value)}
            aria-invalid={!state.dateOk && Boolean(state.travelDate) ? true : undefined}
            aria-describedby={`${id("date-rule")} ${id("date-season")}`}
            className="h-12 w-full rounded-control border border-ink-300 bg-ink-100 px-4 text-base text-ink-900 focus:border-primary-500 focus:bg-white focus:outline-none aria-[invalid=true]:border-error"
          />
        {state.bookingType === "private" ? (
          <p id={id("date-rule")} className="text-xs text-ink-500">
            {t("privateRule", { hours: trip.availability.private?.lead_time_hours ?? 24 })}
          </p>
        ) : (
          <p id={id("date-rule")} className="inline-flex items-center gap-1.5 self-start rounded-control bg-brand-navy-900 px-3 py-1.5 text-xs text-white">
            <Info className="size-4 shrink-0" aria-hidden />
            {weekdayNames.length >= 7 ? t("groupDaily") : t("groupRule", { days: format.list(weekdayNames, { type: "conjunction" }) })}
          </p>
        )}
        {state.travelDate && !state.dateOk && (
          <p className="flex items-center gap-1 text-sm text-error" role="alert">
            <AlertCircle className="size-4" aria-hidden /> {state.bookingType === "private" ? te("privateDate") : te("groupDate")}
          </p>
        )}
        <p id={id("date-season")} className="text-xs font-medium text-ink-900" aria-live="polite">
          {season && range
            ? t("seasonNote", { season: season.name, from: dm(range.start), to: dm(range.end) })
            : state.travelDate
              ? t("baseNote")
              : ""}
        </p>
      </div>

      <div className="flex flex-col gap-3">
        <h3 className="text-[22px] font-semibold leading-[30px] text-ink-900">{trip.pricing_mode === "per_unit" ? t("riders") : t("travellers")}</h3>
        <CounterRow
          label={t("adults")}
          hint={t("adultsHint")}
          value={state.adults}
          min={1}
          max={trip.pricing_mode === "per_person" ? trip.max_persons + 8 - state.children : 40}
          onChange={state.setAdults}
        />
        <CounterRow
          label={t("children")}
          hint={t("childrenHint")}
          value={state.children}
          min={0}
          max={trip.pricing_mode === "per_person" ? trip.max_persons + 8 - state.adults : 20}
          onChange={state.setChildren}
        />
        {state.children > 0 && (
          <div className="grid grid-cols-2 gap-3">
            {state.childAges.map((age, i) => (
              <div key={i} className="flex flex-col gap-1">
                <label htmlFor={id(`age-${i}`)} className="text-sm text-ink-700">
                  {t("childAge", { n: i + 1 })}
                </label>
                <select
                  id={id(`age-${i}`)}
                  value={age ?? ""}
                  aria-invalid={showErrors && age == null ? true : undefined}
                  onChange={(e) => state.setChildAges((prev) => prev.map((p, j) => (j === i ? (e.target.value === "" ? null : Number(e.target.value)) : p)))}
                  className="h-12 rounded-control border border-ink-300 bg-ink-100 px-3 text-ink-900 focus:border-primary-500 focus:outline-none aria-[invalid=true]:border-error"
                >
                  <option value="">{t("selectAge")}</option>
                  {Array.from({ length: 18 }, (_, a) => (
                    <option key={a} value={a}>
                      {t("years", { count: a })}
                    </option>
                  ))}
                </select>
              </div>
            ))}
          </div>
        )}
        {showErrors && childAgeMissing && (
          <p className="flex items-center gap-1 text-sm text-error" role="alert">
            <AlertCircle className="size-4" aria-hidden /> {te("childAges")}
          </p>
        )}

        {trip.pricing_mode === "per_person" ? (
          <>
            {quote?.per_person_price != null && (
              <p className="flex items-center gap-2 rounded-image bg-primary-100 px-4 py-3 text-sm text-ink-900">
                <Users className="size-5" aria-hidden />
                {t("peopleEach", { count: persons, price: money(quote.per_person_price) })}
              </p>
            )}
            <p className={cn("text-xs", maxReached ? "font-medium text-warning" : "text-ink-500")} role={maxReached ? "status" : undefined}>
              {maxReached ? t("maxReached", { max: trip.max_persons }) : t("maxPersons", { max: trip.max_persons })}
            </p>
          </>
        ) : (
          <div className="flex flex-col gap-3">
            <h3 className="mt-2 text-[22px] font-semibold leading-[30px] text-ink-900">{t("chooseUnits", { unit: unitLabel })}</h3>
            {trip.unit_options.map((o) => (
              <UnitOptionCard
                key={o.id}
                option={o}
                price={seasonForUnit(o.id, o.price)}
                quantity={state.units[o.id] ?? 0}
                unitLabel={unitLabel}
                onChange={(q) => state.setUnits((prev) => ({ ...prev, [o.id]: q }))}
              />
            ))}
            <SeatsCheck state={state} unitLabel={unitLabel} />
          </div>
        )}
      </div>

      {visibleAddons.length > 0 && (
        <div className="flex flex-col gap-3">
          <h3 className="text-[22px] font-semibold leading-[30px] text-ink-900">{t("addons")}</h3>
          {visibleAddons.map((a) => (
            <AddonOptionCard
              key={a.id}
              addon={a}
              compact
              checked={state.addons.includes(a.id)}
              onChange={(checked) => state.setAddons((prev) => (checked ? [...prev, a.id] : prev.filter((x) => x !== a.id)))}
            />
          ))}
        </div>
      )}

      <div className="border-t border-ink-100 pt-5">
        <PriceSummary quote={quote} loading={quoteLoading} pendingNote={childAgeMissing ? t("childPending") : null} />
      </div>

      {serverErrors.length > 0 && (
        <ul className="rounded-image bg-error-100 p-3 text-sm text-error" role="alert">
          {serverErrors.map((e) => (
            <li key={e}>{e}</li>
          ))}
        </ul>
      )}
      {serverErrors.length === 0 && (showErrors || state.validation.errors.some((e) => e.key === "maxPersons")) && state.validation.errors.filter((e) => e.key !== "childAges").length > 0 && (
        <ul className="rounded-image bg-error-100 p-3 text-sm text-error" role="alert">
          {state.validation.errors
            .filter((e) => e.key !== "childAges")
            .map((e) => (
              <li key={e.key}>{te(e.key, e.values)}</li>
            ))}
        </ul>
      )}

      <div className="flex flex-col items-center gap-3">
        <Button size="lg" className="w-full" onClick={onReserve} disabled={reserveDisabled}>
          {t("reserve")}
        </Button>
        <p className="inline-flex items-center gap-1.5 text-xs text-ink-700">
          <Lock className="size-3.5" aria-hidden /> {t("secure")}
        </p>
        <a href={whatsappHref(t("whatsappText", { title: trip.title }))} target="_blank" rel="noopener noreferrer" className="inline-flex items-center gap-2 text-sm font-medium text-ink-900 underline">
          <MessageCircle className="size-4" aria-hidden /> {t("questions")}
        </a>
      </div>
    </div>
  );
}

function CounterRow({ label, hint, value, min, max, onChange }: { label: string; hint: string; value: number; min: number; max: number; onChange: (v: number) => void }) {
  const t = useTranslations("booking");
  return (
    <div className="flex items-center justify-between gap-3 rounded-image border border-ink-300 px-4 py-3">
      <div>
        <p className="font-medium text-ink-900">{label}</p>
        <p className="text-sm text-ink-700">{hint}</p>
      </div>
      <QuantityStepper value={value} min={min} max={Math.max(min, max)} onChange={onChange} label={label} decreaseLabel={t("decrease", { item: label })} increaseLabel={t("increase", { item: label })} />
    </div>
  );
}

/** Per-unit seats check (CR-1): seats >= riders, every unit carries a rider, an adult per unit. */
function SeatsCheck({ state, unitLabel }: { state: BookingState; unitLabel: string }) {
  const t = useTranslations("booking");
  const te = useTranslations("booking.errors");
  const { seatsTotal, unitsTotal, persons } = state.validation;
  const unitErrors = state.validation.errors.filter((e) => ["notEnoughSeats", "emptyUnit", "adultPerUnit", "chooseUnit", "maxQuantity"].includes(e.key));
  if (unitErrors.length) {
    return (
      <p className="flex items-start gap-2 rounded-image bg-error-100 px-3 py-2 text-sm text-error" role="alert">
        <AlertCircle className="mt-0.5 size-4 shrink-0" aria-hidden /> {te(unitErrors[0].key, unitErrors[0].values)}
      </p>
    );
  }
  return (
    <p className="flex items-center gap-2 rounded-image bg-success-100 px-3 py-2 text-sm text-ink-900" role="status">
      <CheckCircle2 className="size-4 shrink-0 text-success" aria-hidden />
      {t("seatsOk", { riders: persons, seats: seatsTotal, units: unitsTotal, unit: unitLabel })}
    </p>
  );
}
