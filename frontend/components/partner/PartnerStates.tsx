"use client";

import { buttonClasses } from "@/components/ui/Button";
import { EmptyState } from "@/components/ui/EmptyState";
import { ApiError } from "@/lib/api/client";
import { Lock } from "lucide-react";
import { useTranslations } from "next-intl";
import type { ReactNode } from "react";

/** Loading / error / forbidden wrapper for partner endpoints. */
export function PartnerData<T>({
  query,
  children,
  skeleton = "h-64",
  forbiddenText,
}: {
  query: { isPending: boolean; isError: boolean; error: unknown; data?: T; refetch: () => unknown };
  children: (data: T) => ReactNode;
  skeleton?: string;
  forbiddenText?: string;
}) {
  const t = useTranslations("partnerPanel");
  if (query.isPending) return <div className={`animate-pulse rounded-card bg-ink-100 ${skeleton}`} aria-busy="true" />;
  if (query.isError || query.data === undefined) {
    if (query.error instanceof ApiError && query.error.status === 403) return <EmptyState tone="partner" icon={<Lock aria-hidden />} title={forbiddenText ?? t("forbidden")} />;
    return (
      <EmptyState
        tone="warning"
        title={t("loadError")}
        action={
          <button type="button" className={buttonClasses("primary", "md")} onClick={() => query.refetch()}>
            {t("retry")}
          </button>
        }
      />
    );
  }
  return <>{children(query.data)}</>;
}
