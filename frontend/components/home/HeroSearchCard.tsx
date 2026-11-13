"use client";

import { Combobox, type ComboboxOption } from "@/components/ui/Combobox";
import { useLocationGroups, type HotelSuggestion } from "@/components/ui/locationGroups";
import { useRouter } from "@/i18n/navigation";
import type { DestinationSummary, TransferHub } from "@/lib/api/types";
import { useToday } from "@/lib/hooks";
import { addDays, cn, parseISODate, toISODate } from "@/lib/utils";
import { BedDouble, CalendarDays, Car, Luggage, MapPin, Search, Sun, Waves } from "lucide-react";
import { useTranslations } from "next-intl";
import { useId, useState, type FormEvent, type ReactNode } from "react";
import { TravellersPicker } from "./TravellersPicker";

type Service = "trips" | "nile" | "packages" | "accommodation" | "transfers";

const TABS: { key: Service; icon: ReactNode }[] = [
  { key: "trips", icon: <Sun className="size-[18px]" aria-hidden /> },
  { key: "nile", icon: <Waves className="size-[18px]" aria-hidden /> },
  { key: "packages", icon: <Luggage className="size-[18px]" aria-hidden /> },
  { key: "accommodation", icon: <BedDouble className="size-[18px]" aria-hidden /> },
  { key: "transfers", icon: <Car className="size-[18px]" aria-hidden /> },
];

const LISTING: Record<"trips" | "nile" | "packages", string> = { trips: "/trips", nile: "/nile", packages: "/packages" };

function FieldShell({ label, htmlFor, icon, children }: { label: string; htmlFor: string; icon: ReactNode; children: ReactNode }) {
  return (
    <div className="flex min-w-0 flex-1 flex-col gap-1.5">
      <label htmlFor={htmlFor} className="text-xs font-medium uppercase tracking-[0.06em] text-ink-500">
        {label}
      </label>
      <div className="flex h-14 items-center gap-3 rounded-image border border-ink-300 bg-ink-100 px-4 focus-within:border-primary-500 focus-within:bg-white">
        <span className="grid size-8 shrink-0 place-items-center rounded-full bg-brand-100 text-brand-700">{icon}</span>
        {children}
      </div>
    </div>
  );
}

const inputCls = "h-full min-w-0 flex-1 bg-transparent text-base text-ink-900 placeholder:text-ink-500 focus:outline-none";

/**
 * Figma HeroSearchCard (147:20908). CR-5: the Trips, Nile and Packages tabs
 * have Destination + Travellers only (no date). Accommodation has dates;
 * Transfers has its own mini form.
 */
/** Closest destination slug for free text: exact name, then "starts with", then "contains". */
function resolveDestination(text: string, destinations: DestinationSummary[]): string | undefined {
  const q = text.trim().toLowerCase();
  if (!q) return undefined;
  return (
    destinations.find((d) => d.name.toLowerCase() === q) ??
    destinations.find((d) => d.name.toLowerCase().startsWith(q)) ??
    destinations.find((d) => d.name.toLowerCase().includes(q))
  )?.slug;
}

export function HeroSearchCard({ destinations, hubs, hotels = [] }: { destinations: DestinationSummary[]; hubs: TransferHub[]; hotels?: HotelSuggestion[] }) {
  const t = useTranslations("search");
  const router = useRouter();
  const id = useId();
  const [tab, setTab] = useState<Service>("trips");
  const groups = useLocationGroups();
  /** Text shown in the destination field and the option picked from the dropdown (if any). */
  const [destinationText, setDestinationText] = useState("");
  const [destinationPick, setDestinationPick] = useState<ComboboxOption | null>(null);
  const [adults, setAdults] = useState(2);
  const [children, setChildren] = useState(0);
  const todayIso = useToday();
  const shift = (days: number) => (todayIso ? toISODate(addDays(parseISODate(todayIso), days)) : "");
  const [checkInState, setCheckIn] = useState<string | null>(null);
  const [checkOutState, setCheckOut] = useState<string | null>(null);
  const [hub, setHub] = useState(hubs[0]?.code ?? "");
  const [hubText, setHubText] = useState(hubs[0]?.name ?? "");
  const [to, setTo] = useState("");
  const [toPoint, setToPoint] = useState<{ lat: number; lng: number } | null>(null);
  const [dateState, setDate] = useState<string | null>(null);
  const checkIn = checkInState ?? shift(14);
  const checkOut = checkOutState ?? shift(17);
  const date = dateState ?? shift(7);
  const tomorrow = shift(1);

  const pickedSlug = destinationPick && !destinationPick.value.includes(":") ? destinationPick.value : undefined;
  const destination = pickedSlug ?? resolveDestination(destinationText, destinations) ?? "";
  const selectedHub = hubs.find((h) => h.code === hub);

  const submit = (e: FormEvent) => {
    e.preventDefault();
    const [pickType, pickSlug] = destinationPick && destinationPick.value.includes(":") ? destinationPick.value.split(":", 2) : [];
    if (pickType === "trip") {
      router.push(`${tab === "nile" ? "/nile" : "/trips"}/${pickSlug}`);
    } else if (pickType === "package") {
      router.push(`/packages/${pickSlug}`);
    } else if (tab === "accommodation" && pickType === "hotel") {
      router.push({ pathname: `/buildings/${pickSlug}`, query: { check_in: checkIn, check_out: checkOut, adults: String(adults), children: String(children) } });
    } else if (tab === "accommodation") {
      router.push({ pathname: "/accommodation", query: { ...(destination && { destination }), check_in: checkIn, check_out: checkOut, adults: String(adults), children: String(children) } });
    } else if (tab === "transfers") {
      router.push({ pathname: "/transfers", query: { hub, to, date, ...(toPoint ? { to_lat: String(toPoint.lat), to_lng: String(toPoint.lng) } : {}) } });
    } else {
      router.push({ pathname: LISTING[tab], query: { ...(destination && { destination }), adults: String(adults), ...(children ? { children: String(children) } : {}) } });
    }
  };

  const destinationField = (
    <Combobox
      id={`${id}-dest-${tab === "accommodation" ? "stay" : "trip"}`}
      label={t("destination")}
      icon={<MapPin className="size-4" aria-hidden />}
      placeholder={t("whereTo")}
      value={destinationText}
      onInputChange={(text) => {
        setDestinationText(text);
        setDestinationPick(null);
      }}
      onSelect={setDestinationPick}
      loadGroups={groups.remote(tab === "accommodation" ? "stays" : tab === "transfers" ? "trips" : tab)}
      loadingText={groups.labels.searching}
      groups={
        tab === "accommodation"
          ? [groups.destinations(destinations, "stays"), ...(hotels.length ? [groups.hotels(hotels)] : [])]
          : [groups.destinations(destinations)]
      }
      emptyText={groups.labels.noMatches}
      clearLabel={groups.labels.clear}
    />
  );

  const searchButton = (
    <button type="submit" className="inline-flex h-14 shrink-0 items-center justify-center gap-2 rounded-image bg-brand-500 px-7 font-semibold text-brand-navy-900 shadow-cta transition-colors hover:bg-[#f39448] md:w-[170px]">
      <Search className="size-5" aria-hidden />
      {tab === "transfers" ? t("getPrice") : t("search")}
    </button>
  );

  return (
    <div className="w-full rounded-panel bg-white px-4 pb-5 pt-2 text-start shadow-raised md:px-6 md:pb-6 md:pt-4">
      <div role="tablist" aria-label={t("services")} className="no-scrollbar flex gap-1 overflow-x-auto border-b border-ink-100 md:gap-2">
        {TABS.map(({ key, icon }) => {
          const active = key === tab;
          return (
            <button
              key={key}
              role="tab"
              type="button"
              id={`${id}-tab-${key}`}
              aria-selected={active}
              aria-controls={`${id}-panel`}
              aria-label={t(`tabs.${key}`)}
              onClick={() => setTab(key)}
              className={cn(
                "-mb-px flex shrink-0 items-center gap-2 border-b-[3px] px-3 py-3.5 font-semibold transition-colors md:px-4",
                active ? "border-brand-500 text-ink-900 [&_svg]:text-brand-700" : "border-transparent text-ink-500 hover:text-ink-900",
              )}
            >
              {icon}
              <span className={cn(!active && "max-md:sr-only")}>{t(`tabs.${key}`)}</span>
            </button>
          );
        })}
      </div>

      <form id={`${id}-panel`} role="tabpanel" aria-labelledby={`${id}-tab-${tab}`} onSubmit={submit} className="mt-5 flex flex-col gap-4">
        {(tab === "trips" || tab === "nile" || tab === "packages") && (
          <>
            {destinationField}
            <div className="flex flex-col gap-4 md:flex-row md:items-end">
              <div className="flex-1">
                <TravellersPicker adults={adults} childCount={children} label={t("travellers")} onChange={(a, c) => { setAdults(a); setChildren(c); }} />
              </div>
              {searchButton}
            </div>
          </>
        )}

        {tab === "accommodation" && (
          <>
            {destinationField}
            <div className="grid gap-4 md:grid-cols-2">
              <FieldShell label={t("checkIn")} htmlFor={`${id}-in`} icon={<CalendarDays className="size-4" aria-hidden />}>
                <input id={`${id}-in`} type="date" value={checkIn} min={tomorrow} onChange={(e) => { setCheckIn(e.target.value); if (e.target.value >= checkOut) setCheckOut(toISODate(addDays(parseISODate(e.target.value), 1))); }} className={inputCls} required />
              </FieldShell>
              <FieldShell label={t("checkOut")} htmlFor={`${id}-out`} icon={<CalendarDays className="size-4" aria-hidden />}>
                <input id={`${id}-out`} type="date" value={checkOut} min={checkIn} onChange={(e) => setCheckOut(e.target.value)} className={inputCls} required />
              </FieldShell>
            </div>
            <div className="flex flex-col gap-4 md:flex-row md:items-end">
              <div className="flex-1">
                <TravellersPicker adults={adults} childCount={children} label={t("guests")} onChange={(a, c) => { setAdults(a); setChildren(c); }} />
              </div>
              {searchButton}
            </div>
          </>
        )}

        {tab === "transfers" && (
          <>
            <div className="grid gap-4 md:grid-cols-2">
              <Combobox
                id={`${id}-hub`}
                label={t("from")}
                icon={<MapPin className="size-4" aria-hidden />}
                placeholder={groups.labels.fromPlaceholder}
                value={hubText}
                onInputChange={setHubText}
                onSelect={(option) => {
                  if (option) {
                    setHub(option.value);
                    setTo("");
                    setToPoint(null);
                  }
                }}
                groups={[groups.hubs(hubs)]}
                loadGroups={selectedHub?.id ? groups.remote("transfer_from") : undefined}
                loadingText={groups.labels.searching}
                emptyText={groups.labels.noMatches}
                clearLabel={groups.labels.clear}
              />
              <Combobox
                id={`${id}-to`}
                label={t("to")}
                icon={<MapPin className="size-4" aria-hidden />}
                placeholder={t("toPlaceholder")}
                value={to}
                onInputChange={(text) => {
                  setTo(text);
                  setToPoint(null);
                }}
                onSelect={(option) => {
                  const lat = option?.data?.lat;
                  const lng = option?.data?.lng;
                  setToPoint(typeof lat === "number" && typeof lng === "number" ? { lat, lng } : null);
                }}
                groups={[groups.places(selectedHub)]}
                loadGroups={selectedHub?.id ? groups.remote("transfer_to", selectedHub.id) : undefined}
                loadingText={groups.labels.searching}
                allowFreeText
                freeTextLabel={groups.labels.useTyped}
                emptyText={groups.labels.noMatches}
                clearLabel={groups.labels.clear}
              />
            </div>
            <div className="flex flex-col gap-4 md:flex-row md:items-end">
              <FieldShell label={t("date")} htmlFor={`${id}-date`} icon={<CalendarDays className="size-4" aria-hidden />}>
                <input id={`${id}-date`} type="date" value={date} min={tomorrow} onChange={(e) => setDate(e.target.value)} className={inputCls} />
              </FieldShell>
              {searchButton}
            </div>
          </>
        )}
      </form>
    </div>
  );
}
