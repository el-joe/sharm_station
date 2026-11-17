"use client";

import { useNationality } from "@/lib/nationality";
import { flagEmoji } from "@/lib/nationality-shared";
import { cn } from "@/lib/utils";
import { ChevronDown, Globe, Loader2 } from "lucide-react";
import { useTranslations } from "next-intl";
import { useState } from "react";
import { NationalityDialog } from "./NationalityDialog";

/**
 * Header nationality pill (Figma 250:32030): "🇮🇹 Italy ⌄", same 32 px pill as the language and
 * currency switchers. Opens the nationality picker in "change" mode.
 */
export function NationalityChip({ className, tone = "light" }: { className?: string; tone?: "light" | "dark" }) {
  const t = useTranslations("nationality");
  const { code, country, refreshing } = useNationality();
  const [open, setOpen] = useState(false);
  const name = country?.name ?? code ?? null;

  return (
    <>
      <button
        type="button"
        onClick={() => setOpen(true)}
        aria-haspopup="dialog"
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
        <ChevronDown className="size-4 shrink-0" aria-hidden />
      </button>
      {open && <NationalityDialog open={open} mode="change" onDone={() => setOpen(false)} />}
    </>
  );
}
