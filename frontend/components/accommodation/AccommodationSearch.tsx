"use client";

import { SelectMenu } from "@/components/ui/SelectMenu";
import { FilterSwitchRow } from "@/components/trips/FilterSwitchRow";
import { BottomSheet } from "@/components/ui/BottomSheet";
import { Button } from "@/components/ui/Button";
import { Combobox } from "@/components/ui/Combobox";
import { useLocationGroups } from "@/components/ui/locationGroups";
import { Field, Input, Select } from "@/components/ui/Field";
import { usePathname, useRouter } from "@/i18n/navigation";
import type { DestinationSummary } from "@/lib/api/types";
import { useToday } from "@/lib/hooks";
import { addDays, cn, parseISODate, toISODate } from "@/lib/utils";
import { ArrowUpDown, CircleCheck, MapPin, Search, SlidersHorizontal } from "lucide-react";
import { useTranslations } from "next-intl";
import { useId, useState, useTransition, type FormEvent } from "react";

export interface AccommodationQuery {
  destination?: string;
  building?: string;
  check_in?: string;
  check_out?: string;
  adults: number;
  children: number;
  free_cancellation: boolean;
  sort: "recommended" | "price_asc" | "price_desc" | "rating";
}

export function toAccommodationQuery(q: AccommodationQuery): Record<string, string> {
  const out: Record<string, string> = {};
  if (q.destination) out.destination = q.destination;
  if (q.building) out.building = q.building;
  if (q.check_in) out.check_in = q.check_in;
  if (q.check_out) out.check_out = q.check_out;
  out.adults = String(q.adults);
  if (q.children) out.children = String(q.children);
  if (q.free_cancellation) out.free_cancellation = "1";
  if (q.sort !== "recommended") out.sort = q.sort;
  return out;
}

/** Search bar (destination, dates, guests) + filters for the rooms listing (SRS 6.3). */
export function AccommodationSearch({ query, destinations, buildings, total }: { query: AccommodationQuery; destinations: DestinationSummary[]; buildings: { slug: string; name: string }[]; total: number }) {
  const t = useTranslations("accommodation");
  const tf = useTranslations("filters");
  const router = useRouter();
  const pathname = usePathname();
  const id = useId();
  const today = useToday();
  const [pending, startTransition] = useTransition();
  const [draft, setDraft] = useState(query);
  const [sheet, setSheet] = useState(false);
  const groups = useLocationGroups();
  const [placeText, setPlaceText] = useState(
    () => buildings.find((b) => b.slug === query.building)?.name ?? destinations.find((d) => d.slug === query.destination)?.name ?? "",
  );

  const defaultIn = today ? toISODate(addDays(parseISODate(today), 14)) : "";
  const checkIn = draft.check_in ?? defaultIn;
  const checkOut = draft.check_out ?? (checkIn ? toISODate(addDays(parseISODate(checkIn), 3)) : "");

  const go = (q: AccommodationQuery) =>
    startTransition(() => {
      // Keep the List / Map choice (?view=map) and the grouping (?group=building, see AccommodationResults) when the filters change.
      const current = new URLSearchParams(window.location.search);
      const view = current.get("view");
      const group = current.get("group");
      router.push({ pathname, query: { ...toAccommodationQuery(q), ...(view === "map" ? { view } : {}), ...(group === "building" ? { group } : {}) } }, { scroll: false });
    });

  const submit = (e: FormEvent) => {
    e.preventDefault();
    go({ ...draft, check_in: checkIn || undefined, check_out: checkOut || undefined });
  };

  const filters = (q: AccommodationQuery, set: (q: AccommodationQuery) => void) => (
    <div className="flex flex-col gap-4 lg:flex-row lg:items-end lg:[&>*]:flex-1">
      <FilterSwitchRow icon={<CircleCheck className="size-6" aria-hidden />} label={tf("freeCancellation")} helper={t("freeCancellationHelper")} checked={q.free_cancellation} onChange={(v) => set({ ...q, free_cancellation: v })} />
      <Field label={t("building")} htmlFor={`${id}-b`}>
        <Select id={`${id}-b`} value={q.building ?? ""} onChange={(e) => set({ ...q, building: e.target.value || undefined })}>
          <option value="">{t("allBuildings")}</option>
          {buildings.map((b) => (
            <option key={b.slug} value={b.slug}>{b.name}</option>
          ))}
        </Select>
      </Field>
    </div>
  );

  return (
    <div className={cn("flex flex-col gap-6 transition-opacity", pending && "opacity-60")}>
      <form onSubmit={submit} className="grid gap-4 rounded-card border border-ink-100 bg-white p-4 shadow-card md:grid-cols-2 md:p-6 lg:grid-cols-[1.3fr_1fr_1fr_0.7fr_0.7fr_auto] lg:items-end">
        <Combobox
          id={`${id}-d`}
          variant="field"
          label={t("destination")}
          icon={<MapPin aria-hidden />}
          placeholder={t("anywhere")}
          value={placeText}
          onInputChange={setPlaceText}
          onSelect={(option) => {
            if (!option) setDraft({ ...draft, destination: undefined, building: undefined });
            else if (option.value.startsWith("hotel:")) setDraft({ ...draft, building: option.value.slice(6), destination: undefined });
            else setDraft({ ...draft, destination: option.value, building: undefined });
          }}
          groups={[groups.destinations(destinations, "stays"), groups.hotels(buildings.map((b) => ({ slug: b.slug, name: b.name, stars: 0, city: "" })))]}
          loadGroups={groups.remote("stays")}
          emptyText={groups.labels.noMatches}
          clearLabel={groups.labels.clear}
          loadingText={groups.labels.searching}
        />
        <Field label={t("checkIn")} htmlFor={`${id}-in`}>
          <Input id={`${id}-in`} type="date" value={checkIn} min={today ?? undefined} onChange={(e) => {
            const v = e.target.value;
            setDraft({ ...draft, check_in: v, check_out: checkOut && checkOut > v ? checkOut : toISODate(addDays(parseISODate(v), 1)) });
          }} />
        </Field>
        <Field label={t("checkOut")} htmlFor={`${id}-out`}>
          <Input id={`${id}-out`} type="date" value={checkOut} min={checkIn || undefined} onChange={(e) => setDraft({ ...draft, check_in: checkIn, check_out: e.target.value })} />
        </Field>
        <Field label={t("adults")} htmlFor={`${id}-a`}>
          <Select id={`${id}-a`} value={draft.adults} onChange={(e) => setDraft({ ...draft, adults: Number(e.target.value) })}>
            {Array.from({ length: 6 }, (_, i) => i + 1).map((n) => (<option key={n} value={n}>{n}</option>))}
          </Select>
        </Field>
        <Field label={t("children")} htmlFor={`${id}-c`}>
          <Select id={`${id}-c`} value={draft.children} onChange={(e) => setDraft({ ...draft, children: Number(e.target.value) })}>
            {Array.from({ length: 5 }, (_, i) => i).map((n) => (<option key={n} value={n}>{n}</option>))}
          </Select>
        </Field>
        <Button type="submit" size="md" className="h-12 md:col-span-2 lg:col-span-1" icon={<Search className="size-5" aria-hidden />}>
          {t("search")}
        </Button>
      </form>

      <div className="flex flex-wrap items-center justify-between gap-3">
        <p className="text-ink-700" role="status">{t("results", { count: total })}</p>
        <div className="flex items-center gap-2">
          <Button variant="outline" size="sm" className="h-11 lg:hidden" icon={<SlidersHorizontal className="size-4" aria-hidden />} onClick={() => { setDraft(query); setSheet(true); }}>
            {tf("title")}
          </Button>
          <div className="flex items-center gap-2 text-sm text-ink-700">
            <span className="max-sm:sr-only" id="rooms-sort-label">{tf("sortBy")}</span>
            <div className="w-48">
              <SelectMenu aria-labelledby="rooms-sort-label" appearance="outline" size="sm" align="end" leading={<ArrowUpDown aria-hidden />} value={query.sort} onChange={(e) => go({ ...query, sort: e.target.value as AccommodationQuery["sort"] })} className="h-11">
                {(["recommended", "price_asc", "price_desc"] as const).map((s) => (
                  <option key={s} value={s}>{tf(`sort.${s}`)}</option>
                ))}
              </SelectMenu>
            </div>
          </div>
        </div>
      </div>

      <div className="hidden lg:block">
        {filters(query, go)}
      </div>

      <BottomSheet open={sheet} onClose={() => setSheet(false)} title={tf("title")} closeLabel={tf("close")} className="lg:hidden" footer={
        <Button size="lg" className="w-full" onClick={() => { setSheet(false); go(draft); }}>{tf("apply")}</Button>
      }>
        {filters(draft, setDraft)}
      </BottomSheet>
    </div>
  );
}
