"use client";

import { useNationality } from "@/lib/nationality";
import { flagEmoji } from "@/lib/nationality-shared";
import { cn } from "@/lib/utils";
import { ChevronDown, Globe, Loader2 } from "lucide-react";
import { useTranslations } from "next-intl";
import { useCallback, useRef, useState } from "react";
import { NationalityDialog } from "./NationalityDialog";
import { NationalityPopover } from "./NationalityPopover";

const DESKTOP = "(min-width: 768px)";

/**
 * Header nationality pill (Figma 250:32030): "🇮🇹 Italy ⌄", same 32 px pill as the language and
 * currency switchers. `variant="auto"` (header): anchored popover under the chip on desktop
 * (>= 768 px), bottom sheet below that; `variant="sheet"` (mobile drawer): always the bottom sheet.
 */
export function NationalityChip({ className, tone = "light", variant = "auto" }: { className?: string; tone?: "light" | "dark"; variant?: "auto" | "sheet" }) {
  const t = useTranslations("nationality");
  const { code, country, refreshing } = useNationality();
  const [open, setOpen] = useState<null | "popover" | "sheet">(null);
  const anchorRef = useRef<HTMLButtonElement>(null);
  const name = country?.name ?? code ?? null;
  const done = useCallback(() => setOpen(null), []);

  const toggle = () => {
    if (open) return setOpen(null);
    setOpen(variant === "auto" && window.matchMedia(DESKTOP).matches ? "popover" : "sheet");
  };

  return (
    <div className="relative">
      <button
        ref={anchorRef}
        type="button"
        onClick={toggle}
        aria-haspopup="dialog"
        aria-expanded={open === "popover" ? true : variant === "auto" ? false : undefined}
        aria-label={name ? t("chipLabel", { country: name }) : t("chipEmpty")}
        className={cn(
          "inline-flex h-8 max-w-44 items-center gap-1.5 rounded-pill px-3 text-sm font-semibold transition-colors",
          tone === "light" ? "bg-white text-ink-900 hover:bg-ink-100" : "bg-white/10 text-white hover:bg-white/20",
          className,
        )}
      >
        {refreshing ? (
          <Loader2 className="size-4 shrink-0 animate-spin" aria-hidden />
        ) : code ? (
          <span className="text-base leading-none" aria-hidden>
            {flagEmoji(code)}
          </span>
        ) : (
          <Globe className="size-4 shrink-0" aria-hidden />
        )}
        <span className="truncate">{code === undefined ? "" : (name ?? t("chipEmpty"))}</span>
        <ChevronDown className={cn("size-4 shrink-0 transition-transform", open === "popover" && "rotate-180")} aria-hidden />
      </button>
      {open === "popover" && <NationalityPopover anchorRef={anchorRef} onDone={done} />}
      {open === "sheet" && <NationalityDialog open mode="change" onDone={done} />}
    </div>
  );
}
