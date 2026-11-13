"use client";

import { cn } from "@/lib/utils";

/** 44px-wide accessible toggle (role="switch"). */
export function Switch({
  checked,
  onChange,
  id,
  label,
  className,
  describedBy,
}: {
  checked: boolean;
  onChange: (checked: boolean) => void;
  id?: string;
  label?: string;
  className?: string;
  describedBy?: string;
}) {
  return (
    <button
      type="button"
      role="switch"
      id={id}
      aria-checked={checked}
      aria-label={label}
      aria-describedby={describedBy}
      onClick={() => onChange(!checked)}
      className={cn(
        "relative inline-flex h-7 w-12 shrink-0 items-center rounded-pill transition-colors",
        checked ? "bg-primary-700" : "bg-ink-300",
        className,
      )}
    >
      <span className={cn("inline-block size-5 rounded-full bg-white shadow transition-transform", checked ? "translate-x-6 rtl:-translate-x-6" : "translate-x-1 rtl:-translate-x-1")} />
    </button>
  );
}
