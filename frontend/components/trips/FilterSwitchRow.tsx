"use client";

import { Switch } from "@/components/ui/Switch";
import { cn } from "@/lib/utils";
import { useId, type ReactNode } from "react";

/**
 * Figma FilterSwitchRow (226:21598), SRS 13.3 / CR-6: full-width bordered row
 * with leading icon, label, one-line helper and a 44px toggle. No bare checkbox.
 */
export function FilterSwitchRow({
  icon,
  label,
  helper,
  checked,
  onChange,
  className,
}: {
  icon: ReactNode;
  label: string;
  helper: string;
  checked: boolean;
  onChange: (checked: boolean) => void;
  className?: string;
}) {
  const id = useId();
  return (
    <div
      className={cn(
        "flex items-center gap-3 rounded-image border p-3 transition-colors",
        checked ? "border-primary-500 bg-primary-100" : "border-ink-300 bg-white",
        className,
      )}
    >
      <span className="grid size-11 shrink-0 place-items-center rounded-full bg-white text-primary-700 shadow-sm" aria-hidden>
        {icon}
      </span>
      <div className="min-w-0 flex-1">
        <label htmlFor={id} className="block cursor-pointer font-semibold text-ink-900">
          {label}
        </label>
        <p id={`${id}-helper`} className="truncate text-sm text-ink-700">
          {helper}
        </p>
      </div>
      <Switch id={id} checked={checked} onChange={onChange} describedBy={`${id}-helper`} />
    </div>
  );
}
