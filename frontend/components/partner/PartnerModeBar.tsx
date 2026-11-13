"use client";

import { Link } from "@/i18n/navigation";
import { useAuth } from "@/lib/auth";
import { ArrowRight, Briefcase } from "lucide-react";
import { useTranslations } from "next-intl";

/** Indigo bar under the header while an active partner is signed in (SRS 9.2, partner mode). */
export function PartnerModeBar() {
  const t = useTranslations("partnerBar");
  const { isActivePartner, user } = useAuth();
  if (!isActivePartner) return null;
  const company = user?.partner?.company;
  return (
    <div className="bg-partner-600 text-white" role="region" aria-label={t("label")}>
      <div className="container-page flex min-h-10 flex-wrap items-center justify-between gap-x-4 gap-y-1 py-1.5 text-sm">
        <p className="flex items-center gap-2">
          <Briefcase className="size-4 shrink-0" aria-hidden />
          <span className="rounded-pill bg-white/15 px-2 py-0.5 text-xs font-semibold uppercase tracking-wide">{t("label")}</span>
          <span>{company ? t("text", { company }) : t("textNoCompany")}</span>
        </p>
        <Link href="/partner" className="inline-flex items-center gap-1 font-semibold underline-offset-2 hover:underline">
          {t("panel")}
          <ArrowRight className="size-4 rtl:rotate-180" aria-hidden />
        </Link>
      </div>
    </div>
  );
}
