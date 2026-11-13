"use client";

import { cn } from "@/lib/utils";
import { Minus, Plus } from "lucide-react";

/** Figma QuantityStepper (227:22402): 44px round buttons, value in the middle. */
export function QuantityStepper({
  value,
  onChange,
  min = 0,
  max = 99,
  label,
  decreaseLabel,
  increaseLabel,
  size = "md",
  className,
}: {
  value: number;
  onChange: (value: number) => void;
  min?: number;
  max?: number;
  label: string;
  decreaseLabel: string;
  increaseLabel: string;
  size?: "sm" | "md";
  className?: string;
}) {
  const btn = cn(
    "grid shrink-0 place-items-center rounded-full border border-primary-700 text-primary-700 transition-colors hover:bg-primary-100 disabled:border-ink-300 disabled:text-ink-300 disabled:hover:bg-transparent",
    size === "md" ? "size-11" : "size-9",
  );
  return (
    <div className={cn("flex items-center gap-3", className)} role="group" aria-label={label}>
      <button type="button" className={btn} onClick={() => onChange(Math.max(min, value - 1))} disabled={value <= min} aria-label={decreaseLabel}>
        <Minus className="size-5" aria-hidden />
      </button>
      <output className="w-6 text-center text-base font-semibold tabular-nums text-ink-900" aria-live="polite">
        {value}
      </output>
      <button type="button" className={btn} onClick={() => onChange(Math.min(max, value + 1))} disabled={value >= max} aria-label={increaseLabel}>
        <Plus className="size-5" aria-hidden />
      </button>
    </div>
  );
}
