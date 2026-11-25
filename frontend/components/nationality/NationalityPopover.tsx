"use client";

import { Button } from "@/components/ui/Button";
import type { Country } from "@/lib/api/types";
import { useNationality } from "@/lib/nationality";
import { flagEmoji, normalizeNationality } from "@/lib/nationality-shared";
import { cn } from "@/lib/utils";
import { Check, Info, Loader2, Search, X } from "lucide-react";
import { useTranslations } from "next-intl";
import { useEffect, useId, useMemo, useRef, useState, type KeyboardEvent, type RefObject } from "react";

const POPULAR = ["IT", "EG", "DE", "GB", "FR", "SA"];

const fold = (s: string) => s.normalize("NFD").replace(/\p{Diacritic}/gu, "").toLowerCase();

/**
 * Desktop header nationality picker (Figma 250:32030): a non-modal popover anchored under the chip
 * with a title, the current pricing group, a search field driving a listbox (arrow keys, Enter,
 * Home/End), the "prices update" note and Cancel / Apply. Esc, Cancel and a click outside close it
 * and return focus to the chip; nothing changes until Apply. The mobile bottom sheet and the
 * mandatory first-visit gate stay in NationalityDialog.
 */
export function NationalityPopover({ anchorRef, onDone }: { anchorRef: RefObject<HTMLElement | null>; onDone: () => void }) {
  const t = useTranslations("nationality");
  const { code, country, data, loading, setNationality } = useNationality();
  const panelRef = useRef<HTMLDivElement>(null);
  const inputRef = useRef<HTMLInputElement>(null);
  const listRef = useRef<HTMLUListElement>(null);
  const id = useId();
  const [query, setQuery] = useState("");
  const [choice, setChoice] = useState<string | null>(code ?? null);
  const [active, setActive] = useState(0);
  const [saving, setSaving] = useState(false);

  const options = useMemo<Country[]>(() => {
    const countries = data?.countries ?? [];
    const q = fold(query.trim());
    if (q) return countries.filter((c) => fold(c.name).includes(q) || c.code.toLowerCase() === q || fold(c.group.name).includes(q));
    const byCode = new Map(countries.map((c) => [c.code, c]));
    const first = [...new Set([code, normalizeNationality(data?.detected), ...POPULAR].filter((c): c is string => Boolean(c && byCode.has(c))))];
    return [...first.map((c) => byCode.get(c)!), ...countries.filter((c) => !first.includes(c.code))];
  }, [data, query, code]);

  const close = () => {
    onDone();
    anchorRef.current?.focus();
  };

  // Focus the search field on open; close on a click outside (the chip itself toggles).
  useEffect(() => {
    inputRef.current?.focus();
    const onPointer = (e: PointerEvent) => {
      const target = e.target as Node;
      if (panelRef.current?.contains(target) || anchorRef.current?.contains(target)) return;
      onDone();
    };
    document.addEventListener("pointerdown", onPointer);
    return () => document.removeEventListener("pointerdown", onPointer);
  }, [anchorRef, onDone]);

  // Keep the highlighted option visible.
  useEffect(() => {
    listRef.current?.querySelector<HTMLElement>(`[data-index="${active}"]`)?.scrollIntoView({ block: "nearest" });
  }, [active]);

  const apply = async () => {
    if (!choice) return;
    if (choice === code) return close();
    setSaving(true);
    try {
      await setNationality(choice);
    } finally {
      setSaving(false);
      close();
    }
  };

  const onKeyDown = (e: KeyboardEvent<HTMLElement>) => {
    if (e.key === "Escape") {
      e.preventDefault();
      close();
    }
  };

  const onInputKeyDown = (e: KeyboardEvent<HTMLInputElement>) => {
    if (!options.length) return;
    const last = options.length - 1;
    if (e.key === "ArrowDown") setActive((i) => Math.min(last, i + 1));
    else if (e.key === "ArrowUp") setActive((i) => Math.max(0, i - 1));
    else if (e.key === "Home" && e.ctrlKey) setActive(0);
    else if (e.key === "End" && e.ctrlKey) setActive(last);
    else if (e.key === "Enter") {
      const option = options[Math.min(active, last)];
      // Enter on the already chosen country applies it, otherwise it selects.
      if (option.code === choice) void apply();
      else setChoice(option.code);
    } else return;
    e.preventDefault();
  };

  const optionId = (i: number) => `${id}-opt-${i}`;
  const titleId = `${id}-title`;
  const descId = `${id}-desc`;
  const listId = `${id}-list`;

  return (
    <div
      ref={panelRef}
      role="dialog"
      aria-labelledby={titleId}
      aria-describedby={descId}
      onKeyDown={onKeyDown}
      className="absolute end-0 top-full z-50 mt-2 flex w-[380px] max-w-[calc(100vw-32px)] flex-col gap-3 rounded-card border border-ink-100 bg-white p-5 text-start text-ink-900 shadow-raised animate-fade-in"
    >
      <div className="flex items-start justify-between gap-3">
        <div>
          <h2 id={titleId} className="text-lg font-semibold leading-6">
            {t("changeTitle")}
          </h2>
          <p id={descId} className="mt-0.5 text-xs text-ink-500">
            {country ? t("popoverSubtitle", { country: country.name, group: country.group.name }) : t("subtitle")}
          </p>
        </div>
        <button type="button" onClick={close} className="-me-1.5 -mt-1 grid size-8 shrink-0 place-items-center rounded-full bg-ink-100 hover:bg-ink-300/50" aria-label={t("close")}>
          <X className="size-4" aria-hidden />
        </button>
      </div>

      {loading || !data ? (
        <div className="flex h-12 items-center gap-2 text-sm text-ink-700" role="status">
          <Loader2 className="size-4 animate-spin" aria-hidden /> {t("loading")}
        </div>
      ) : (
        <>
          <div className="relative">
            <Search className="pointer-events-none absolute start-3 top-1/2 size-4 -translate-y-1/2 text-ink-500" aria-hidden />
            <input
              ref={inputRef}
              type="text"
              role="combobox"
              aria-expanded="true"
              aria-controls={listId}
              aria-autocomplete="list"
              aria-activedescendant={options.length ? optionId(Math.min(active, options.length - 1)) : undefined}
              aria-label={t("label")}
              placeholder={t("searchPlaceholder")}
              value={query}
              onChange={(e) => {
                setQuery(e.target.value);
                setActive(0);
              }}
              onKeyDown={onInputKeyDown}
              autoComplete="off"
              spellCheck={false}
              className="h-10 w-full rounded-control border-2 border-primary-700 bg-white ps-9 pe-3 text-sm text-ink-900 outline-none placeholder:text-ink-500 focus-visible:ring-2 focus-visible:ring-primary-700/30"
            />
          </div>
          <ul ref={listRef} id={listId} role="listbox" aria-label={t("allCountries")} className="-mx-1 max-h-56 overflow-y-auto px-1">
            {options.length === 0 && (
              <li role="presentation" className="px-3 py-2 text-sm text-ink-500">
                {t("noMatch")}
              </li>
            )}
            {options.map((c, i) => {
              const selected = c.code === choice;
              const highlighted = i === Math.min(active, options.length - 1);
              return (
                <li
                  key={c.code}
                  id={optionId(i)}
                  data-index={i}
                  role="option"
                  aria-selected={selected}
                  onPointerDown={(e) => e.preventDefault()}
                  onClick={() => {
                    setChoice(c.code);
                    setActive(i);
                    inputRef.current?.focus();
                  }}
                  onMouseMove={() => setActive(i)}
                  className={cn(
                    "flex cursor-pointer items-center gap-2.5 rounded-control px-3 py-2 text-sm",
                    selected ? "bg-primary-100 font-semibold text-primary-700" : "text-ink-900",
                    highlighted && !selected && "bg-ink-100",
                    highlighted && "outline outline-2 -outline-offset-2 outline-primary-700/40",
                  )}
                >
                  <span className="text-base leading-none" aria-hidden>
                    {flagEmoji(c.code)}
                  </span>
                  <span className="min-w-0 flex-1 truncate">{c.name}</span>
                  {selected && <Check className="size-4 shrink-0" aria-hidden />}
                </li>
              );
            })}
          </ul>
        </>
      )}

      <p className="flex items-start gap-2 rounded-image bg-sand-50 p-3 text-xs text-ink-700">
        <Info className="mt-0.5 size-4 shrink-0 text-primary-700" aria-hidden /> {t("changeNote")}
      </p>

      <div className="flex items-center justify-end gap-2">
        <Button variant="ghost" size="sm" onClick={close}>
          {t("cancel")}
        </Button>
        <Button variant="primary" size="sm" disabled={!choice} loading={saving} onClick={() => void apply()}>
          {t("apply")}
        </Button>
      </div>
    </div>
  );
}
