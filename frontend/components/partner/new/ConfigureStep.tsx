"use client";

import { UnitOptionCard } from "@/components/booking/UnitOptionCard";
import { NationalityCombobox } from "@/components/nationality/NationalityCombobox";
import { AppImage } from "@/components/ui/AppImage";
import { Button } from "@/components/ui/Button";
import { Field, Input, Select } from "@/components/ui/Field";
import { QuantityStepper } from "@/components/ui/QuantityStepper";
import { api, ApiError } from "@/lib/api/client";
import type { BookingType, Locale, PartnerCatalogueItem, PartnerQuote, PartnerQuoteRequest, TripDetail } from "@/lib/api/types";
import { useAuth } from "@/lib/auth";
import { useCurrency } from "@/lib/currency";
import { useDebounced, useToday } from "@/lib/hooks";
import { useNationality } from "@/lib/nationality";
import { firstAvailableDate, seasonForDate } from "@/lib/pricing";
import { addDays, cn, parseISODate, round2, toISODate } from "@/lib/utils";
import { keepPreviousData, useQuery } from "@tanstack/react-query";
import { Check, Loader2 } from "lucide-react";
import { useFormatter, useLocale, useTranslations } from "next-intl";
import { useState, type ReactNode } from "react";
import { PartnerPriceSummary } from "../shared";

export interface Configured {
  request: Omit<PartnerQuoteRequest, "nationality_code">;
  quote: PartnerQuote;
  adults: number;
  children: number;
  childAges: number[];
  subtitle: string;
}

function Card({ title, text, children }: { title: string; text?: string; children: ReactNode }) {
  const id = title.replace(/\W+/g, "-").toLowerCase();
  return (
    <section className="rounded-card border border-ink-100 bg-white p-5 shadow-card" aria-labelledby={`cfg-${id}`}>
      <h2 id={`cfg-${id}`} className="text-h4 text-ink-900">
        {title}
      </h2>
      {text && <p className="mt-1 text-sm text-ink-500">{text}</p>}
      <div className="mt-4">{children}</div>
    </section>
  );
}

/** Step 2 (Figma 257:35830): date, booking type, travellers or units, add-ons, live POST /partner/quotes summary. */
export function ConfigureStep({
  item,
  nationality,
  onNationality,
  serviceLabel,
  initial,
  onBack,
  onContinue,
}: {
  item: PartnerCatalogueItem;
  nationality: string;
  onNationality: (code: string) => void;
  serviceLabel: string;
  initial?: Configured | null;
  onBack: () => void;
  onContinue: (c: Configured) => void;
}) {
  const t = useTranslations("partnerPanel.newBooking.configure");
  const locale = useLocale() as Locale;
  const { data } = useNationality();
  const group = data?.countries.find((c) => c.code === nationality)?.group;
  const isTrip = item.product_type === "trip" || item.product_type === "package";

  // Unit, tier and add-on prices shown while configuring are the client's nationality group prices.
  const detail = useQuery({
    queryKey: ["partner", "product", item.product_type, item.slug, locale, nationality],
    queryFn: ({ signal }) =>
      item.product_type === "package" ? api.packageDetail(item.slug!, { locale, nationality, signal }) : api.trip(item.slug!, { locale, nationality, signal }),
    enabled: isTrip && Boolean(item.slug),
    staleTime: 5 * 60_000,
    // Same product while configuring: keep the form mounted while the new group's prices load.
    placeholderData: keepPreviousData,
  });

  const head = (
    <>
      <section className="flex items-center gap-4 rounded-card border border-ink-100 bg-white p-4 shadow-card">
        <span className="relative h-16 w-24 shrink-0 overflow-hidden rounded-image bg-partner-100">
          {item.cover_image && <AppImage src={item.cover_image} alt="" fill sizes="96px" className="object-cover" />}
        </span>
        <div className="min-w-0 flex-1">
          <p className="font-semibold text-ink-900">{item.title}</p>
          <p className="text-sm text-ink-700">{[serviceLabel, item.subtitle].filter(Boolean).join(" · ")}</p>
        </div>
        <Button variant="ghost" onClick={onBack}>
          {t("change")}
        </Button>
      </section>

      <Card title={t("nationalityTitle")} text={t("nationalityText")}>
        <NationalityCombobox
          id="cfg-nationality"
          label={t("nationalityTitle")}
          hideLabel
          countries={data?.countries ?? []}
          value={nationality}
          onChange={(c) => c && onNationality(c)}
          suggestions={[nationality]}
          describedBy="cfg-nationality-hint"
          required
        />
        <p id="cfg-nationality-hint" className="mt-1 text-xs text-ink-500">
          {group ? t("groupHint", { group: group.name }) : ""}
        </p>
      </Card>
    </>
  );
  const common = { item, nationality, serviceLabel, initial, onContinue, head };

  return isTrip ? (
    detail.isPending ? (
      <Layout head={head} summaryTitle={item.title} summarySubtitle="" live={{ quote: null, loading: true, errors: [] }} serviceLabel={serviceLabel} onContinue={() => {}}>
        <div className="h-80 animate-pulse rounded-card bg-ink-100" aria-busy="true" />
      </Layout>
    ) : detail.data ? (
      <TripConfig key={item.slug} trip={detail.data} {...common} />
    ) : (
      <Layout head={head} summaryTitle={item.title} summarySubtitle="" live={{ quote: null, loading: false, errors: [] }} serviceLabel={serviceLabel} onContinue={() => {}}>
        <p className="rounded-card bg-warning-100 p-4 text-ink-900" role="alert">
          {t("loadError")}
        </p>
      </Layout>
    )
  ) : item.product_type === "room" ? (
    <RoomConfig {...common} />
  ) : (
    <TransferConfig {...common} />
  );
}

/* ----------------------------------------------------------------- live quote */

function useLiveQuote(request: Omit<PartnerQuoteRequest, "nationality_code"> | null, nationality: string) {
  const locale = useLocale() as Locale;
  const { token } = useAuth();
  const { currency } = useCurrency();
  const debounced = useDebounced(request, 350);
  const query = useQuery({
    queryKey: ["partner", "quote", debounced, nationality, token, locale, currency],
    queryFn: ({ signal }) => api.partnerQuote({ ...debounced!, nationality_code: nationality }, { locale, currency, token: token!, signal }),
    enabled: Boolean(debounced && token && nationality),
    placeholderData: keepPreviousData,
    retry: false,
  });
  const errors = query.error instanceof ApiError ? (query.error.isValidation ? [...new Set(Object.values(query.error.errors).flat())] : [query.error.message]) : [];
  const stale = JSON.stringify(debounced) !== JSON.stringify(request);
  return { quote: query.isError ? null : query.data, loading: query.isFetching || stale, errors };
}

function Layout({
  head,
  children,
  summaryTitle,
  summarySubtitle,
  live,
  serviceLabel,
  onContinue,
}: {
  head: ReactNode;
  children: ReactNode;
  summaryTitle: string;
  summarySubtitle: string;
  live: ReturnType<typeof useLiveQuote>;
  serviceLabel: string;
  onContinue: () => void;
}) {
  const t = useTranslations("partnerPanel.newBooking.configure");
  const ready = Boolean(live.quote?.valid) && !live.loading && live.errors.length === 0;
  return (
    <div className="grid gap-6 xl:grid-cols-[minmax(0,1fr)_340px]">
      <div className="flex min-w-0 flex-col gap-5">
        {head}
        {children}
      </div>
      <div>
        <div className="xl:sticky xl:top-28">
          <PartnerPriceSummary
            title={summaryTitle}
            subtitle={summarySubtitle}
            quote={live.quote}
            loading={live.loading}
            errors={live.errors}
            serviceLabel={serviceLabel}
            footer={
              <Button variant="partner" size="lg" className="w-full" disabled={!ready} onClick={onContinue}>
                {live.loading && <Loader2 className="size-5 animate-spin" aria-hidden />}
                {t("continue")}
              </Button>
            }
          />
        </div>
      </div>
    </div>
  );
}

function ChildAges({ ages, onChange, idPrefix }: { ages: (number | null)[]; onChange: (ages: (number | null)[]) => void; idPrefix: string }) {
  const t = useTranslations("partnerPanel.newBooking.configure");
  if (!ages.length) return null;
  return (
    <div className="grid grid-cols-2 gap-3 sm:grid-cols-4">
      {ages.map((age, i) => (
        <Field key={i} label={t("childAge", { n: i + 1 })} htmlFor={`${idPrefix}-age-${i}`}>
          <Select id={`${idPrefix}-age-${i}`} value={age == null ? "" : String(age)} onChange={(e) => onChange(ages.map((a, j) => (j === i ? (e.target.value === "" ? null : Number(e.target.value)) : a)))}>
            <option value="">{t("chooseAge")}</option>
            {Array.from({ length: 18 }, (_, a) => (
              <option key={a} value={a}>
                {t("years", { count: a })}
              </option>
            ))}
          </Select>
        </Field>
      ))}
    </div>
  );
}

function Counter({ label, hint, value, min, max, onChange }: { label: string; hint?: string; value: number; min: number; max: number; onChange: (n: number) => void }) {
  const t = useTranslations("booking");
  return (
    <div className="flex items-center justify-between gap-4 py-2">
      <div>
        <p className="font-semibold text-ink-900">{label}</p>
        {hint && <p className="text-xs text-ink-500">{hint}</p>}
      </div>
      <QuantityStepper value={value} min={min} max={max} onChange={onChange} size="sm" label={label} decreaseLabel={t("decrease", { item: label })} increaseLabel={t("increase", { item: label })} />
    </div>
  );
}

/* ----------------------------------------------------------------- trips and packages */

function TripConfig({
  trip,
  item,
  nationality,
  serviceLabel,
  initial,
  onContinue,
  head,
}: {
  trip: TripDetail;
  item: PartnerCatalogueItem;
  nationality: string;
  serviceLabel: string;
  initial?: Configured | null;
  onContinue: (c: Configured) => void;
  head: ReactNode;
}) {
  const t = useTranslations("partnerPanel.newBooking.configure");
  const tb = useTranslations("booking");
  const format = useFormatter();
  const { format: money } = useCurrency();
  const today = useToday();
  const init = initial?.request;
  const [bookingType, setBookingType] = useState<BookingType>(init?.booking_type ?? trip.booking_types[0] ?? "group");
  const [dateState, setDate] = useState<string | null>(init?.travel_date ?? null);
  const [adults, setAdults] = useState(initial?.adults ?? 2);
  const [childAges, setChildAges] = useState<(number | null)[]>(initial?.childAges ?? []);
  const [units, setUnits] = useState<Record<number, number>>(() => Object.fromEntries((init?.units ?? []).map((u) => [u.unit_option_id, u.quantity])));
  const [addons, setAddons] = useState<number[]>(() => init?.addons?.map((a) => a.addon_id) ?? trip.addons.filter((a) => a.required).map((a) => a.id));
  const perUnit = trip.pricing_mode === "per_unit";

  const travelDate = dateState ?? (today ? firstAvailableDate(trip, bookingType, parseISODate(today)) : "");
  const allAges = childAges.every((a) => a != null);
  const unitList = Object.entries(units)
    .filter(([, q]) => q > 0)
    .map(([id, quantity]) => ({ unit_option_id: Number(id), quantity }));
  const visibleAddons = trip.addons.filter((a) => !a.booking_types.length || a.booking_types.includes(bookingType));
  const request: Omit<PartnerQuoteRequest, "nationality_code"> | null =
    travelDate && allAges && (!perUnit || unitList.length)
      ? {
          product_type: item.product_type,
          slug: item.slug ?? trip.slug,
          booking_type: bookingType,
          travel_date: travelDate,
          adults,
          children: childAges.length,
          child_ages: childAges as number[],
          ...(perUnit && item.product_type === "trip" ? { units: unitList } : {}),
          addons: addons.filter((id) => visibleAddons.some((a) => a.id === id)).map((id) => ({ addon_id: id, quantity: 1 })),
        }
      : null;
  const live = useLiveQuote(request, nationality);
  const pct = item.commission.percent;
  const partnerOf = (amount: number) => round2(amount * (1 - pct / 100));

  // Unit prices follow the season of the chosen travel date (the same rule the quote uses).
  const unitSeason = seasonForDate(trip.price_list, travelDate);
  const dateLabel = travelDate ? format.dateTime(parseISODate(travelDate), { day: "numeric", month: "short", year: "numeric" }) : "";
  const subtitle = [dateLabel, tb(bookingType), t("party", { adults, children: childAges.length })].filter(Boolean).join(" · ");

  return (
    <Layout
      head={head}
      summaryTitle={item.title}
      summarySubtitle={subtitle}
      live={live}
      serviceLabel={serviceLabel}
      onContinue={() => live.quote && request && onContinue({ request, quote: live.quote, adults, children: childAges.length, childAges: childAges as number[], subtitle })}
    >
      {trip.booking_types.length > 0 && (
        <Card title={t("bookingType")}>
          <div role="radiogroup" aria-label={t("bookingType")} className="grid gap-3 sm:grid-cols-2">
            {trip.booking_types.map((bt) => {
              const active = bt === bookingType;
              const from = bt === "private" && trip.private_tiers.length ? Math.min(...trip.private_tiers.map((p) => p.price_per_person)) : trip.price_from;
              return (
                <button
                  key={bt}
                  type="button"
                  role="radio"
                  aria-checked={active}
                  onClick={() => {
                    setBookingType(bt);
                    setDate(null);
                  }}
                  className={cn("flex items-start gap-3 rounded-image border p-4 text-start transition-colors", active ? "border-2 border-partner-600 bg-partner-100" : "border-ink-300 bg-white hover:bg-sand-50")}
                >
                  <span className={cn("mt-0.5 grid size-5 shrink-0 place-items-center rounded-full border-2", active ? "border-partner-600" : "border-ink-300")} aria-hidden>
                    {active && <span className="size-2.5 rounded-full bg-partner-600" />}
                  </span>
                  <span>
                    <span className="block font-semibold text-ink-900">{tb(bt)}</span>
                    <span className="block text-sm text-ink-700">{bt === "private" ? t("privateText") : t("groupText")}</span>
                    {from > 0 && (
                      <span className="mt-1 block text-xs font-semibold text-partner-600">
                        {t("fromPrices", { customer: money(from, { decimals: true }), partner: money(partnerOf(from), { decimals: true }) })}
                      </span>
                    )}
                  </span>
                </button>
              );
            })}
          </div>
        </Card>
      )}

      <Card title={t("dateTravellers")}>
        <div className="flex flex-col gap-4">
          <Field label={t("travelDate")} htmlFor="cfg-date" required>
            <Input id="cfg-date" type="date" className="max-w-60" value={travelDate} min={today ? toISODate(addDays(parseISODate(today), 1)) : undefined} onChange={(e) => setDate(e.target.value || null)} required />
          </Field>
          <div className="divide-y divide-ink-100">
            <Counter label={tb("adults")} hint={tb("adultsHint")} value={adults} min={1} max={Math.max(1, trip.max_persons || 50)} onChange={setAdults} />
            <Counter
              label={tb("children")}
              hint={tb("childrenHint")}
              value={childAges.length}
              min={0}
              max={20}
              onChange={(n) => setChildAges((prev) => Array.from({ length: n }, (_, i) => prev[i] ?? null))}
            />
          </div>
          <ChildAges ages={childAges} onChange={setChildAges} idPrefix="cfg" />
          {!allAges && <p className="text-sm text-warning">{t("agesRequired")}</p>}
        </div>
      </Card>

      {perUnit && item.product_type === "trip" && trip.unit_options.length > 0 && (
        <Card title={t("units", { unit: trip.unit_label ?? "" })} text={t("unitsText")}>
          <div className="flex flex-col gap-3">
            {trip.unit_options.map((o) => (
              <UnitOptionCard key={o.id} option={o} price={unitSeason?.unit_prices?.[String(o.id)] ?? o.price} quantity={units[o.id] ?? 0} onChange={(q) => setUnits((u) => ({ ...u, [o.id]: q }))} unitLabel={trip.unit_label ?? ""} />
            ))}
          </div>
          {!unitList.length && <p className="mt-2 text-sm text-warning">{t("unitsRequired")}</p>}
        </Card>
      )}

      {visibleAddons.length > 0 && (
        <Card title={t("addons")} text={pct > 0 ? t("addonsText", { percent: pct }) : undefined}>
          <ul className="flex flex-col gap-2">
            {visibleAddons.map((a) => {
              const checked = addons.includes(a.id);
              return (
                <li key={a.id}>
                  <label className="flex cursor-pointer items-center gap-3 rounded-control px-1 py-2 hover:bg-sand-50">
                    <input
                      type="checkbox"
                      className="peer sr-only"
                      checked={checked}
                      disabled={a.required}
                      onChange={(e) => setAddons((list) => (e.target.checked ? [...list, a.id] : list.filter((x) => x !== a.id)))}
                    />
                    <span
                      className={cn(
                        "grid size-5 shrink-0 place-items-center rounded border peer-focus-visible:outline peer-focus-visible:outline-2 peer-focus-visible:outline-partner-600",
                        checked ? "border-partner-600 bg-partner-600 text-white" : "border-ink-300 bg-white",
                      )}
                      aria-hidden
                    >
                      {checked && <Check className="size-3.5" strokeWidth={3} />}
                    </span>
                    <span className="flex-1 text-ink-900">
                      {a.name}
                      {a.required && <span className="ms-2 text-xs text-ink-500">{t("required")}</span>}
                    </span>
                    <span className="text-end text-xs text-ink-500">
                      {money(a.price, { decimals: true })} {tb(`basis.${a.pricing_basis}`)}
                      {pct > 0 && ` · ${t("partnerShort", { amount: money(partnerOf(a.price), { decimals: true }) })}`}
                    </span>
                  </label>
                </li>
              );
            })}
          </ul>
        </Card>
      )}
    </Layout>
  );
}

/* ----------------------------------------------------------------- accommodation */

function RoomConfig({
  item,
  nationality,
  serviceLabel,
  initial,
  onContinue,
  head,
}: {
  item: PartnerCatalogueItem;
  nationality: string;
  serviceLabel: string;
  initial?: Configured | null;
  onContinue: (c: Configured) => void;
  head: ReactNode;
}) {
  const t = useTranslations("partnerPanel.newBooking.configure");
  const tb = useTranslations("booking");
  const format = useFormatter();
  const today = useToday();
  const init = initial?.request;
  const [checkIn, setCheckIn] = useState<string | null>(init?.check_in ?? null);
  const [checkOut, setCheckOut] = useState<string | null>(init?.check_out ?? null);
  const [adults, setAdults] = useState(initial?.adults ?? 2);
  const [childAges, setChildAges] = useState<(number | null)[]>(initial?.childAges ?? []);
  const base = today ? parseISODate(today) : null;
  const inDate = checkIn ?? (base ? toISODate(addDays(base, 7)) : "");
  const outDate = checkOut ?? (inDate ? toISODate(addDays(parseISODate(inDate), 2)) : "");
  const allAges = childAges.every((a) => a != null);
  const request: Omit<PartnerQuoteRequest, "nationality_code"> | null =
    inDate && outDate && outDate > inDate && allAges
      ? { product_type: "room", id: item.id, check_in: inDate, check_out: outDate, adults, children: childAges.length, child_ages: childAges as number[] }
      : null;
  const live = useLiveQuote(request, nationality);
  const d = (iso: string) => (iso ? format.dateTime(parseISODate(iso), { day: "numeric", month: "short" }) : "");
  const subtitle = [inDate && outDate ? `${d(inDate)} – ${d(outDate)}` : "", t("party", { adults, children: childAges.length })].filter(Boolean).join(" · ");

  return (
    <Layout
      head={head}
      summaryTitle={item.title}
      summarySubtitle={subtitle}
      live={live}
      serviceLabel={serviceLabel}
      onContinue={() => live.quote && request && onContinue({ request, quote: live.quote, adults, children: childAges.length, childAges: childAges as number[], subtitle })}
    >
      <Card title={t("stay")} text={t("noCommissionText")}>
        <div className="flex flex-col gap-4">
          <div className="grid gap-4 sm:grid-cols-2">
            <Field label={t("checkIn")} htmlFor="cfg-in" required>
              <Input id="cfg-in" type="date" value={inDate} onChange={(e) => setCheckIn(e.target.value || null)} required />
            </Field>
            <Field label={t("checkOut")} htmlFor="cfg-out" required error={outDate && inDate && outDate <= inDate ? t("checkOutAfter") : undefined}>
              <Input id="cfg-out" type="date" value={outDate} min={inDate || undefined} onChange={(e) => setCheckOut(e.target.value || null)} required />
            </Field>
          </div>
          <div className="divide-y divide-ink-100">
            <Counter label={tb("adults")} value={adults} min={1} max={20} onChange={setAdults} />
            <Counter label={tb("children")} value={childAges.length} min={0} max={10} onChange={(n) => setChildAges((prev) => Array.from({ length: n }, (_, i) => prev[i] ?? null))} />
          </div>
          <ChildAges ages={childAges} onChange={setChildAges} idPrefix="room" />
        </div>
      </Card>
    </Layout>
  );
}

/* ----------------------------------------------------------------- transfers */

function TransferConfig({
  item,
  nationality,
  serviceLabel,
  initial,
  onContinue,
  head,
}: {
  item: PartnerCatalogueItem;
  nationality: string;
  serviceLabel: string;
  initial?: Configured | null;
  onContinue: (c: Configured) => void;
  head: ReactNode;
}) {
  const t = useTranslations("partnerPanel.newBooking.configure");
  const locale = useLocale() as Locale;
  const format = useFormatter();
  const today = useToday();
  const init = initial?.request.transfer;
  const catalog = useQuery({ queryKey: ["transfer-catalog", locale], queryFn: () => api.transferCatalog({ locale }), staleTime: 60 * 60_000 });
  const vehicles = (catalog.data?.vehicles ?? []).filter((v) => !item.vehicle_type_id || Number(v.code) === item.vehicle_type_id);
  const [date, setDate] = useState<string | null>(init?.pickup_at.slice(0, 10) ?? null);
  const [time, setTime] = useState(init?.pickup_at.slice(11, 16) ?? "10:00");
  const [passengers, setPassengers] = useState(init?.passengers ?? 2);
  const [vehicle, setVehicle] = useState<string | null>(init ? String(init.vehicle_type_id) : null);
  const [flight, setFlight] = useState(init?.flight_number ?? "");
  const pickupDate = date ?? (today ? toISODate(addDays(parseISODate(today), 7)) : "");
  const vehicleId = vehicle ?? vehicles.find((v) => (v.capacity ?? 99) >= passengers)?.code ?? vehicles[0]?.code ?? null;
  const destinationName = item.title.split("→").pop()?.trim() ?? item.title;
  const request: Omit<PartnerQuoteRequest, "nationality_code"> | null =
    pickupDate && /^\d{2}:\d{2}$/.test(time) && vehicleId && item.hub_id && item.location
      ? {
          product_type: "transfer",
          id: item.id,
          transfer: {
            hub_id: item.hub_id,
            vehicle_type_id: Number(vehicleId),
            destination: { name: destinationName, lat: item.location.lat, lng: item.location.lng },
            pickup_at: `${pickupDate} ${time}`,
            passengers,
            ...(flight.trim() ? { flight_number: flight.trim() } : {}),
          },
        }
      : null;
  const live = useLiveQuote(request, nationality);
  const vehicleName = vehicles.find((v) => v.code === vehicleId)?.name ?? "";
  const subtitle = [pickupDate ? `${format.dateTime(parseISODate(pickupDate), { day: "numeric", month: "short", year: "numeric" })} ${time}` : "", vehicleName, t("passengers", { count: passengers })]
    .filter(Boolean)
    .join(" · ");

  return (
    <Layout
      head={head}
      summaryTitle={item.title}
      summarySubtitle={subtitle}
      live={live}
      serviceLabel={serviceLabel}
      onContinue={() => live.quote && request && onContinue({ request, quote: live.quote, adults: passengers, children: 0, childAges: [], subtitle })}
    >
      <Card title={t("transferTitle")}>
        <div className="grid gap-4 sm:grid-cols-2">
          <Field label={t("pickupDate")} htmlFor="cfg-tdate" required>
            <Input id="cfg-tdate" type="date" value={pickupDate} onChange={(e) => setDate(e.target.value || null)} required />
          </Field>
          <Field label={t("pickupTime")} htmlFor="cfg-ttime" required>
            <Input id="cfg-ttime" type="time" value={time} onChange={(e) => setTime(e.target.value)} required />
          </Field>
          <Field label={t("vehicle")} htmlFor="cfg-vehicle" required>
            <Select id="cfg-vehicle" value={vehicleId ?? ""} onChange={(e) => setVehicle(e.target.value)}>
              {vehicles.map((v) => (
                <option key={v.code} value={v.code}>
                  {v.capacity ? t("vehicleCapacity", { name: v.name, count: v.capacity }) : v.name}
                </option>
              ))}
            </Select>
          </Field>
          <Field label={t("flight")} htmlFor="cfg-flight">
            <Input id="cfg-flight" value={flight} onChange={(e) => setFlight(e.target.value)} autoComplete="off" />
          </Field>
          <div className="sm:col-span-2">
            <Counter label={t("passengersLabel")} value={passengers} min={1} max={60} onChange={setPassengers} />
          </div>
        </div>
      </Card>
    </Layout>
  );
}
