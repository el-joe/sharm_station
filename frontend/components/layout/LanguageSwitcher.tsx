"use client";

import { getPathname, usePathname, useRouter } from "@/i18n/navigation";
import { mapsNeedReloadFor } from "@/lib/maps/google";
import { routing } from "@/i18n/routing";
import { SelectMenu } from "@/components/ui/SelectMenu";
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
    <SelectMenu
      aria-label={t("language")}
      appearance="pill"
      align="end"
      menuMinWidth={190}
      value={locale}
      onChange={(e) => {
        const next = e.target.value;
        const search = typeof window !== "undefined" ? window.location.search : "";
        // A Google Maps JS instance keeps the language it was loaded with: switch with a full page
        // load in that case (only then), so maps and place suggestions come back in the new language.
        if (mapsNeedReloadFor(next)) {
          // eslint-disable-next-line @next/next/no-location-assign-relative-destination -- full load is intended (Maps JS language)
          window.location.assign(`${getPathname({ href: pathname, locale: next })}${search}${window.location.hash}`);
          return;
        }
        startTransition(() => router.replace(`${pathname}${search}`, { locale: next }));
      }}
      leading={<Globe aria-hidden />}
      renderValue={(o) => o.value.toUpperCase()}
      renderOptionPrefix={(o) => (
        <span className="grid h-7 min-w-9 place-items-center rounded-control bg-ink-100 px-1.5 text-xs font-bold uppercase text-ink-700">{o.value}</span>
      )}
      className={cn(tone === "light" ? "bg-white text-ink-900 hover:bg-ink-100 [&_svg]:text-current" : "bg-white/10 text-white hover:bg-white/20 [&_svg]:text-current", pending && "opacity-60", className)}
    >
      {routing.locales.map((l) => (
        <option key={l} value={l}>
          {LABELS[l] ?? l}
        </option>
      ))}
    </SelectMenu>
  );
}

/** Static placeholder rendered while the pathname resolves (Cache Components). */
export function LanguageSwitcherFallback({ className, tone = "light" }: { className?: string; tone?: "light" | "dark" }) {
  return <span className={cn("inline-block h-8 w-[76px] rounded-pill", tone === "light" ? "bg-white" : "bg-white/10", className)} aria-hidden />;
}
