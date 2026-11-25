"use client";

import { Button } from "@/components/ui/Button";
import { SelectMenu } from "@/components/ui/SelectMenu";
import { ApiError } from "@/lib/api/client";
import { useCurrency } from "@/lib/currency";
import { cn } from "@/lib/utils";
import { BedDouble, CheckCircle2, Sparkles, Users } from "lucide-react";
import { useTranslations } from "next-intl";
import { RoomAllocationBoard } from "./RoomAllocationBoard";
import type { MultiRoomStay, RoomCombinationsState } from "./useMultiRoomStay";

/**
 * Body of a multi-room stay (B2.1, B2.2): "best combinations" (one click applies rooms, extra beds / cots and the
 * allocation), "Add a room" and the RoomAllocationBoard. The stay / party fields and the price summary belong to the
 * caller (website building page, partner new-booking wizard). `tone="partner"` uses the partner panel colours.
 */
export function MultiRoomPlanner({
  stay,
  combos,
  agesOk,
  nights,
  serverRoomErrors,
  serverErrors,
  tone = "site",
  idPrefix,
}: {
  stay: MultiRoomStay;
  combos: RoomCombinationsState;
  agesOk: boolean;
  nights: number;
  serverRoomErrors?: Record<number, string[]>;
  serverErrors?: string[];
  tone?: "site" | "partner";
  idPrefix: string;
}) {
  const t = useTranslations("multiRoom");
  const { format: money } = useCurrency();
  const { query, current } = combos;
  const partner = tone === "partner";

  return (
    <>
      <p className="sr-only" aria-live="polite" role="status">{stay.announcement}</p>

      {/* Best combinations */}
      <section aria-labelledby={`${idPrefix}-combos`} className="@container flex flex-col gap-3">
        <div>
          <h3 id={`${idPrefix}-combos`} className="flex items-center gap-2 text-h4 text-ink-900">
            <Sparkles className={cn("size-5", partner ? "text-partner-600" : "text-cta-600")} aria-hidden />
            {t("suggestionsTitle")}
          </h3>
          <p className="text-sm text-ink-700">{t("suggestionsText")}</p>
        </div>
        {!agesOk ? null : !current || (query.isPending && query.fetchStatus !== "idle") ? (
          <p className="text-sm text-ink-700" role="status">{t("suggestionsLoading")}</p>
        ) : query.isError ? (
          <p className="rounded-image bg-warning-100 p-3 text-sm text-ink-900" role="alert">
            {query.error instanceof ApiError && query.error.isValidation ? Object.values(query.error.errors).flat()[0] : t("suggestionsError")}
          </p>
        ) : query.data && query.data.combinations.length === 0 ? (
          <p className="rounded-image bg-sand-50 p-3 text-sm text-ink-900" role="status">{query.data.message ?? t("suggestionsNone")}</p>
        ) : query.data ? (
          <ul className="grid grid-cols-[minmax(0,1fr)] gap-3 @md:grid-cols-2 @3xl:grid-cols-3" aria-label={t("suggestionsTitle")}>
            {query.data.combinations.map((c, i) => {
              const roomsText = c.rooms.map((r) => r.name).join(" + ");
              const isApplied = stay.applied === i;
              return (
                <li
                  key={i}
                  className={cn("flex flex-col gap-3 rounded-card border bg-white p-4 shadow-card", isApplied ? cn("border-2", partner ? "border-partner-600" : "border-primary-700") : "border-ink-100")}
                  data-testid="combination"
                >
                  <div className="flex items-center justify-between gap-2 text-sm">
                    <span className="inline-flex items-center gap-1 font-semibold text-ink-900"><BedDouble className="size-4" aria-hidden />{t("roomsCount", { count: c.rooms_count })}</span>
                    <span className="inline-flex items-center gap-1 text-ink-700"><Users className="size-4" aria-hidden />{t("sleeps", { count: c.capacity })}</span>
                  </div>
                  <ul className="flex flex-col gap-1.5 text-sm">
                    {c.rooms.map((r, j) => {
                      const extras = [r.extra_beds ? t("extraBeds", { count: r.extra_beds }) : null, r.cots ? t("cots", { count: r.cots }) : null].filter(Boolean).join(" · ");
                      return (
                        <li key={j} className="flex justify-between gap-2">
                          <span className="min-w-0">
                            <span className="block truncate font-medium text-ink-900">{r.name}{r.number ? ` · ${r.number}` : ""}</span>
                            <span className="text-xs text-ink-500">{[t("guestsIn", { adults: r.adults, children: r.children_ages.length }), extras || null].filter(Boolean).join(" · ")}</span>
                          </span>
                          <span className="shrink-0 tabular-nums text-ink-900">{money(r.total)}</span>
                        </li>
                      );
                    })}
                  </ul>
                  <div className="mt-auto flex items-end justify-between gap-2 border-t border-ink-100 pt-3">
                    <span className="text-xs text-ink-500">{t("totalFor", { nights: query.data.nights || nights })}</span>
                    <span className="text-xl font-bold tabular-nums text-ink-900">{money(c.total)}</span>
                  </div>
                  <Button
                    variant={isApplied ? "outline" : partner ? "partner" : "primary"}
                    size="sm"
                    className="h-11"
                    onClick={() => stay.applyCombination(c, i)}
                    aria-label={t("applyAria", { n: i + 1, rooms: roomsText, total: money(c.total) })}
                    icon={isApplied ? <CheckCircle2 className="size-4" aria-hidden /> : undefined}
                  >
                    {isApplied ? t("applied") : t("apply")}
                  </Button>
                </li>
              );
            })}
          </ul>
        ) : null}
      </section>

      {/* Manual room choice + allocation */}
      <div className="flex flex-col gap-2 sm:max-w-md">
        <label htmlFor={`${idPrefix}-add`} className="text-sm font-medium text-ink-900">{t("addRoom")}</label>
        <SelectMenu id={`${idPrefix}-add`} value="" onChange={(e) => stay.addRoom(Number(e.target.value))} disabled={!stay.available.length || stay.picked.length >= 10}>
          <option value="">{t("addRoomPlaceholder")}</option>
          {stay.available.map((r) => (
            <option key={r.id} value={r.id}>{t("addRoomOption", { name: [r.name, r.number].filter(Boolean).join(" "), count: r.max_total, price: money(r.price_per_night) })}</option>
          ))}
        </SelectMenu>
      </div>

      {stay.boardRooms.length === 0 ? (
        <p className="rounded-image border border-dashed border-ink-300 p-6 text-center text-ink-700">{t("noRooms")}</p>
      ) : (
        <RoomAllocationBoard
          rooms={stay.boardRooms}
          travellers={stay.travellers}
          assignment={stay.assignment}
          errors={stay.clientErrors}
          serverRoomErrors={serverRoomErrors}
          serverErrors={serverErrors}
          onAssign={stay.assign}
          onExtras={stay.setExtras}
          onRemoveRoom={stay.removeRoom}
          onAutoAssign={stay.autoAssign}
        />
      )}
    </>
  );
}
