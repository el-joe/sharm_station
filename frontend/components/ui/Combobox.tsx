"use client";

import { cn } from "@/lib/utils";
import { Check, ChevronDown, Loader2, MapPin, SearchX, X } from "lucide-react";
import { useCallback, useEffect, useId, useMemo, useRef, useState, type KeyboardEvent, type ReactNode } from "react";

export interface ComboboxOption {
  /** Stable value returned on select (slug, "hotel:slug", hub id, place name...). */
  value: string;
  label: string;
  /** Second line: the location ("Naama Bay, Sharm El Sheikh, Egypt") or a short fact. */
  description?: string;
  /** Short trailing note, e.g. "Fixed price". */
  badge?: string;
  icon?: ReactNode;
  /** Extra words that should also match the typed text (city, aliases...). */
  keywords?: string[];
  /** Anything the caller needs back on select (coordinates, type...). */
  data?: Record<string, unknown>;
}

export interface ComboboxGroup {
  key: string;
  label: string;
  options: ComboboxOption[];
}

/** Lower-case and strip accents so "aswan" matches "Aswān". */
export function normalize(text: string) {
  return text.normalize("NFD").replace(/\p{Diacritic}/gu, "").toLowerCase();
}

/** "Hurghada  sta" -> ["hurghada", "sta"] */
export function tokenize(text: string) {
  return normalize(text)
    .split(/[^\p{L}\p{N}]+/u)
    .filter(Boolean)
    .slice(0, 5);
}

/** Every word must appear somewhere in the label, description or keywords. */
function matches(option: ComboboxOption, tokens: string[]) {
  if (!tokens.length) return true;
  const haystack = normalize([option.label, option.description ?? "", ...(option.keywords ?? [])].join(" "));
  return tokens.every((token) => haystack.includes(token));
}

/** Bold every typed word inside the text (Halla-style "**Star** Apartment **Hurghada**"). */
function Highlight({ text, tokens }: { text: string; tokens: string[] }) {
  if (!tokens.length) return <>{text}</>;
  const lower = normalize(text);
  const marks = new Array<boolean>(text.length).fill(false);
  for (const token of tokens) {
    let from = 0;
    let index: number;
    while (token && (index = lower.indexOf(token, from)) !== -1) {
      for (let i = index; i < index + token.length; i++) marks[i] = true;
      from = index + token.length;
    }
  }
  const parts: ReactNode[] = [];
  let i = 0;
  while (i < text.length) {
    const marked = marks[i];
    let j = i;
    while (j < text.length && marks[j] === marked) j++;
    const chunk = text.slice(i, j);
    parts.push(marked ? <mark key={i} className="bg-transparent font-bold text-ink-900">{chunk}</mark> : chunk);
    i = j;
  }
  return <>{parts}</>;
}

/**
 * Type-ahead picker (ARIA 1.2 combobox + listbox), modelled on large travel sites:
 * suggestions on focus, word-by-word matching with highlights, a location line per row,
 * keyboard support (↑ ↓ Home End Enter Esc), optional server results (`loadGroups`,
 * debounced) and an optional "Use …" row for free text such as a hotel address.
 */
export function Combobox({
  id: idProp,
  label,
  groups,
  loadGroups,
  value,
  onInputChange,
  onSelect,
  placeholder,
  icon,
  variant = "hero",
  allowFreeText = false,
  freeTextLabel = (text: string) => `Use “${text}”`,
  emptyText = "No matches",
  clearLabel = "Clear",
  loadingText = "Searching…",
  invalid,
  describedBy,
  required,
  hideLabel = false,
}: {
  id?: string;
  label: string;
  /** Suggestions available instantly (shown on focus and filtered locally while typing). */
  groups: ComboboxGroup[];
  /** Optional server search; replaces the local results for the typed text once it resolves. */
  loadGroups?: (query: string, signal: AbortSignal) => Promise<ComboboxGroup[]>;
  value: string;
  onInputChange: (text: string) => void;
  /** Called with the chosen option, a free-text option ({value: text}) or null when cleared. */
  onSelect: (option: ComboboxOption | null) => void;
  placeholder?: string;
  icon?: ReactNode;
  /** "hero": large search-card field with an icon chip; "field": regular form control. */
  variant?: "hero" | "field";
  allowFreeText?: boolean;
  freeTextLabel?: (text: string) => string;
  emptyText?: string;
  clearLabel?: string;
  loadingText?: string;
  invalid?: boolean;
  describedBy?: string;
  required?: boolean;
  /** Keep the label for screen readers only (when the surrounding UI already shows it). */
  hideLabel?: boolean;
}) {
  const autoId = useId();
  const id = idProp ?? autoId;
  const listId = `${id}-listbox`;
  const rootRef = useRef<HTMLDivElement>(null);
  const inputRef = useRef<HTMLInputElement>(null);
  const listRef = useRef<HTMLUListElement>(null);
  const [open, setOpen] = useState(false);
  const [active, setActive] = useState(-1);
  /** Text typed since the field was focused; empty means "show the default suggestions". */
  const [query, setQuery] = useState("");
  const [remote, setRemote] = useState<{ query: string; groups: ComboboxGroup[] } | null>(null);
  const [loading, setLoading] = useState(false);

  const tokens = useMemo(() => tokenize(query), [query]);

  // Latest loader in a ref: callers may pass a new function every render, which must not
  // restart (and abort) the request in flight.
  const loadRef = useRef(loadGroups);
  useEffect(() => {
    loadRef.current = loadGroups;
  });
  const canLoad = Boolean(loadGroups);

  // Debounced server search for the typed text.
  useEffect(() => {
    const load = loadRef.current;
    if (!load || !open || !tokens.length) return;
    const controller = new AbortController();
    const timer = setTimeout(() => {
      setLoading(true);
      load(query, controller.signal)
        .then((result) => setRemote({ query, groups: result }))
        .catch(() => {
          /* keep local results on network errors */
        })
        .finally(() => !controller.signal.aborted && setLoading(false));
    }, 200);
    return () => {
      clearTimeout(timer);
      controller.abort();
    };
  }, [canLoad, open, query, tokens.length]);

  const source = remote && tokens.length && remote.query === query ? remote.groups : groups;

  const filtered = useMemo(
    () => source.map((g) => ({ ...g, options: g.options.filter((o) => matches(o, tokens)) })).filter((g) => g.options.length > 0),
    [source, tokens],
  );

  const exactMatch = filtered.some((g) => g.options.some((o) => normalize(o.label).trim() === normalize(query).trim()));
  const freeText = allowFreeText && query.trim() && !exactMatch ? query.trim() : null;

  /** Flat list of selectable rows in display order (free-text row first). */
  const flat = useMemo(() => {
    const rows: ComboboxOption[] = [];
    if (freeText) rows.push({ value: freeText, label: freeText });
    filtered.forEach((g) => rows.push(...g.options));
    return rows;
  }, [filtered, freeText]);

  const close = useCallback(() => {
    setOpen(false);
    setActive(-1);
    setQuery("");
    setLoading(false);
  }, []);

  useEffect(() => {
    if (!open) return;
    const onPointer = (event: PointerEvent) => {
      if (!rootRef.current?.contains(event.target as Node)) close();
    };
    document.addEventListener("pointerdown", onPointer);
    return () => document.removeEventListener("pointerdown", onPointer);
  }, [open, close]);

  useEffect(() => {
    if (active < 0) return;
    listRef.current?.querySelector<HTMLElement>(`[data-index="${active}"]`)?.scrollIntoView({ block: "nearest" });
  }, [active]);

  const choose = (option: ComboboxOption) => {
    // The free-text row is labelled "Use “…”"; the field and the selection keep the typed text.
    const picked = option.data?.freeText ? { value: option.value, label: option.value, data: option.data } : option;
    onInputChange(picked.label);
    onSelect(picked);
    close();
  };

  const onKeyDown = (event: KeyboardEvent<HTMLInputElement>) => {
    switch (event.key) {
      case "ArrowDown":
        event.preventDefault();
        if (!open) setOpen(true);
        setActive((i) => (flat.length ? (i + 1) % flat.length : -1));
        break;
      case "ArrowUp":
        event.preventDefault();
        if (!open) setOpen(true);
        setActive((i) => (flat.length ? (i <= 0 ? flat.length - 1 : i - 1) : -1));
        break;
      case "Home":
        if (open && flat.length) {
          event.preventDefault();
          setActive(0);
        }
        break;
      case "End":
        if (open && flat.length) {
          event.preventDefault();
          setActive(flat.length - 1);
        }
        break;
      case "Enter":
        if (open && active >= 0 && flat[active]) {
          event.preventDefault();
          choose(flat[active]);
        } else if (open && flat.length === 1) {
          event.preventDefault();
          choose(flat[0]);
        }
        break;
      case "Escape":
        if (open) {
          event.preventDefault();
          close();
        }
        break;
      case "Tab":
        close();
        break;
    }
  };

  const clear = () => {
    onInputChange("");
    onSelect(null);
    setQuery("");
    setActive(-1);
    setOpen(true);
    inputRef.current?.focus();
  };

  const hero = variant === "hero";
  let rowIndex = freeText ? 1 : 0;
  const showLoading = loading && tokens.length > 0;

  const row = (option: ComboboxOption, index: number, key: string) => {
    const selected = normalize(option.label) === normalize(value);
    return (
      <li
        key={key}
        id={`${id}-opt-${index}`}
        data-index={index}
        role="option"
        aria-selected={active === index}
        onPointerDown={(e) => e.preventDefault()}
        onClick={() => choose(option)}
        onPointerMove={() => active !== index && setActive(index)}
        className={cn(
          "flex cursor-pointer items-center gap-3 border-b border-ink-100 px-4 py-3 last:border-b-0",
          active === index ? "bg-primary-100" : "hover:bg-sand-50",
        )}
      >
        <span className="shrink-0 text-primary-500 [&_svg]:size-5">{option.icon ?? <MapPin aria-hidden />}</span>
        <span className="flex min-w-0 flex-1 flex-col">
          <span className="truncate font-medium text-ink-900">
            <Highlight text={option.label} tokens={tokens} />
          </span>
          {option.description && (
            <span className="truncate text-[13px] text-ink-500">
              <Highlight text={option.description} tokens={tokens} />
            </span>
          )}
        </span>
        {option.badge && (
          <span className="hidden shrink-0 rounded-pill bg-success-100 px-2 py-0.5 text-xs font-medium text-success sm:inline">{option.badge}</span>
        )}
        {selected && <Check className="size-4 shrink-0 text-primary-700" aria-hidden />}
      </li>
    );
  };

  return (
    <div ref={rootRef} className={cn("relative min-w-0", hero && "flex flex-1 flex-col gap-1.5")}>
      <label
        htmlFor={id}
        className={cn(
          hideLabel ? "sr-only" : hero ? "text-xs font-medium uppercase tracking-[0.06em] text-ink-500" : "mb-1 block text-sm text-ink-700",
        )}
      >
        {label}
      </label>
      <div
        className={cn(
          "flex items-center gap-3 border transition-colors",
          hero
            ? "h-14 rounded-image border-ink-300 bg-ink-100 px-4 focus-within:border-primary-500 focus-within:bg-white"
            : "h-12 rounded-control border-ink-300 bg-ink-100 ps-4 pe-2 focus-within:border-primary-500 focus-within:bg-white focus-within:ring-2 focus-within:ring-primary-100",
          invalid && "border-error bg-white",
          open && "border-primary-500 bg-white",
        )}
      >
        {hero && icon && <span className="grid size-8 shrink-0 place-items-center rounded-full bg-brand-100 text-brand-700">{icon}</span>}
        {!hero && icon && <span className="shrink-0 text-brand-700 [&_svg]:size-4">{icon}</span>}
        <input
          ref={inputRef}
          id={id}
          type="text"
          role="combobox"
          aria-expanded={open}
          aria-controls={listId}
          aria-autocomplete="list"
          aria-activedescendant={open && active >= 0 ? `${id}-opt-${active}` : undefined}
          aria-invalid={invalid || undefined}
          aria-describedby={describedBy}
          aria-required={required || undefined}
          autoComplete="off"
          spellCheck={false}
          value={value}
          placeholder={placeholder}
          onChange={(e) => {
            onInputChange(e.target.value);
            setQuery(e.target.value);
            setActive(e.target.value.trim() ? 0 : -1);
            setOpen(true);
          }}
          onFocus={() => setOpen(true)}
          onClick={() => setOpen(true)}
          onKeyDown={onKeyDown}
          className="h-full min-w-0 flex-1 bg-transparent text-base text-ink-900 placeholder:text-ink-500 focus:outline-none"
        />
        <span className="flex shrink-0 items-center gap-1">
          {showLoading && <Loader2 className="size-4 animate-spin text-ink-500" aria-hidden />}
          {value && (
            <button
              type="button"
              onClick={clear}
              aria-label={clearLabel}
              className="grid size-8 place-items-center rounded-full text-ink-500 transition-colors hover:bg-ink-100 hover:text-ink-900"
            >
              <X className="size-4" aria-hidden />
            </button>
          )}
          <ChevronDown className={cn("size-5 text-ink-500 transition-transform", open && "rotate-180")} aria-hidden />
        </span>
      </div>

      {open && (
        <div className="absolute inset-x-0 top-full z-50 mt-2 overflow-hidden rounded-image border border-ink-100 bg-white text-start shadow-raised">
          <ul ref={listRef} id={listId} role="listbox" aria-label={label} aria-busy={showLoading || undefined} className="max-h-[22rem] overflow-y-auto overscroll-contain">
            {freeText && row({ value: freeText, label: freeTextLabel(freeText), data: { freeText: true } }, 0, "free-text")}

            {filtered.map((group) => (
              <li key={group.key} role="presentation">
                {(filtered.length > 1 || !tokens.length) && (
                  <p className="sticky top-0 z-[1] border-b border-ink-100 bg-sand-50 px-4 py-1.5 text-[11px] font-semibold uppercase tracking-[0.08em] text-ink-500" aria-hidden>
                    {group.label}
                  </p>
                )}
                <ul role="group" aria-label={group.label}>
                  {group.options.map((option) => row(option, rowIndex++, `${group.key}-${option.value}`))}
                </ul>
              </li>
            ))}

            {flat.length === 0 && (
              <li role="presentation" className="flex flex-col items-center gap-2 px-4 py-8 text-center text-ink-500">
                {showLoading ? <Loader2 className="size-6 animate-spin" aria-hidden /> : <SearchX className="size-6" aria-hidden />}
                <span>{showLoading ? loadingText : emptyText}</span>
              </li>
            )}
          </ul>
        </div>
      )}
    </div>
  );
}
