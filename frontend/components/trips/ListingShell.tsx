"use client";

import { Button } from "@/components/ui/Button";
import { BottomSheet } from "@/components/ui/BottomSheet";
import { usePathname, useRouter } from "@/i18n/navigation";
import type { DestinationSummary, TripSort } from "@/lib/api/types";
import { useCurrency } from "@/lib/currency";
import { cn } from "@/lib/utils";
import { SlidersHorizontal, X } from "lucide-react";
import { useTranslations } from "next-intl";
import { useState, useTransition, type ReactNode } from "react";
import { FilterPanel } from "./FilterPanel";
import { toQuery, type ListingKind, type ListingState } from "./filters";

/**
 * Listing chrome: desktop FilterSidebar, mobile FilterBottomSheet (CR-6),
 * sort, active filter chips. Results are server-rendered and passed as children.
 */
export function ListingShell({
  kind,
  state,
  total,
  destinations,
  children,
}: {
  kind: ListingKind;
  state: ListingState;
  total: number;
  destinations: DestinationSummary[];
  children: ReactNode;
}) {
  const t = useTranslations("filters");
  const { format } = useCurrency();
  const router = useRouter();
  const pathname = usePathname();
  const [pending, startTransition] = useTransition();
  const [sheetOpen, setSheetOpen] = useState(false);
  const [draft, setDraft] = useState<ListingState>(state);

  const navigate = (next: ListingState) =>
    startTransition(() => router.push({ pathname, query: toQuery(next) }, { scroll: false }));

  const chips: { key: string; label: string; remove: Partial<ListingState> }[] = [];
  if (state.free_cancellation) chips.push({ key: "fc", label: t("freeCancellation"), remove: { free_cancellation: false } });
  state.type.forEach((ty) => chips.push({ key: ty, label: ty === "single_day" ? t("singleDay") : t("multiDay"), remove: { type: state.type.filter((x) => x !== ty) } }));
  if (state.booking_type) chips.push({ key: "bt", label: t(state.booking_type), remove: { booking_type: undefined } });
  if (state.destination) chips.push({ key: "d", label: destinations.find((d) => d.slug === state.destination)?.name ?? state.destination, remove: { destination: undefined } });
  if (state.pricing_mode) chips.push({ key: "pm", label: t(state.pricing_mode), remove: { pricing_mode: undefined } });
  if (state.price_max) chips.push({ key: "p", label: t("upTo", { price: format(state.price_max) }), remove: { price_max: undefined } });

  const empty: ListingState = { type: [], free_cancellation: false, sort: state.sort, page: 1 };

  return (
    <div className="grid gap-8 lg:grid-cols-[280px_1fr]">
      <aside className="hidden lg:block" aria-label={t("title")}>
        <div className="sticky top-28 rounded-card border border-ink-100 bg-white p-5 shadow-card">
          <div className="mb-4 flex items-center justify-between">
            <h2 className="font-display text-2xl font-semibold">{t("title")}</h2>
            {chips.length > 0 && (
              <button type="button" onClick={() => navigate(empty)} className="text-sm font-semibold text-primary-700 hover:underline">
                {t("clearAll")}
              </button>
            )}
          </div>
          <FilterPanel kind={kind} state={state} onChange={navigate} destinations={destinations} />
        </div>
      </aside>

      <div className={cn("min-w-0 transition-opacity", pending && "opacity-60")} aria-busy={pending}>
        <div className="mb-4 flex flex-wrap items-center justify-between gap-3">
          <p className="text-ink-700" role="status">
            {t("results", { count: total })}
          </p>
          <div className="flex items-center gap-2">
            <Button
              variant="outline"
              size="sm"
              className="h-11 lg:hidden"
              icon={<SlidersHorizontal className="size-4" aria-hidden />}
              onClick={() => {
                setDraft(state);
                setSheetOpen(true);
              }}
            >
              {t("title")}
              {chips.length > 0 && <span className="grid size-5 place-items-center rounded-full bg-primary-700 text-xs text-white">{chips.length}</span>}
            </Button>
            <label className="flex items-center gap-2 text-sm text-ink-700">
              <span className="max-sm:sr-only">{t("sortBy")}</span>
              <select
                value={state.sort}
                onChange={(e) => navigate({ ...state, sort: e.target.value as TripSort, page: 1 })}
                className="h-11 rounded-control border border-ink-300 bg-white px-3 font-semibold text-ink-900 focus:border-primary-500 focus:outline-none"
              >
                {(["recommended", "price_asc", "price_desc", "newest"] as const).map((s) => (
                  <option key={s} value={s}>
                    {t(`sort.${s}`)}
                  </option>
                ))}
              </select>
            </label>
          </div>
        </div>

        {chips.length > 0 && (
          <ul className="mb-6 flex flex-wrap gap-2" aria-label={t("active")}>
            {chips.map((c) => (
              <li key={c.key}>
                <button
                  type="button"
                  onClick={() => navigate({ ...state, ...c.remove, page: 1 })}
                  className="inline-flex h-9 items-center gap-1.5 rounded-pill bg-primary-100 ps-3 pe-2 text-sm font-medium text-primary-700 hover:bg-primary-100/70"
                  aria-label={t("remove", { label: c.label })}
                >
                  {c.label}
                  <X className="size-4" aria-hidden />
                </button>
              </li>
            ))}
          </ul>
        )}

        {children}
      </div>

      <BottomSheet
        open={sheetOpen}
        onClose={() => setSheetOpen(false)}
        title={t("title")}
        closeLabel={t("close")}
        className="lg:hidden"
        footer={
          <div className="flex flex-col gap-2">
            <Button
              size="lg"
              className="w-full"
              onClick={() => {
                setSheetOpen(false);
                navigate(draft);
              }}
            >
              {t("apply")}
            </Button>
            <button type="button" className="h-11 font-semibold text-primary-700" onClick={() => setDraft(empty)}>
              {t("clearAll")}
            </button>
          </div>
        }
      >
        <FilterPanel kind={kind} state={draft} onChange={setDraft} destinations={destinations} />
      </BottomSheet>
    </div>
  );
}
