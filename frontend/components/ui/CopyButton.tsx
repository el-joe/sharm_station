"use client";

import { cn } from "@/lib/utils";
import { Check, Copy } from "lucide-react";
import { useTranslations } from "next-intl";
import { useEffect, useState } from "react";

export function CopyButton({ value, label, className }: { value: string; label: string; className?: string }) {
  const t = useTranslations("payment");
  const [copied, setCopied] = useState(false);
  useEffect(() => {
    if (!copied) return;
    const id = setTimeout(() => setCopied(false), 2000);
    return () => clearTimeout(id);
  }, [copied]);
  return (
    <button
      type="button"
      onClick={async () => {
        try {
          await navigator.clipboard.writeText(value);
          setCopied(true);
        } catch {
          /* clipboard blocked: the value stays selectable */
        }
      }}
      className={cn("inline-flex h-9 shrink-0 items-center gap-1.5 rounded-control border border-ink-300 bg-white px-3 text-sm font-semibold text-primary-700 hover:bg-primary-100", className)}
      aria-label={t("copyAria", { label })}
    >
      {copied ? <Check className="size-4 text-success" aria-hidden /> : <Copy className="size-4" aria-hidden />}
      <span aria-live="polite">{copied ? t("copied") : t("copy")}</span>
    </button>
  );
}
