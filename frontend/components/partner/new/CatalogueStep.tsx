"use client";

import { NationalityCombobox } from "@/components/nationality/NationalityCombobox";
import { AppImage } from "@/components/ui/AppImage";
import { Button } from "@/components/ui/Button";
import { Input, Select } from "@/components/ui/Field";
import { api } from "@/lib/api/client";
import type { CatalogueService, Locale, PartnerCatalogueItem } from "@/lib/api/types";
import { useCurrency } from "@/lib/currency";
import { useDebounced } from "@/lib/hooks";
import { useNationality } from "@/lib/nationality";
import { cn } from "@/lib/utils";
import { useQuery } from "@tanstack/react-query";
import { CalendarDays, Info, MapPin, Search } from "lucide-react";
import { useLocale, useTranslations } from "next-intl";
import { PartnerData } from "../PartnerStates";
import { CommissionPill, SERVICES } from "../shared";
import { usePartnerQuery } from "../usePartnerQuery";

export interface CatalogueFilters {
  service: CatalogueService;
  q: string;
  destination: string;
  date: string;
}

/** Step 1 (Figma 257:35545): client nationality, service tabs, search / destination / date, products at partner price. */
export function CatalogueStep({
  nationality,
  onNationality,
  filters,
  onFilters,
  onSelect,
}: {
  nationality: string | null;
  onNationality: (code: string) => void;
  filters: CatalogueFilters;
  onFilters: (f: CatalogueFilters) => void;
  onSelect: (item: PartnerCatalogueItem) => void;
}) {
  const t = useTranslations("partnerPanel.newBooking.catalogue");
  const ts = useTranslations("partnerPanel.services");
  const locale = useLocale() as Locale;
  const { data } = useNationality();
  const { format } = useCurrency();
  const q = useDebounced(filters.q, 300);
  const group = data?.countries.find((c) => c.code === nationality)?.group;

  const meta = useQuery({ queryKey: ["meta", locale], queryFn: ({ signal }) => api.meta({ locale, signal }), staleTime: 60 * 60_000 });
  const query = usePartnerQuery(
    ["catalogue", filters.service, nationality, filters.destination, q, filters.date],
    (o) =>
      api.partnerCatalogue(
        { service: filters.service, nationality: nationality ?? "", destination: filters.destination || undefined, q: q.trim() || undefined, date: filters.date || undefined },
        o,
      ),
    Boolean(nationality),
  );
  const set = (patch: Partial<CatalogueFilters>) => onFilters({ ...filters, ...patch });

  return (
    <div className="flex flex-col gap-5">
      <div className="grid gap-3 rounded-card bg-partner-100 p-4 md:grid-cols-[minmax(0,320px)_minmax(0,1fr)] md:items-center">
        <NationalityCombobox
          id="nb-nationality"
          label={t("nationality")}
          hideLabel
          countries={data?.countries ?? []}
          value={nationality}
          onChange={(c) => c && onNationality(c)}
          suggestions={[nationality]}
          describedBy="nb-nationality-text"
          required
        />
        <div id="nb-nationality-text">
          <p className="flex flex-wrap items-center gap-2 font-semibold text-ink-900">
            {t("nationality")}
            {group && <span className="rounded-pill bg-partner-600 px-2.5 py-0.5 text-xs font-semibold text-white">{t("group", { group: group.name })}</span>}
          </p>
          <p className="text-xs text-ink-700">{t("nationalityText")}</p>
        </div>
      </div>

      <div role="tablist" aria-label={t("servicesLabel")} className="no-scrollbar -mx-4 flex gap-2 overflow-x-auto px-4 md:mx-0 md:flex-wrap md:px-0">
        {SERVICES.map((s) => {
          const active = s === filters.service;
          return (
            <button
              key={s}
              type="button"
              role="tab"
              aria-selected={active}
              onClick={() => set({ service: s })}
              className={cn(
                "h-10 shrink-0 rounded-pill border px-4 text-sm font-semibold whitespace-nowrap transition-colors",
                active ? "border-partner-600 bg-partner-600 text-white" : "border-ink-300 bg-white text-ink-900 hover:bg-partner-100",
              )}
            >
              {ts(s)}
            </button>
          );
        })}
      </div>

      <div className="grid gap-3 md:grid-cols-[minmax(0,1fr)_200px_180px]">
        <div className="relative">
          <label htmlFor="nb-q" className="sr-only">
            {t("search", { service: ts(filters.service).toLowerCase() })}
          </label>
          <Search className="pointer-events-none absolute start-3 top-1/2 size-5 -translate-y-1/2 text-ink-500" aria-hidden />
          <Input id="nb-q" type="search" className="bg-white ps-10" placeholder={t("search", { service: ts(filters.service).toLowerCase() })} value={filters.q} onChange={(e) => set({ q: e.target.value })} />
        </div>
        {filters.service !== "transfers" ? (
          <Select aria-label={t("destination")} appearance="outline" leading={<MapPin aria-hidden />} value={filters.destination} onChange={(e) => set({ destination: e.target.value })}>
            <option value="">{t("allDestinations")}</option>
            {(meta.data?.destinations ?? []).map((d) => (
              <option key={d.slug} value={d.slug}>
                {d.name}
              </option>
            ))}
          </Select>
        ) : (
          <span className="hidden md:block" />
        )}
        <div className="relative">
          <label htmlFor="nb-date" className="sr-only">
            {t("date")}
          </label>
          <CalendarDays className="pointer-events-none absolute start-3 top-1/2 size-5 -translate-y-1/2 text-ink-500" aria-hidden />
          <Input id="nb-date" type="date" className="bg-white ps-10" value={filters.date} onChange={(e) => set({ date: e.target.value })} />
        </div>
      </div>

      {!nationality ? (
        <p className="rounded-card bg-white p-6 text-center text-ink-700">{t("chooseNationality")}</p>
      ) : (
        <PartnerData query={query} skeleton="h-96">
          {(items) =>
            items.length ? (
              <ul className="flex flex-col gap-3" aria-live="polite" aria-busy={query.isFetching || undefined}>
                {items.map((item) => (
                  <li key={`${item.product_type}-${item.id}`} className="flex flex-col gap-3 rounded-card border border-ink-100 bg-white p-3 shadow-card sm:flex-row sm:items-center">
                    <div className="flex min-w-0 flex-1 items-center gap-3">
                      <span className="relative hidden h-20 w-28 shrink-0 overflow-hidden rounded-image bg-partner-100 sm:block">
                        {item.cover_image && <AppImage src={item.cover_image} alt="" fill sizes="112px" className="object-cover" />}
                      </span>
                      <div className="min-w-0">
                        <h3 className="font-semibold text-ink-900">{item.title}</h3>
                        <p className="text-sm text-ink-700">{[item.subtitle, item.price_basis === "from" ? t("from") : (item.season ?? t("basePrice"))].filter(Boolean).join(" · ")}</p>
                        <p className="mt-1 text-xs text-ink-500">{item.price_unit ? t(`unit.${unitKey(item.price_unit)}`) : ""}</p>
                      </div>
                    </div>
                    <div className="flex items-center justify-between gap-4 sm:justify-end">
                      <div className="text-end">
                        {item.commission.percent > 0 && (
                          <p className="text-xs text-ink-500">
                            {t("customerPrice")} <s>{format(item.customer_price, { decimals: true })}</s>
                          </p>
                        )}
                        <p className="flex flex-col items-end">
                          <span className="text-xs text-ink-500">{t("partnerPrice")}</span>{" "}
                          <span className="text-xl font-bold text-partner-600">{format(item.partner_price, { decimals: true })}</span>
                        </p>
                        <CommissionPill percent={item.commission.percent} />
                      </div>
                      <Button variant="partner" onClick={() => onSelect(item)} aria-label={t("selectAria", { title: item.title })}>
                        {t("select")}
                      </Button>
                    </div>
                  </li>
                ))}
              </ul>
            ) : (
              <p className="rounded-card bg-white p-6 text-center text-ink-700">{t("empty")}</p>
            )
          }
        </PartnerData>
      )}

      {filters.service === "accommodation" && (
        <p className="flex items-start gap-2 rounded-card border border-ink-100 bg-white p-3 text-sm text-ink-700">
          <Info className="mt-0.5 size-4 shrink-0 text-primary-700" aria-hidden /> {t("accommodationNote")}
        </p>
      )}
    </div>
  );
}

function unitKey(unit: string): "per_person" | "per_unit" | "per_night" | "per_vehicle" | "other" {
  return ["per_person", "per_unit", "per_night", "per_vehicle"].includes(unit) ? (unit as "per_person") : "other";
}
