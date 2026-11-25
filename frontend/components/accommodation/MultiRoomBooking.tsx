"use client";

import { PriceSummary } from "@/components/booking/PriceSummary";
import { Button } from "@/components/ui/Button";
import { Field, Input } from "@/components/ui/Field";
import { QuantityStepper } from "@/components/ui/QuantityStepper";
import { SelectMenu } from "@/components/ui/SelectMenu";
import { useRouter } from "@/i18n/navigation";
import { api } from "@/lib/api/client";
import type { Locale, RoomQuoteRequest, RoomSummary } from "@/lib/api/types";
import { saveDraft } from "@/lib/checkout";
import { useCurrency } from "@/lib/currency";
import { useDebounced, useLocationSearch, useToday } from "@/lib/hooks";
import { useNationalityCode } from "@/lib/nationality";
import { addDays, nightsBetween, parseISODate, toISODate } from "@/lib/utils";
import { keepPreviousData, useQuery } from "@tanstack/react-query";
import { Lock } from "lucide-react";
import { useLocale, useTranslations } from "next-intl";
import { useId, useState } from "react";
import { MultiRoomPlanner } from "./MultiRoomPlanner";
import { splitRoomErrors, useMultiRoomStay, useRoomCombinations } from "./useMultiRoomStay";

const iso = (v: string | null) => (v && /^\d{4}-\d{2}-\d{2}$/.test(v) ? v : null);

/**
 * Multi-room accommodation booking for one building (B2.1, B2.2, SRS 6.4 / 7.2): stay + party, "best combinations"
 * from GET /rooms/combinations (one click applies the rooms and the allocation), the RoomAllocationBoard (shared with
 * the partner wizard: useMultiRoomStay + MultiRoomPlanner), a live
 * POST /rooms/quote with rooms[] and Reserve -> /checkout (POST /bookings with rooms[]).
 */
export function MultiRoomBooking({ building, rooms, image }: { building: { slug: string; name: string; location: string }; rooms: RoomSummary[]; image: string }) {
  const t = useTranslations("multiRoom");
  const tb = useTranslations("booking");
  const tr = useTranslations("roomBooking");
  const locale = useLocale() as Locale;
  const { currency } = useCurrency();
  const nationality = useNationalityCode();
  const router = useRouter();
  const today = useToday();
  const id = useId();
  const search = new URLSearchParams(useLocationSearch());

  const [inState, setIn] = useState<string | null>(null);
  const [outState, setOut] = useState<string | null>(null);
  const [adultsState, setAdults] = useState<number | null>(null);
  const [agesState, setAges] = useState<(number | null)[] | null>(null);

  const checkIn = inState ?? iso(search.get("check_in")) ?? (today ? toISODate(addDays(parseISODate(today), 14)) : "");
  const checkOut = outState ?? iso(search.get("check_out")) ?? (checkIn ? toISODate(addDays(parseISODate(checkIn), 2)) : "");
  const adults = adultsState ?? Math.min(20, Math.max(1, Number(search.get("adults")) || 2));
  const childAges = agesState ?? Array.from({ length: Math.min(10, Math.max(0, Number(search.get("children")) || 0)) }, () => null);
  const nights = checkIn && checkOut ? nightsBetween(checkIn, checkOut) : 0;
  const datesOk = Boolean(checkIn && checkOut && checkOut > checkIn && (!today || checkIn >= today));
  const agesOk = childAges.every((a) => a != null);
  const knownAges = childAges.filter((a): a is number => a != null);

  const stay = useMultiRoomStay({ rooms, adults, childAges });
  const { boardRooms, allocation, clientErrors } = stay;
  const combos = useRoomCombinations({ building: building.slug, checkIn, checkOut, adults, childAges: knownAges, nationality, enabled: datesOk && agesOk });

  /* ------------------------------------------------------------ quote */
  const request: RoomQuoteRequest = { check_in: checkIn, check_out: checkOut, adults, children: childAges.length, child_ages: knownAges, rooms: allocation };
  const debounced = useDebounced(request, 350);
  const quote = useQuery({
    queryKey: ["multi-room-quote", debounced, currency, locale, nationality],
    queryFn: ({ signal }) => api.quoteRoom(debounced, { locale, currency, signal, nationality }),
    enabled: datesOk && agesOk && (debounced.rooms?.length ?? 0) > 0,
    placeholderData: keepPreviousData,
    retry: false,
  });
  const { roomErrors: serverRoomErrors, errors: serverErrors } = splitRoomErrors(quote.error);
  const current = JSON.stringify(debounced) === JSON.stringify(request) && !quote.isFetching && !quote.isPlaceholderData;
  const quoteOk = Boolean(quote.data) && !quote.isError && current;
  const canReserve = quoteOk && clientErrors.length === 0 && boardRooms.length > 0;

  /* ------------------------------------------------------------ actions */
  const setParty = (nextAdults: number, nextAges: (number | null)[]) => {
    setAdults(nextAdults);
    setAges(nextAges);
    stay.clearApplied();
  };

  const reserve = () => {
    if (!canReserve || !quote.data) return;
    saveDraft({
      service_type: "room",
      product: {
        room_id: boardRooms[0] ? Number(boardRooms[0].key) : undefined,
        title: t("draftTitle", { building: building.name, count: boardRooms.length }),
        image,
        href: `/buildings/${building.slug}`,
        subtitle: building.location,
      },
      check_in: checkIn,
      check_out: checkOut,
      adults,
      children: childAges.length,
      child_ages: knownAges,
      units: [],
      addons: [],
      rooms: allocation.map((a, i) => ({ ...a, name: boardRooms[i].name, number: boardRooms[i].number })),
      quote: quote.data,
      season_note: quote.data.season?.name ?? null,
      created_at: Date.now(),
    });
    router.push("/checkout");
  };

  return (
    <section id="book-rooms" aria-labelledby="book-rooms-title" className="scroll-mt-28">
      <h2 id="book-rooms-title" className="text-h3 text-ink-900">{t("title")}</h2>
      <p className="mt-1 max-w-3xl text-ink-700">{t("subtitle", { building: building.name })}</p>

      <div className="mt-6 grid gap-6 lg:grid-cols-[minmax(0,1fr)_380px]">
        <div className="flex min-w-0 flex-col gap-6">
          {/* Stay and party */}
          <div className="grid gap-4 rounded-card border border-ink-100 bg-white p-5 shadow-card md:grid-cols-2 md:p-6">
            <fieldset className="flex flex-col gap-3">
              <legend className="mb-2 font-semibold text-ink-900">{t("stay")}</legend>
              <div className="grid grid-cols-2 gap-3">
                <Field label={tr("checkIn")} htmlFor={`${id}-in`}>
                  <Input
                    id={`${id}-in`}
                    type="date"
                    value={checkIn}
                    min={today ?? undefined}
                    onChange={(e) => {
                      const v = e.target.value;
                      setIn(v);
                      if (v && checkOut <= v) setOut(toISODate(addDays(parseISODate(v), 1)));
                    }}
                  />
                </Field>
                <Field label={tr("checkOut")} htmlFor={`${id}-out`}>
                  <Input id={`${id}-out`} type="date" value={checkOut} min={checkIn || undefined} onChange={(e) => setOut(e.target.value)} />
                </Field>
              </div>
            </fieldset>
            <fieldset className="flex flex-col gap-2">
              <legend className="mb-2 font-semibold text-ink-900">{t("party")}</legend>
              <PartyRow label={t("adults")} hint={t("adultsHint")} value={adults} min={1} max={20} onChange={(n) => setParty(n, childAges)} />
              <PartyRow label={t("children")} hint={t("childrenHint")} value={childAges.length} min={0} max={10} onChange={(n) => setParty(adults, Array.from({ length: n }, (_, i) => childAges[i] ?? null))} />
            </fieldset>
            {childAges.length > 0 && (
              <div className="grid grid-cols-2 gap-3 sm:grid-cols-4 md:col-span-2">
                {childAges.map((age, i) => (
                  <div key={i} className="flex flex-col gap-1">
                    <label htmlFor={`${id}-age-${i}`} className="text-sm text-ink-700">{t("childAge", { n: i + 1 })}</label>
                    <SelectMenu
                      id={`${id}-age-${i}`}
                      value={age ?? ""}
                      searchable={false}
                      onChange={(e) => setParty(adults, childAges.map((a, j) => (j === i ? (e.target.value === "" ? null : Number(e.target.value)) : a)))}
                    >
                      <option value="">{t("selectAge")}</option>
                      {Array.from({ length: 18 }, (_, a) => (
                        <option key={a} value={a}>{tb("years", { count: a })}</option>
                      ))}
                    </SelectMenu>
                  </div>
                ))}
                {!agesOk && <p className="col-span-full text-sm text-warning">{t("agesRequired")}</p>}
              </div>
            )}
          </div>

          <MultiRoomPlanner stay={stay} combos={combos} agesOk={agesOk} nights={nights} serverRoomErrors={serverRoomErrors} serverErrors={serverErrors} idPrefix={id} />
        </div>

        <aside className="lg:sticky lg:top-28 lg:self-start" aria-label={t("title")}>
          <div className="flex flex-col gap-5 rounded-card border border-ink-100 bg-white p-5 shadow-card md:p-6">
            {boardRooms.length > 0 && !quote.isError ? (
              <PriceSummary quote={quote.data} loading={quote.isFetching} />
            ) : boardRooms.length > 0 ? null : (
              <p className="text-sm text-ink-700">{t("noRooms")}</p>
            )}
            {boardRooms.length > 0 && !canReserve && !quote.isFetching && <p className="text-sm text-ink-700">{t("fixErrors")}</p>}
            <Button size="lg" className="w-full" onClick={reserve} disabled={!canReserve} data-testid="multi-room-reserve">
              {t("reserve", { count: Math.max(1, boardRooms.length) })}
            </Button>
            <p className="inline-flex items-center justify-center gap-1.5 text-xs text-ink-700"><Lock className="size-3.5" aria-hidden /> {tb("secure")}</p>
          </div>
        </aside>
      </div>
    </section>
  );
}

function PartyRow({ label, hint, value, min, max, onChange }: { label: string; hint: string; value: number; min: number; max: number; onChange: (n: number) => void }) {
  const tb = useTranslations("booking");
  return (
    <div className="flex items-center justify-between gap-3">
      <div>
        <p className="font-medium text-ink-900">{label}</p>
        <p className="text-xs text-ink-500">{hint}</p>
      </div>
      <QuantityStepper size="sm" value={value} min={min} max={max} onChange={onChange} label={label} decreaseLabel={tb("decrease", { item: label })} increaseLabel={tb("increase", { item: label })} />
    </div>
  );
}
