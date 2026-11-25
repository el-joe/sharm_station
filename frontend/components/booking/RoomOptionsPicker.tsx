"use client";

import { Button } from "@/components/ui/Button";
import { QuantityStepper } from "@/components/ui/QuantityStepper";
import { partyTravellers, validateAllocation, type AllocRoom } from "@/lib/allocation";
import type { RoomOption, RoomOptionSelection } from "@/lib/api/types";
import { useCurrency } from "@/lib/currency";
import { cn } from "@/lib/utils";
import { AlertCircle, BedDouble, Check, CheckCircle2, Plus, Star, Trash2 } from "lucide-react";
import { useTranslations } from "next-intl";

/** One added room: the option and who sleeps in it (children by their index in the party's child ages). */
export interface PickedRoomOption {
  key: string;
  option_id: number;
  adults: number;
  children: number[];
}

let seq = 0;
const newKey = () => `ro-${Date.now().toString(36)}-${(seq++).toString(36)}`;

/** Clamp the picked rooms to the current party (adults are counted in room order, per stage). */
export function normalizeRoomOptions(picked: PickedRoomOption[], options: RoomOption[], adults: number, childCount: number): PickedRoomOption[] {
  const stageOf = (id: number) => options.find((o) => o.id === id)?.stage_position ?? null;
  const used = new Map<string, number>();
  const seen = new Map<string, Set<number>>();
  return picked
    .filter((p) => options.some((o) => o.id === p.option_id))
    .map((p) => {
      const stage = String(stageOf(p.option_id));
      const before = used.get(stage) ?? 0;
      const a = Math.max(0, Math.min(p.adults, adults - before));
      used.set(stage, before + a);
      const taken = seen.get(stage) ?? new Set<number>();
      const children = p.children.filter((c) => c < childCount && !taken.has(c));
      children.forEach((c) => taken.add(c));
      seen.set(stage, taken);
      return { ...p, adults: a, children };
    });
}

/** Picked rooms -> room_options[] for the quote / booking (children with a known age only). */
export function roomOptionsPayload(picked: PickedRoomOption[], options: RoomOption[], adults: number, childAges: (number | null)[]): RoomOptionSelection[] {
  return normalizeRoomOptions(picked, options, adults, childAges.length).map((p) => ({
    room_option_id: p.option_id,
    adults: p.adults,
    children_ages: p.children.map((c) => childAges[c]).filter((a): a is number => a != null),
  }));
}

/**
 * Optional rooms of a multi-day trip, Nile trip or package (B2.3): add rooms (up to each option's max quantity) and
 * say who sleeps in each. New rooms are filled with the travellers who have no room yet. Prices are the visitor's
 * (or the partner's client's) nationality group prices; the quote applies the season and returns the `room` lines.
 */
export function RoomOptionsPicker({
  options,
  adults,
  childAges,
  value,
  onChange,
  tone = "default",
  idPrefix,
}: {
  options: RoomOption[];
  adults: number;
  childAges: (number | null)[];
  value: PickedRoomOption[];
  onChange: (next: PickedRoomOption[]) => void;
  tone?: "default" | "partner";
  idPrefix: string;
}) {
  const t = useTranslations("roomOptions");
  const ta = useTranslations("allocation");
  const tb = useTranslations("booking");
  const { format: money } = useCurrency();
  if (!options.length) return null;

  const picked = normalizeRoomOptions(value, options, adults, childAges.length);
  const stages = [...new Set(options.map((o) => o.stage_position))].sort((a, b) => (a ?? 0) - (b ?? 0));
  const optionOf = (id: number) => options.find((o) => o.id === id)!;
  const accent = tone === "partner" ? "partner" : "primary";

  const update = (key: string, patch: Partial<PickedRoomOption>) => onChange(picked.map((p) => (p.key === key ? { ...p, ...patch } : p)));
  const remove = (key: string) => onChange(picked.filter((p) => p.key !== key));
  const add = (o: RoomOption) => {
    const same = picked.filter((p) => optionOf(p.option_id).stage_position === o.stage_position);
    const freeAdults = adults - same.reduce((s, p) => s + p.adults, 0);
    const takenChildren = new Set(same.flatMap((p) => p.children));
    const a = Math.max(0, Math.min(freeAdults, o.capacity.max_adults, o.capacity.max_total));
    const room = Math.max(0, o.capacity.max_total - a);
    const children = a > 0 ? childAges.map((_, i) => i).filter((i) => !takenChildren.has(i)).slice(0, Math.min(o.capacity.max_children, room)) : [];
    onChange([...picked, { key: newKey(), option_id: o.id, adults: a, children }]);
  };

  return (
    <div className="flex flex-col gap-5" data-testid="room-options">
      <p className="text-sm text-ink-700">{t("text")}</p>
      {stages.map((stage) => {
        const stageOptions = options.filter((o) => o.stage_position === stage);
        const stageRooms = picked.filter((p) => optionOf(p.option_id).stage_position === stage);
        const nights = stageOptions[0]?.nights ?? 0;
        const allocRooms: AllocRoom[] = stageRooms.map((p) => ({ key: p.key, capacity: optionOf(p.option_id).capacity }));
        const travellers = partyTravellers(adults, childAges);
        const assignment: Record<string, string | null> = {};
        let nextAdult = 0;
        for (const p of stageRooms) {
          for (let k = 0; k < p.adults; k++) assignment[`a${nextAdult++}`] = p.key;
          for (const c of p.children) assignment[`c${c}`] = p.key;
        }
        const errors = stageRooms.length ? validateAllocation(allocRooms, travellers, assignment) : [];
        const unassigned = travellers.filter((tr) => !assignment[tr.id]).length;
        const headingId = `${idPrefix}-stage-${stage ?? 0}`;
        return (
          <section key={String(stage)} aria-labelledby={headingId} className="flex flex-col gap-3">
            {stage != null && (
              <h4 id={headingId} className="font-semibold text-ink-900">{t("stageNights", { n: stage, nights })}</h4>
            )}
            <ul className="flex flex-col gap-2" aria-labelledby={stage != null ? headingId : undefined} aria-label={stage == null ? t("title") : undefined}>
              {stageOptions.map((o) => {
                const count = stageRooms.filter((p) => p.option_id === o.id).length;
                const full = count >= o.max_quantity;
                return (
                  <li key={o.id} className="flex flex-wrap items-center justify-between gap-3 rounded-image border border-ink-300 p-3">
                    <div className="min-w-0 flex-1">
                      <p className="flex flex-wrap items-center gap-x-2 font-semibold text-ink-900">
                        <BedDouble className="size-4 shrink-0 text-ink-500" aria-hidden />
                        {o.name}
                        {o.stars > 0 && (
                          <span className="inline-flex" role="img" aria-label={`${o.stars} ★`}>
                            {Array.from({ length: o.stars }, (_, i) => <Star key={i} className="size-3.5 fill-gold-500 text-gold-500" aria-hidden />)}
                          </span>
                        )}
                      </p>
                      <p className="text-xs text-ink-500">
                        {o.unit_type_label} · {t("capacity", { total: o.capacity.max_total, adults: o.capacity.max_adults, children: o.capacity.max_children })}
                      </p>
                      <p className="text-sm font-medium text-ink-900">
                        {o.price_period === "per_night" ? t("pricePerNight", { price: money(o.price) }) : t("pricePerStay", { price: money(o.price) })} · {o.pricing_mode === "per_person" ? t("perPerson") : t("perRoom")}
                      </p>
                    </div>
                    <Button variant="outline" size="sm" className="h-11" onClick={() => add(o)} disabled={full} aria-label={t("addAria", { name: o.name })} icon={<Plus className="size-4" aria-hidden />}>
                      {full ? t("maxReached") : t("add")}
                    </Button>
                  </li>
                );
              })}
            </ul>

            {stageRooms.length > 0 && (
              <ol className="flex flex-col gap-3">
                {stageRooms.map((p, i) => {
                  const o = optionOf(p.option_id);
                  const n = i + 1;
                  const otherAdults = stageRooms.filter((q) => q.key !== p.key).reduce((s, q) => s + q.adults, 0);
                  const roomErrors = errors.filter((e) => e.room === i).map((e) => ta(`errors.${e.key}`, e.values));
                  return (
                    <li key={p.key} className={cn("flex flex-col gap-3 rounded-image border p-3", roomErrors.length ? "border-error" : "border-ink-100 bg-sand-50")} aria-label={t("roomN", { n, name: o.name })} data-testid="picked-room">
                      <div className="flex items-center justify-between gap-2">
                        <p className="font-semibold text-ink-900">{t("roomN", { n, name: o.name })}</p>
                        <button type="button" onClick={() => remove(p.key)} aria-label={t("removeAria", { n })} className="inline-flex h-9 items-center gap-1 rounded-control px-2 text-sm text-ink-700 hover:bg-white">
                          <Trash2 className="size-4" aria-hidden /> {t("remove")}
                        </button>
                      </div>
                      <div className="flex items-center justify-between gap-3">
                        <span className="text-sm text-ink-900">{tb("adults")}</span>
                        <QuantityStepper
                          size="sm"
                          value={p.adults}
                          min={0}
                          max={Math.max(0, Math.min(o.capacity.max_adults, adults - otherAdults))}
                          onChange={(v) => update(p.key, { adults: v })}
                          label={t("adults", { n })}
                          decreaseLabel={tb("decrease", { item: t("adults", { n }) })}
                          increaseLabel={tb("increase", { item: t("adults", { n }) })}
                        />
                      </div>
                      {childAges.length > 0 && (
                        <div className="flex flex-wrap gap-2" role="group" aria-label={tb("children")}>
                          {childAges.map((age, c) => {
                            const here = p.children.includes(c);
                            const elsewhere = stageRooms.some((q) => q.key !== p.key && q.children.includes(c));
                            const label = age != null ? t("childToggle", { n: c + 1, age }) : t("childToggleUnknown", { n: c + 1 });
                            return (
                              <button
                                key={c}
                                type="button"
                                aria-pressed={here}
                                disabled={age == null}
                                onClick={() =>
                                  onChange(
                                    picked.map((q) =>
                                      q.key === p.key
                                        ? { ...q, children: here ? q.children.filter((x) => x !== c) : [...q.children, c] }
                                        : elsewhere && !here
                                          ? { ...q, children: q.children.filter((x) => x !== c) }
                                          : q,
                                    ),
                                  )
                                }
                                className={cn(
                                  "inline-flex h-9 items-center gap-1 rounded-pill border px-3 text-sm transition-colors disabled:opacity-50",
                                  here ? (accent === "partner" ? "border-partner-600 bg-partner-600 text-white" : "border-primary-700 bg-primary-700 text-white") : "border-ink-300 bg-white text-ink-900",
                                )}
                              >
                                {here && <Check className="size-3.5" aria-hidden />}
                                {label}
                              </button>
                            );
                          })}
                        </div>
                      )}
                      {roomErrors.length > 0 && (
                        <ul className="text-sm text-error">
                          {roomErrors.map((m) => (
                            <li key={m} className="flex items-start gap-1.5"><AlertCircle className="mt-0.5 size-4 shrink-0" aria-hidden />{m}</li>
                          ))}
                        </ul>
                      )}
                    </li>
                  );
                })}
              </ol>
            )}

            {stageRooms.length > 0 && (
              <p className={cn("flex items-center gap-2 rounded-image px-3 py-2 text-sm", unassigned || errors.length ? "bg-warning-100 text-ink-900" : "bg-success-100 text-ink-900")} role="status">
                {unassigned || errors.length ? <AlertCircle className="size-4 shrink-0 text-warning" aria-hidden /> : <CheckCircle2 className="size-4 shrink-0 text-success" aria-hidden />}
                {childAges.some((a) => a == null) ? t("agesFirst") : unassigned ? t("unassigned", { count: unassigned }) : errors.length ? ta(`errors.${errors[0].key}`, errors[0].values) : t("allAssigned")}
              </p>
            )}
          </section>
        );
      })}
      {picked.length === 0 && <p className="text-xs text-ink-500">{t("noRooms")}</p>}
    </div>
  );
}
