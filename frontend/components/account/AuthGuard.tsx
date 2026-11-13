"use client";

import { buttonClasses } from "@/components/ui/Button";
import { EmptyState } from "@/components/ui/EmptyState";
import { Link, useRouter } from "@/i18n/navigation";
import { safeReturnTo, useAuth } from "@/lib/auth";
import { Briefcase, Clock, Loader2, PauseCircle, WifiOff } from "lucide-react";
import { useTranslations } from "next-intl";
import { useEffect, type ReactNode } from "react";

/**
 * Client-side protection for /account/* and /partner/*. The API enforces access
 * with the bearer token anyway; this only keeps signed-out visitors out of the UI
 * and sends them to /sign-in?returnTo=<current path>.
 */
export function AuthGuard({ children, partner = false }: { children: ReactNode; partner?: boolean }) {
  const t = useTranslations("guard");
  const { status, user, isPartner } = useAuth();
  const router = useRouter();

  useEffect(() => {
    // window.location instead of usePathname(): keeps dynamic child routes prerenderable.
    if (status === "anonymous") {
      const returnTo = safeReturnTo(window.location.pathname + window.location.search) ?? "/";
      router.replace({ pathname: "/sign-in", query: { returnTo } });
    }
  }, [status, router]);

  if (status !== "authenticated") {
    return (
      <div className="flex min-h-[40vh] items-center justify-center gap-3 text-ink-700" role="status" aria-live="polite">
        <Loader2 className="size-6 animate-spin text-primary-700" aria-hidden />
        {t("checking")}
      </div>
    );
  }

  if (!partner) return <>{children}</>;

  if (!user) {
    return (
      <EmptyState
        className="my-10"
        tone="warning"
        icon={<WifiOff aria-hidden />}
        title={t("offlineTitle")}
        text={t("offlineText")}
        action={
          <button type="button" className={buttonClasses("primary", "md")} onClick={() => window.location.reload()}>
            {t("retry")}
          </button>
        }
      />
    );
  }
  if (!isPartner) {
    return (
      <EmptyState
        className="my-10"
        tone="partner"
        icon={<Briefcase aria-hidden />}
        title={t("partnerOnlyTitle")}
        text={t("partnerOnlyText")}
        action={<Link href="/become-a-partner" className={buttonClasses("partner", "md")}>{t("becomePartner")}</Link>}
      />
    );
  }
  const status2 = user.partner?.status ?? "pending";
  if (status2 !== "active") {
    const pending = status2 === "pending";
    return (
      <EmptyState
        className="my-10"
        tone="partner"
        icon={pending ? <Clock aria-hidden /> : <PauseCircle aria-hidden />}
        title={pending ? t("pendingTitle") : t("suspendedTitle")}
        text={pending ? t("pendingText") : t("suspendedText")}
        action={<Link href="/contact" className={buttonClasses("outline", "md")}>{t("contact")}</Link>}
      />
    );
  }
  return <>{children}</>;
}
