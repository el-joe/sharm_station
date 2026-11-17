"use client";

import { Combobox, type ComboboxGroup, type ComboboxOption } from "@/components/ui/Combobox";
import type { Country } from "@/lib/api/types";
import { flagEmoji } from "@/lib/nationality-shared";
import { Search } from "lucide-react";
import { useTranslations } from "next-intl";
import { useMemo, useState } from "react";

/** Most common markets, used to fill the "Suggested" group after the detected/browser/current country. */
const POPULAR = ["IT", "EG", "DE", "GB", "FR", "SA"];

export function Flag({ code, className }: { code: string | null | undefined; className?: string }) {
  const flag = flagEmoji(code);
  if (!flag) return null;
  return (
    <span className={className} aria-hidden>
      {flag}
    </span>
  );
}

/**
 * Searchable country picker (the shared Combobox) with flag emoji: "Suggested" first, then all countries.
 * The typed text is local; the selected value is the ISO-2 code.
 */
export function NationalityCombobox({
  id,
  countries,
  value,
  onChange,
  suggestions = [],
  label,
  hideLabel,
  invalid,
  describedBy,
  required,
}: {
  id?: string;
  countries: Country[];
  value: string | null;
  onChange: (code: string | null) => void;
  /** Codes to list first (detected, browser region, current choice...). */
  suggestions?: (string | null | undefined)[];
  label: string;
  hideLabel?: boolean;
  invalid?: boolean;
  describedBy?: string;
  required?: boolean;
}) {
  const t = useTranslations("nationality");
  const selected = countries.find((c) => c.code === value) ?? null;
  // Text typed since the last selection; null shows the selected country's name.
  const [typed, setTyped] = useState<string | null>(null);
  const text = typed ?? selected?.name ?? "";

  const groups = useMemo<ComboboxGroup[]>(() => {
    const byCode = new Map(countries.map((c) => [c.code, c]));
    const suggestedCodes = [...new Set([...suggestions, ...POPULAR].filter((c): c is string => Boolean(c && byCode.has(c))))].slice(0, 6);
    const option = (c: Country): ComboboxOption => ({
      value: c.code,
      label: c.name,
      icon: <span className="text-lg leading-none">{flagEmoji(c.code)}</span>,
      keywords: [c.code, c.group.name],
    });
    return [
      { key: "suggested", label: t("suggested"), options: suggestedCodes.map((c) => option(byCode.get(c)!)) },
      { key: "all", label: t("allCountries"), options: countries.filter((c) => !suggestedCodes.includes(c.code)).map(option) },
    ];
  }, [countries, suggestions, t]);

  return (
    // Leaving the field without picking a country restores the selected country's name.
    <div
      className="min-w-0"
      // Typing replaces the selected country instead of appending to it.
      onFocus={(e) => {
        if (e.target instanceof HTMLInputElement) e.target.select();
      }}
      onBlur={(e) => {
        if (!e.currentTarget.contains(e.relatedTarget as Node | null)) setTyped(null);
      }}
    >
      <Combobox
        id={id}
        variant="field"
        label={label}
        hideLabel={hideLabel}
        groups={groups}
        value={text}
        onInputChange={setTyped}
        onSelect={(o) => {
          setTyped(null);
          onChange(o ? o.value : null);
        }}
        placeholder={t("searchPlaceholder")}
        icon={selected && typed === null ? <span className="text-lg leading-none">{flagEmoji(selected.code)}</span> : <Search aria-hidden />}
        emptyText={t("noMatch")}
        clearLabel={t("clear")}
        invalid={invalid}
        describedBy={describedBy}
        required={required}
      />
    </div>
  );
}
