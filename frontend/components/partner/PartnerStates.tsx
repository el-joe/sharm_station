"use client";

import { buttonClasses } from "@/components/ui/Button";
import { EmptyState } from "@/components/ui/EmptyState";
import { Link } from "@/i18n/navigation";
import { ApiError } from "@/lib/api/client";
import { Construction, Lock } from "lucide-react";
import { useTranslations } from "next-intl";
import type { ReactNode } from "react";

export function PartnerComingSoon({ text }: { text?: string }) {
  const t = useTranslations("partnerPanel");
  return (
    <EmptyState
      tone="partner"
      icon={<Construction aria-hidden />}
      title={t("comingSoonTitle")}
      text={text ?? t("comingSoonText")}
      action={<Link href="/trips" className={buttonClasses("partner", "md")}>{t("dashboard.startBooking")}</Link>}
    />
  );
}

/** Loading / error / unavailable wrapper for planned partner endpoints. */
export function PartnerData<T>({
  query,
  children,
  skeleton = "h-64",
  forbiddenText,
}: {
  query: { isPending: boolean; isError: boolean; error: unknown; data?: { status: "ok"; data: T } | { status: "unavailable" }; refetch: () => unknown };
  children: (data: T) => ReactNode;
  skeleton?: string;
  forbiddenText?: string;
}) {
  const t = useTranslations("partnerPanel");
  if (query.isPending) return <div className={`animate-pulse rounded-card bg-ink-100 ${skeleton}`} aria-busy="true" />;
  if (query.isError) {
    if (query.error instanceof ApiError && query.error.status === 403) return <EmptyState tone="partner" icon={<Lock aria-hidden />} title={forbiddenText ?? t("noData")} />;
    return (
      <EmptyState
        tone="warning"
        title={t("noData")}
        action={
          <button type="button" className={buttonClasses("primary", "md")} onClick={() => query.refetch()}>
            {t("retry")}
          </button>
        }
      />
    );
  }
  if (!query.data || query.data.status === "unavailable") return <PartnerComingSoon />;
  return <>{children(query.data.data)}</>;
}
