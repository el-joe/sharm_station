"use client";

import { Combobox } from "@/components/ui/Combobox";
import { useLocationGroups } from "@/components/ui/locationGroups";
import type { DestinationSummary } from "@/lib/api/types";
import { useCurrency } from "@/lib/currency";
import { cn } from "@/lib/utils";
import { Check, ChevronDown, CircleCheck, MapPin } from "lucide-react";
import { useTranslations } from "next-intl";
import { useId, useState, type ReactNode } from "react";
import { FilterSwitchRow } from "./FilterSwitchRow";
import type { ListingKind, ListingState } from "./filters";

function Group({ title, children, defaultOpen = true }: { title: string; children: ReactNode; defaultOpen?: boolean }) {
  const [open, setOpen] = useState(defaultOpen);
  const id = useId();
  return (
    <fieldset className="border-b border-ink-100 py-4 last:border-0">
      <legend className="contents">
        <button type="button" onClick={() => setOpen((o) => !o)} aria-expanded={open} aria-controls={id} className="flex w-full items-center justify-between py-1 text-start text-lg font-semibold text-ink-900">
          {title}
          <ChevronDown className={cn("size-5 transition-transform", open && "rotate-180")} aria-hidden />
        </button>
      </legend>
      <div id={id} hidden={!open} className="mt-3">
        {children}
      </div>
    </fieldset>
  );
}

function Option({ selected, onClick, children }: { selected: boolean; onClick: () => void; children: ReactNode }) {
  return (
    <button
      type="button"
      aria-pressed={selected}
      onClick={onClick}
      className={cn(
        "flex min-h-11 w-full items-center gap-3 rounded-control px-2 text-start text-ink-900 hover:bg-ink-100",
      )}
    >
      <span className={cn("grid size-5 shrink-0 place-items-center rounded-[6px] border", selected ? "border-primary-700 bg-primary-700 text-white" : "border-ink-300 bg-white")} aria-hidden>
        {selected && <Check className="size-3.5" strokeWidth={3} />}
      </span>
      {children}
    </button>
  );
}

const PRICE_STEPS = [50, 100, 300, 600, 1000];

/** Shared filter body for the desktop FilterSidebar and the mobile FilterBottomSheet. */
export function FilterPanel({
  kind,
  state,
  onChange,
  destinations,
}: {
  kind: ListingKind;
  state: ListingState;
  onChange: (next: ListingState) => void;
  destinations: DestinationSummary[];
}) {
  const t = useTranslations("filters");
  const { format } = useCurrency();
  const set = (patch: Partial<ListingState>) => onChange({ ...state, ...patch, page: 1 });
  const toggleType = (type: "single_day" | "multi_day") =>
    set({ type: state.type.includes(type) ? state.type.filter((x) => x !== type) : [...state.type, type] });

  return (
    <div>
      <FilterSwitchRow
        icon={<CircleCheck className="size-6" aria-hidden />}
        label={t("freeCancellation")}
        helper={t("freeCancellationHelper")}
        checked={state.free_cancellation}
        onChange={(v) => set({ free_cancellation: v })}
      />
      {kind === "trips" && (
        <Group title={t("type")}>
          <Option selected={state.type.includes("single_day")} onClick={() => toggleType("single_day")}>{t("singleDay")}</Option>
          <Option selected={state.type.includes("multi_day")} onClick={() => toggleType("multi_day")}>{t("multiDay")}</Option>
        </Group>
      )}
      <Group title={t("bookingType")}>
        {(["private", "group"] as const).map((bt) => (
          <Option key={bt} selected={state.booking_type === bt} onClick={() => set({ booking_type: state.booking_type === bt ? undefined : bt })}>
            {t(bt)}
          </Option>
        ))}
      </Group>
      {kind !== "packages" && (
        <Group title={t("destination")}>
          <DestinationFilter
            kind={kind}
            label={t("destination")}
            destinations={destinations}
            value={state.destination}
            onChange={(destination) => set({ destination })}
          />
        </Group>
      )}
      {kind === "trips" && (
        <Group title={t("pricingMode")} defaultOpen={false}>
          {(["per_person", "per_unit"] as const).map((pm) => (
            <Option key={pm} selected={state.pricing_mode === pm} onClick={() => set({ pricing_mode: state.pricing_mode === pm ? undefined : pm })}>
              {t(pm)}
            </Option>
          ))}
        </Group>
      )}
      <Group title={t("price")} defaultOpen={false}>
        {PRICE_STEPS.map((p) => (
          <Option key={p} selected={state.price_max === p} onClick={() => set({ price_max: state.price_max === p ? undefined : p })}>
            {t("upTo", { price: format(p) })}
          </Option>
        ))}
      </Group>
    </div>
  );
}

/** Searchable destination picker (type-ahead, grouped, with a clear button). */
function DestinationFilter({
  kind,
  label,
  destinations,
  value,
  onChange,
}: {
  kind: ListingKind;
  label: string;
  destinations: DestinationSummary[];
  value: string | undefined;
  onChange: (slug: string | undefined) => void;
}) {
  const t = useTranslations("search");
  const groups = useLocationGroups();
  const selectedName = destinations.find((d) => d.slug === value)?.name ?? "";
  const [text, setText] = useState(selectedName);
  const [lastValue, setLastValue] = useState(value);
  // Follow outside changes (removable chips, "Clear all") without an effect.
  if (lastValue !== value) {
    setLastValue(value);
    setText(selectedName);
  }
  const destinationsOnly = (loaded: ReturnType<typeof groups.destinations>[]) => loaded.filter((g) => g.key === "destinations");

  return (
    <Combobox
      variant="field"
      label={label}
      hideLabel
      icon={<MapPin aria-hidden />}
      placeholder={t("whereTo")}
      value={text}
      onInputChange={setText}
      onSelect={(option) => onChange(option && !option.value.includes(":") ? option.value : undefined)}
      groups={[groups.destinations(destinations)]}
      loadGroups={async (query, signal) => destinationsOnly(await groups.remote(kind === "nile" ? "nile" : "trips")(query, signal))}
      emptyText={groups.labels.noMatches}
      clearLabel={groups.labels.clear}
      loadingText={groups.labels.searching}
    />
  );
}
