"use client";

import { buttonClasses } from "@/components/ui/Button";
import { allowGoogleMapsOnce } from "@/lib/consent";
import { cn } from "@/lib/utils";
import { MapPin } from "lucide-react";
import { useTranslations } from "next-intl";

/**
 * Click-to-load placeholder shown instead of Google Maps until the visitor accepts
 * non-essential cookies ("Accept all") or clicks "Show map" (valid for this page view).
 * Also used, without the button, while the consent is read during hydration.
 */
export function MapConsentPlaceholder({ pending = false, className }: { pending?: boolean; className?: string }) {
  const t = useTranslations("map");
  return (
    <div
      data-map-placeholder
      className={cn(
        "flex size-full flex-col items-center justify-center gap-3 bg-[linear-gradient(90deg,var(--color-ink-100)_1px,transparent_1px),linear-gradient(var(--color-ink-100)_1px,transparent_1px)] bg-[size:48px_48px] bg-sand-50 p-6 text-center",
        className,
      )}
    >
      <span className="grid size-11 place-items-center rounded-full bg-white text-cta-600 shadow-card">
        <MapPin className="size-5" aria-hidden />
      </span>
      {pending ? (
        <p className="text-sm text-ink-500">{t("loading")}</p>
      ) : (
        <>
          <p className="max-w-sm text-sm text-ink-700">{t("consentText")}</p>
          <button type="button" onClick={allowGoogleMapsOnce} className={buttonClasses("outline", "sm", "h-11 whitespace-normal")}>
            {t("showMap")}
          </button>
        </>
      )}
    </div>
  );
}
