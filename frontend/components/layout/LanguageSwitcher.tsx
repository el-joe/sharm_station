"use client";

import { usePathname, useRouter } from "@/i18n/navigation";
import { routing } from "@/i18n/routing";
import { cn } from "@/lib/utils";
import { Globe } from "lucide-react";
import { useLocale, useTranslations } from "next-intl";
import { useTransition } from "react";

const LABELS: Record<string, string> = { en: "English", it: "Italiano" };

export function LanguageSwitcher({ className, tone = "light" }: { className?: string; tone?: "light" | "dark" }) {
  const t = useTranslations("header");
  const locale = useLocale();
  const pathname = usePathname();
  const router = useRouter();
  const [pending, startTransition] = useTransition();

  return (
    <label className={cn("relative inline-flex h-9 items-center gap-1.5 rounded-pill ps-3 pe-2 text-sm font-semibold", tone === "light" ? "bg-white text-ink-900" : "bg-white/10 text-white", pending && "opacity-60", className)}>
      <Globe className="size-4" aria-hidden />
      <span className="sr-only">{t("language")}</span>
      <select
        value={locale}
        onChange={(e) => {
          const next = e.target.value;
          const search = typeof window !== "undefined" ? window.location.search : "";
          startTransition(() => router.replace(`${pathname}${search}`, { locale: next }));
        }}
        className="cursor-pointer appearance-none bg-transparent pe-1 uppercase focus:outline-none [&>option]:text-ink-900"
      >
        {routing.locales.map((l) => (
          <option key={l} value={l} lang={l} label={l.toUpperCase()}>
            {LABELS[l] ?? l}
          </option>
        ))}
      </select>
    </label>
  );
}

/** Static placeholder rendered while the pathname resolves (Cache Components). */
export function LanguageSwitcherFallback({ className, tone = "light" }: { className?: string; tone?: "light" | "dark" }) {
  return <span className={cn("inline-block h-9 w-[70px] rounded-pill", tone === "light" ? "bg-white" : "bg-white/10", className)} aria-hidden />;
}
