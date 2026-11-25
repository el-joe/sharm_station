"use client";

import { Button } from "@/components/ui/Button";
import { QuantityStepper } from "@/components/ui/QuantityStepper";
import { occupants, roomCapacity, type AllocationError, type AllocRoom, type Traveller } from "@/lib/allocation";
import { useCurrency } from "@/lib/currency";
import { cn } from "@/lib/utils";
import { AlertCircle, Baby, Check, CheckCircle2, CircleCheck, MoveDown, User, Users, Wand2, X } from "lucide-react";
import { useTranslations } from "next-intl";
import { useEffect, useState, type DragEvent, type ReactNode } from "react";

export interface BoardRoom extends AllocRoom {
  name: string;
  number?: string | null;
  extra_beds: number;
  cots: number;
  max_extra_beds: number;
  max_cots: number;
  /** EUR per night per bed / cot (null: not offered). */
  extra_bed_price: number | null;
  cot_price: number | null;
}

const DRAG_TYPE = "application/x-ssv-traveller";

/**
 * Figma RoomAllocationBoard (27:10110), "Who sleeps where?": rooms are drop zones and travellers are chips.
 * Desktop: drag a chip onto a room (HTML drag and drop). Touch and keyboard: press a chip (it becomes selected,
 * aria-pressed) and then "Move … here" on a room, or "Remove … from the room" on the unassigned pool. Every move is
 * announced in a polite live region. Extra beds and cots have steppers per room within the room's limits.
 * Validation (SRS 7.2) comes from lib/allocation.ts; the server's own messages are shown per room as well.
 * The layout follows the board's own width (container queries): it sits in the website's booking column and in
 * the narrower partner-panel configure step.
 */
export function RoomAllocationBoard({
  rooms,
  travellers,
  assignment,
  errors,
  serverRoomErrors = {},
  serverErrors = [],
  onAssign,
  onExtras,
  onRemoveRoom,
  onAutoAssign,
  footer,
}: {
  rooms: BoardRoom[];
  travellers: Traveller[];
  assignment: Record<string, string | null | undefined>;
  errors: AllocationError[];
  /** Server 422 messages per room position (rooms.N.*). */
  serverRoomErrors?: Record<number, string[]>;
  /** Server 422 messages about the whole stay (rooms, check_in, check_out…). */
  serverErrors?: string[];
  onAssign: (travellerId: string, roomKey: string | null) => void;
  onExtras: (roomKey: string, extras: { extra_beds?: number; cots?: number }) => void;
  onRemoveRoom: (roomKey: string) => void;
  onAutoAssign: () => void;
  footer?: ReactNode;
}) {
  const t = useTranslations("allocation");
  const tb = useTranslations("booking");
  const { format: money } = useCurrency();
  const [selected, setSelected] = useState<string | null>(null);
  const [over, setOver] = useState<string | null>(null);
  const [announcement, setAnnouncement] = useState("");
  // Some browsers (and synthetic drags) do not expose custom drag data on drop: keep the dragged id here too.
  const [dragging, setDragging] = useState<string | null>(null);

  const keys = new Set(rooms.map((r) => r.key));
  const roomLabel = (i: number) => t("room", { n: i + 1 });
  const name = (tr: Traveller) => (tr.type === "adult" ? t("adult", { n: tr.n + 1 }) : tr.age != null ? t("child", { age: tr.age }) : t("childUnknown", { n: tr.n + 1 }));
  const placeOf = (tr: Traveller) => {
    const i = rooms.findIndex((r) => r.key === assignment[tr.id]);
    return i >= 0 ? t("inRoom", { room: roomLabel(i) }) : t("notAssigned");
  };
  const selectedTraveller = travellers.find((tr) => tr.id === selected) ?? null;
  const unassigned = travellers.filter((tr) => !assignment[tr.id] || !keys.has(assignment[tr.id]!));

  // Escape cancels a tap selection.
  useEffect(() => {
    if (!selected) return;
    const onKey = (e: KeyboardEvent) => e.key === "Escape" && setSelected(null);
    window.addEventListener("keydown", onKey);
    return () => window.removeEventListener("keydown", onKey);
  }, [selected]);

  const move = (id: string, roomKey: string | null) => {
    const tr = travellers.find((x) => x.id === id);
    if (!tr) return;
    onAssign(id, roomKey);
    setSelected(null);
    const i = rooms.findIndex((r) => r.key === roomKey);
    setAnnouncement(i >= 0 ? t("announceMoved", { name: name(tr), room: roomLabel(i) }) : t("announceUnassigned", { name: name(tr) }));
  };

  const dropProps = (roomKey: string | null, zone: string) => ({
    onDragOver: (e: DragEvent) => {
      if (!dragging && !e.dataTransfer.types.includes(DRAG_TYPE)) return;
      e.preventDefault();
      e.dataTransfer.dropEffect = "move";
      if (over !== zone) setOver(zone);
    },
    onDragLeave: (e: DragEvent) => {
      if (!e.currentTarget.contains(e.relatedTarget as Node | null)) setOver(null);
    },
    onDrop: (e: DragEvent) => {
      e.preventDefault();
      setOver(null);
      const id = e.dataTransfer.getData(DRAG_TYPE) || dragging;
      setDragging(null);
      if (id) move(id, roomKey);
    },
  });

  const chip = (tr: Traveller) => {
    const isSelected = selected === tr.id;
    const assigned = Boolean(assignment[tr.id] && keys.has(assignment[tr.id]!));
    return (
      <li key={tr.id}>
        <button
          type="button"
          draggable
          onDragStart={(e) => {
            setDragging(tr.id);
            e.dataTransfer.setData(DRAG_TYPE, tr.id);
            e.dataTransfer.setData("text/plain", name(tr));
            e.dataTransfer.effectAllowed = "move";
          }}
          onDragEnd={() => {
            setDragging(null);
            setOver(null);
          }}
          onClick={() => {
            const next = isSelected ? null : tr.id;
            setSelected(next);
            if (next) setAnnouncement(t("announceSelected", { name: name(tr) }));
          }}
          aria-pressed={isSelected}
          aria-label={isSelected ? t("chipSelectedAria", { name: name(tr) }) : t("chipAria", { name: name(tr), place: placeOf(tr) })}
          data-traveller={tr.id}
          className={cn(
            "inline-flex h-11 cursor-grab touch-manipulation items-center gap-2 rounded-pill border px-4 text-sm font-medium transition-colors active:cursor-grabbing focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-primary-500",
            isSelected ? "border-primary-700 bg-primary-700 text-white" : "border-brand-navy-900 bg-primary-100 text-ink-900 hover:bg-white",
          )}
        >
          {assigned && <Check className="size-4" aria-hidden />}
          {tr.type === "adult" ? <User className="size-4" aria-hidden /> : <Baby className="size-4" aria-hidden />}
          {name(tr)}
        </button>
      </li>
    );
  };

  const general = errors.filter((e) => e.room === null).map((e) => t(`errors.${e.key}`, e.values));
  const summary = [...new Set([...general, ...serverErrors])];
  const allRoomMessages = rooms.flatMap((_, i) => roomMessages(i));
  const valid = rooms.length > 0 && errors.length === 0 && serverErrors.length === 0 && Object.values(serverRoomErrors).every((l) => !l.length);

  function roomMessages(i: number) {
    const own = errors.filter((e) => e.room === i).map((e) => t(`errors.${e.key}`, e.values));
    return [...new Set([...own, ...(serverRoomErrors[i] ?? [])])];
  }

  return (
    <section className="@container rounded-card border border-ink-100 bg-white p-5 shadow-card md:p-6" aria-labelledby="alloc-title" data-testid="allocation-board">
      <div className="flex flex-wrap items-start justify-between gap-3">
        <div>
          <h3 id="alloc-title" className="text-h4 text-ink-900">{t("title")}</h3>
          <p className="mt-1 text-sm text-ink-700">{t("subtitle")}</p>
          <p className="mt-1 text-xs text-ink-500">{t("dragHint")}</p>
        </div>
        <Button variant="outline" size="sm" className="h-11" onClick={onAutoAssign} disabled={!rooms.length} icon={<Wand2 className="size-4" aria-hidden />}>
          {t("autoAssign")}
        </Button>
      </div>
      <p className="sr-only" aria-live="polite" role="status">{announcement}</p>

      <div className="mt-5 grid grid-cols-[minmax(0,1fr)] gap-4 @2xl:grid-cols-[minmax(0,220px)_minmax(0,1fr)]">
        <div
          {...dropProps(null, "pool")}
          className={cn("flex flex-col gap-3 self-start rounded-image bg-sand-50 p-4 transition-shadow", over === "pool" && "ring-2 ring-primary-500")}
          role="group"
          aria-labelledby="alloc-pool"
        >
          <p id="alloc-pool" className="text-eyebrow text-ink-500">{t("unassigned")}</p>
          {unassigned.length ? (
            <ul className="flex flex-wrap gap-2">{unassigned.map(chip)}</ul>
          ) : (
            <p className="flex items-center gap-2 text-sm text-ink-900"><CircleCheck className="size-4 text-success" aria-hidden />{t("everyoneAssigned")}</p>
          )}
          {selectedTraveller && assignment[selectedTraveller.id] && (
            <Button variant="ghost" size="sm" className="h-11 self-start" onClick={() => move(selectedTraveller.id, null)}>
              {t("unassignHere", { name: name(selectedTraveller) })}
            </Button>
          )}
        </div>

        <ol className="grid min-w-0 grid-cols-[minmax(0,1fr)] gap-4 @5xl:grid-cols-2">
          {rooms.map((r, i) => {
            const occ = occupants(travellers, assignment, r.key);
            const used = occ.list.length;
            const cap = roomCapacity(r);
            const messages = roomMessages(i);
            const bad = messages.length > 0;
            return (
              <li
                key={r.key}
                {...dropProps(r.key, r.key)}
                aria-labelledby={`alloc-room-${r.key}`}
                data-room={r.key}
                className={cn("flex min-w-0 flex-col gap-3 rounded-image border p-4 transition-shadow", bad ? "border-2 border-error" : "border-ink-100", over === r.key && "ring-2 ring-primary-500")}
              >
                <div className="flex items-start justify-between gap-2">
                  <div className="min-w-0">
                    <h4 id={`alloc-room-${r.key}`} className="font-semibold text-ink-900">{roomLabel(i)}</h4>
                    <p className="truncate text-xs text-ink-500">{[r.name, r.number].filter(Boolean).join(" · ")}</p>
                  </div>
                  <div className="flex shrink-0 items-center gap-1">
                    <span
                      className={cn("inline-flex items-center gap-1 rounded-pill px-2 py-1 text-xs font-medium", used > cap ? "bg-error-100 text-error" : "bg-primary-100 text-ink-900")}
                      aria-label={t("sleepsAria", { used, count: cap })}
                    >
                      <Users className="size-3.5" aria-hidden />
                      {t("sleeps", { count: cap })} <span className="tabular-nums">{used}/{cap}</span>
                    </span>
                    <button type="button" onClick={() => onRemoveRoom(r.key)} aria-label={t("removeRoomAria", { room: `${roomLabel(i)} · ${r.name}` })} className="grid size-9 place-items-center rounded-full text-ink-500 hover:bg-ink-100 hover:text-ink-900">
                      <X className="size-4" aria-hidden />
                    </button>
                  </div>
                </div>
                <div className="min-h-14 rounded-image bg-sand-50 p-2">
                  {occ.list.length > 0 ? <ul className="flex flex-wrap gap-2">{occ.list.map(chip)}</ul> : <p className="p-2 text-sm text-ink-500">—</p>}
                </div>
                {selectedTraveller && assignment[selectedTraveller.id] !== r.key && (
                  <Button variant="outline" size="sm" className="h-11" onClick={() => move(selectedTraveller.id, r.key)} aria-label={t("moveHereAria", { name: name(selectedTraveller), room: roomLabel(i) })} icon={<MoveDown className="size-4" aria-hidden />}>
                    {t("moveHere", { name: name(selectedTraveller) })}
                  </Button>
                )}
                {(r.max_extra_beds > 0 || r.max_cots > 0) && (
                  <div className="flex flex-col gap-2 border-t border-ink-100 pt-3">
                    {r.max_extra_beds > 0 && (
                      <Extra
                        label={`${t("extraBeds")} · ${roomLabel(i)}`}
                        title={t("extraBeds")}
                        hint={r.extra_bed_price ? t("perNight", { price: money(r.extra_bed_price) }) : undefined}
                        value={r.extra_beds}
                        max={r.max_extra_beds}
                        onChange={(v) => onExtras(r.key, { extra_beds: v })}
                        decrease={tb("decrease", { item: `${t("extraBeds")} · ${roomLabel(i)}` })}
                        increase={tb("increase", { item: `${t("extraBeds")} · ${roomLabel(i)}` })}
                      />
                    )}
                    {r.max_cots > 0 && (
                      <Extra
                        label={`${t("cots")} · ${roomLabel(i)}`}
                        title={t("cots")}
                        hint={r.cot_price ? t("perNight", { price: money(r.cot_price) }) : undefined}
                        value={r.cots}
                        max={r.max_cots}
                        onChange={(v) => onExtras(r.key, { cots: v })}
                        decrease={tb("decrease", { item: `${t("cots")} · ${roomLabel(i)}` })}
                        increase={tb("increase", { item: `${t("cots")} · ${roomLabel(i)}` })}
                      />
                    )}
                  </div>
                )}
                {bad && (
                  <ul className="flex flex-col gap-1 text-sm text-error" data-testid="room-errors">
                    {messages.map((m) => (
                      <li key={m} className="flex items-start gap-1.5"><AlertCircle className="mt-0.5 size-4 shrink-0" aria-hidden />{m}</li>
                    ))}
                  </ul>
                )}
              </li>
            );
          })}
        </ol>
      </div>

      {rooms.length > 0 && (
        <div className="mt-5" role={valid ? "status" : "alert"} data-testid="allocation-status">
          {valid ? (
            <p className="flex items-center gap-2 rounded-image border border-success bg-success-100 px-4 py-3 font-semibold text-ink-900">
              <CheckCircle2 className="size-5 shrink-0 text-success" aria-hidden />
              {t("allValid", { count: travellers.length })}
            </p>
          ) : (
            <ul className="flex flex-col gap-1 rounded-image border border-error bg-error-100 px-4 py-3 font-semibold text-ink-900">
              {(summary.length ? summary : allRoomMessages.slice(0, 1)).map((m) => (
                <li key={m} className="flex items-start gap-2"><AlertCircle className="mt-0.5 size-5 shrink-0 text-error" aria-hidden />{m}</li>
              ))}
            </ul>
          )}
        </div>
      )}
      {footer}
    </section>
  );
}

function Extra({ label, title, hint, value, max, onChange, decrease, increase }: { label: string; title: string; hint?: string; value: number; max: number; onChange: (v: number) => void; decrease: string; increase: string }) {
  return (
    <div className="flex items-center justify-between gap-3">
      <div>
        <p className="text-sm font-medium text-ink-900">{title}</p>
        {hint && <p className="text-xs text-ink-500">{hint}</p>}
      </div>
      <QuantityStepper size="sm" value={value} min={0} max={max} onChange={onChange} label={label} decreaseLabel={decrease} increaseLabel={increase} />
    </div>
  );
}
