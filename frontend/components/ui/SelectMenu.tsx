"use client";

import { cn } from "@/lib/utils";
import { Check, ChevronDown, Search } from "lucide-react";
import {
  Children,
  forwardRef,
  isValidElement,
  useCallback,
  useEffect,
  useId,
  useImperativeHandle,
  useLayoutEffect,
  useMemo,
  useRef,
  useState,
  type KeyboardEvent,
  type ReactElement,
  type ReactNode,
  type SelectHTMLAttributes,
} from "react";

interface ParsedOption {
  value: string;
  label: string;
  disabled: boolean;
}

/** Read <option> children (strings or numbers as labels) into plain data. */
function parseOptions(children: ReactNode): ParsedOption[] {
  const out: ParsedOption[] = [];
  Children.forEach(children, (child) => {
    if (!isValidElement(child) || child.type !== "option") return;
    const props = (child as ReactElement<{ value?: string | number; children?: ReactNode; disabled?: boolean }>).props;
    const label = Children.toArray(props.children).join("");
    out.push({ value: props.value === undefined ? label : String(props.value), label, disabled: Boolean(props.disabled) });
  });
  return out;
}

/** Set a native select's value and fire a real change event, so React and React Hook Form both see it. */
function commitNative(select: HTMLSelectElement, value: string) {
  const setter = Object.getOwnPropertyDescriptor(HTMLSelectElement.prototype, "value")?.set;
  setter?.call(select, value);
  select.dispatchEvent(new Event("change", { bubbles: true }));
}

export type SelectMenuProps = Omit<SelectHTMLAttributes<HTMLSelectElement>, "size"> & {
  invalid?: boolean;
  /** Visual size: "md" is the standard 48 px form control, "sm" a compact 40 px toolbar control. */
  size?: "sm" | "md";
  /** Look of the trigger: "field" (grey form control), "outline" (white with border), "ghost" (no border, e.g. on dark bars). */
  appearance?: "field" | "outline" | "ghost" | "pill";
  /** Custom trigger content for the selected option (e.g. "EN" while the menu says "English"). */
  renderValue?: (option: { value: string; label: string }) => ReactNode;
  /** Content shown before each option label in the menu (code badge, symbol...). */
  renderOptionPrefix?: (option: { value: string; label: string }) => ReactNode;
  /** Optional element before the label (icon, flag). */
  leading?: ReactNode;
  /** Show a filter box in the panel; defaults to on when there are more than 8 options. */
  searchable?: boolean;
  searchPlaceholder?: string;
  /** Align the panel to the trigger's end edge (for header switchers near the right side). */
  align?: "start" | "end";
  /** Minimum panel width in px (useful for compact triggers). */
  menuMinWidth?: number;
};

/**
 * Professional dropdown that replaces the native select UI while keeping a real (hidden) <select>
 * for forms: `name`, `value`/`defaultValue`, `onChange`, `required` and React Hook Form `register`
 * keep working unchanged. Keyboard: ↑ ↓ Home End Enter Space Esc, type a letter to jump.
 */
export const SelectMenu = forwardRef<HTMLSelectElement, SelectMenuProps>(function SelectMenu(
  {
    id: idProp,
    children,
    className,
    invalid,
    size: sizeVariant = "md",
    appearance = "field",
    leading,
    searchable,
    searchPlaceholder = "Search…",
    align = "start",
    menuMinWidth,
    renderValue,
    renderOptionPrefix,
    disabled,
    value,
    defaultValue,
    onChange,
    "aria-label": ariaLabel,
    "aria-labelledby": ariaLabelledBy,
    "aria-describedby": ariaDescribedBy,
    ...nativeProps
  },
  forwardedRef,
) {
  const autoId = useId();
  const id = idProp ?? autoId;
  const listId = `${id}-menu`;
  const nativeRef = useRef<HTMLSelectElement>(null);
  useImperativeHandle(forwardedRef, () => nativeRef.current as HTMLSelectElement);
  const rootRef = useRef<HTMLDivElement>(null);
  const buttonRef = useRef<HTMLButtonElement>(null);
  const listRef = useRef<HTMLUListElement>(null);
  const searchRef = useRef<HTMLInputElement>(null);

  const options = useMemo(() => parseOptions(children), [children]);
  const [current, setCurrent] = useState<string>(() => String(value ?? defaultValue ?? options[0]?.value ?? ""));
  const [open, setOpen] = useState(false);
  const [active, setActive] = useState(-1);
  const [filter, setFilter] = useState("");
  const typeahead = useRef({ text: "", at: 0 });

  // Mirror the native select on every render: covers controlled values, defaultValue,
  // and React Hook Form reset()/setValue(), which change the DOM value without events.
  // Runs after every render on purpose; it only sets state when the values differ, so it cannot loop.
  // eslint-disable-next-line react-hooks/exhaustive-deps
  useLayoutEffect(() => {
    const native = nativeRef.current;
    if (native && native.value !== current) setCurrent(native.value);
  });

  const showSearch = searchable ?? options.length > 8;
  const visible = useMemo(() => {
    const q = filter.trim().toLowerCase();
    return q ? options.filter((o) => o.label.toLowerCase().includes(q)) : options;
  }, [options, filter]);
  const selected = options.find((o) => o.value === current);

  const close = useCallback((focusButton = false) => {
    setOpen(false);
    setFilter("");
    setActive(-1);
    if (focusButton) buttonRef.current?.focus();
  }, []);

  const openMenu = () => {
    if (disabled) return;
    setOpen(true);
    setActive(Math.max(0, options.findIndex((o) => o.value === current)));
  };

  useEffect(() => {
    if (!open) return;
    const onPointer = (event: PointerEvent) => {
      if (!rootRef.current?.contains(event.target as Node)) close();
    };
    document.addEventListener("pointerdown", onPointer);
    if (showSearch) searchRef.current?.focus();
    return () => document.removeEventListener("pointerdown", onPointer);
  }, [open, close, showSearch]);

  useEffect(() => {
    if (open && active >= 0) listRef.current?.querySelector<HTMLElement>(`[data-index="${active}"]`)?.scrollIntoView({ block: "nearest" });
  }, [open, active]);

  const pick = (option: ParsedOption) => {
    if (option.disabled) return;
    const native = nativeRef.current;
    if (native) commitNative(native, option.value);
    setCurrent(option.value);
    close(true);
  };

  const move = (delta: number) => {
    if (!visible.length) return;
    let next = active;
    for (let step = 0; step < visible.length; step++) {
      next = (next + delta + visible.length) % visible.length;
      if (!visible[next].disabled) break;
    }
    setActive(next);
  };

  const onKeyDown = (event: KeyboardEvent<HTMLElement>) => {
    if (!open) {
      if (["ArrowDown", "ArrowUp", "Enter", " "].includes(event.key)) {
        event.preventDefault();
        openMenu();
      }
      return;
    }
    switch (event.key) {
      case "ArrowDown":
        event.preventDefault();
        move(1);
        return;
      case "ArrowUp":
        event.preventDefault();
        move(-1);
        return;
      case "Home":
        event.preventDefault();
        setActive(0);
        return;
      case "End":
        event.preventDefault();
        setActive(visible.length - 1);
        return;
      case "Enter":
        event.preventDefault();
        if (visible[active]) pick(visible[active]);
        return;
      case " ":
        if (event.target === buttonRef.current) {
          event.preventDefault();
          if (visible[active]) pick(visible[active]);
        }
        return;
      case "Escape":
        event.preventDefault();
        close(true);
        return;
      case "Tab":
        close();
        return;
    }
    // Type-ahead on the button (the search box handles its own typing).
    if (!showSearch && event.key.length === 1 && /\S/.test(event.key)) {
      const now = Date.now();
      typeahead.current = { text: (now - typeahead.current.at < 600 ? typeahead.current.text : "") + event.key.toLowerCase(), at: now };
      const hit = visible.findIndex((o) => o.label.toLowerCase().startsWith(typeahead.current.text));
      if (hit >= 0) setActive(hit);
    }
  };

  const sm = sizeVariant === "sm";

  return (
    <div ref={rootRef} className="relative" onKeyDown={onKeyDown}>
      <select
        ref={nativeRef}
        tabIndex={-1}
        aria-hidden
        className="sr-only"
        disabled={disabled}
        value={value}
        defaultValue={value === undefined ? defaultValue : undefined}
        onChange={(e) => {
          setCurrent(e.target.value);
          onChange?.(e);
        }}
        {...nativeProps}
      >
        {children}
      </select>

      <button
        ref={buttonRef}
        id={id}
        type="button"
        role="combobox"
        aria-haspopup="listbox"
        aria-expanded={open}
        aria-controls={listId}
        aria-label={ariaLabel}
        aria-labelledby={ariaLabelledBy}
        aria-describedby={ariaDescribedBy}
        aria-invalid={invalid || undefined}
        aria-activedescendant={open && !showSearch && active >= 0 ? `${id}-opt-${active}` : undefined}
        disabled={disabled}
        onClick={() => (open ? close() : openMenu())}
        className={cn(
          "flex w-full items-center gap-2 rounded-control text-start text-ink-900 transition-colors focus:outline-none focus-visible:ring-2 focus-visible:ring-primary-100 disabled:cursor-not-allowed disabled:opacity-60",
          appearance === "pill" ? "h-8 gap-1.5 rounded-pill ps-3 pe-2.5 text-sm font-semibold" : sm ? "h-10 ps-3 pe-2 text-sm" : "h-12 ps-4 pe-3 text-base",
          appearance === "field" && "border border-ink-300 bg-ink-100 hover:border-ink-500 focus-visible:border-primary-500 focus-visible:bg-white",
          appearance === "outline" && "border border-ink-300 bg-white font-semibold hover:border-ink-500 focus-visible:border-primary-500",
          appearance === "ghost" && "bg-transparent font-semibold hover:bg-ink-100",
          open && appearance !== "ghost" && "border-primary-500 bg-white ring-2 ring-primary-100",
          invalid && "border-error bg-white",
          className,
        )}
      >
        {leading && <span className="shrink-0 text-ink-700 [&_svg]:size-4">{leading}</span>}
        <span className={cn("min-w-0 flex-1 truncate", !selected?.value && appearance !== "pill" && "text-ink-500")}>
          {selected ? (renderValue ? renderValue(selected) : selected.label) : "\u00a0"}
        </span>
        <ChevronDown
          className={cn("shrink-0 transition-transform", appearance === "pill" ? "opacity-70" : "text-ink-500", sm || appearance === "pill" ? "size-4" : "size-5", open && "rotate-180")}
          aria-hidden
        />
      </button>

      {open && (
        <div
          className={cn(
            "absolute top-full z-50 mt-2 overflow-hidden rounded-image border border-ink-100 bg-white shadow-raised",
            align === "end" ? "end-0" : "start-0",
            menuMinWidth ? "" : "w-full",
          )}
          style={menuMinWidth ? { minWidth: Math.max(menuMinWidth, buttonRef.current?.offsetWidth ?? 0) } : undefined}
        >
          {showSearch && (
            <div className="border-b border-ink-100 p-2">
              <label className="flex h-10 items-center gap-2 rounded-control border border-ink-300 bg-ink-100 px-3 focus-within:border-primary-500 focus-within:bg-white">
                <Search className="size-4 text-ink-500" aria-hidden />
                <input
                  ref={searchRef}
                  value={filter}
                  onChange={(e) => {
                    setFilter(e.target.value);
                    setActive(0);
                  }}
                  placeholder={searchPlaceholder}
                  aria-label={searchPlaceholder}
                  aria-controls={listId}
                  aria-activedescendant={active >= 0 ? `${id}-opt-${active}` : undefined}
                  className="h-full min-w-0 flex-1 bg-transparent text-sm text-ink-900 placeholder:text-ink-500 focus:outline-none"
                />
              </label>
            </div>
          )}
          <ul ref={listRef} id={listId} role="listbox" aria-labelledby={ariaLabelledBy} aria-label={ariaLabelledBy ? undefined : ariaLabel} className="max-h-72 overflow-y-auto overscroll-contain p-1.5">
            {visible.map((option, index) => {
              const isSelected = option.value === current;
              return (
                <li
                  key={`${option.value}-${index}`}
                  id={`${id}-opt-${index}`}
                  data-index={index}
                  role="option"
                  aria-selected={isSelected}
                  aria-disabled={option.disabled || undefined}
                  onPointerDown={(e) => e.preventDefault()}
                  onPointerMove={() => active !== index && setActive(index)}
                  onClick={() => pick(option)}
                  className={cn(
                    "flex cursor-pointer items-center gap-3 rounded-control px-3 text-ink-900",
                    sm ? "min-h-10 text-sm" : "min-h-11",
                    active === index && "bg-primary-100",
                    isSelected && "font-semibold text-primary-700",
                    option.disabled && "cursor-not-allowed opacity-50",
                  )}
                >
                  {renderOptionPrefix && <span className="shrink-0">{renderOptionPrefix(option)}</span>}
                  <span className="min-w-0 flex-1 truncate">{option.label}</span>
                  {isSelected && <Check className="size-4 shrink-0 text-primary-700" aria-hidden />}
                </li>
              );
            })}
            {visible.length === 0 && <li className="px-3 py-6 text-center text-sm text-ink-500">—</li>}
          </ul>
        </div>
      )}
    </div>
  );
});
