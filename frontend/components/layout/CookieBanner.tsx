"use client";

import { Button } from "@/components/ui/Button";
import { Link } from "@/i18n/navigation";
import { useTranslations } from "next-intl";
import { saveConsent, useConsent } from "@/lib/consent";

export function CookieBanner() {
  const t = useTranslations("cookies");
  // Server snapshot "pending" keeps the banner out of the prerendered HTML.
  const consent = useConsent();

  if (consent !== null) return null;

  const save = saveConsent;

  return (
    <div role="region" aria-label={t("title")} className="fixed inset-x-3 bottom-3 z-50 rounded-card border border-ink-100 bg-white p-4 shadow-raised md:inset-x-auto md:end-6 md:bottom-6 md:max-w-md md:p-5">
      <h2 className="font-semibold text-ink-900">{t("title")}</h2>
      <p className="mt-1 text-sm text-ink-700">
        {t("text")}{" "}
        <Link href="/cookie-policy" className="font-medium text-primary-700 underline">
          {t("policy")}
        </Link>
      </p>
      <div className="mt-4 flex flex-col gap-2 sm:flex-row">
        <Button variant="outline" size="sm" className="h-11 flex-1" onClick={() => save("essential")}>
          {t("essential")}
        </Button>
        <Button variant="primary" size="sm" className="h-11 flex-1" onClick={() => save("all")}>
          {t("acceptAll")}
        </Button>
      </div>
    </div>
  );
}
