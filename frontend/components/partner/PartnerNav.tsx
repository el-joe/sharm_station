"use client";

import { Link, usePathname } from "@/i18n/navigation";
import { useAuth } from "@/lib/auth";
import { cn } from "@/lib/utils";
import { FileSpreadsheet, LayoutDashboard, ListOrdered, PlusCircle, Receipt, Building2 } from "lucide-react";
import { useTranslations } from "next-intl";

const ITEMS = [
  { href: "/partner", key: "dashboard", Icon: LayoutDashboard, exact: true },
  { href: "/partner/bookings", key: "bookings", Icon: ListOrdered },
  { href: "/trips", key: "newBooking", Icon: PlusCircle },
  { href: "/partner/statements", key: "statements", Icon: Receipt },
  { href: "/partner/price-list", key: "priceList", Icon: FileSpreadsheet },
  { href: "/partner/profile", key: "profile", Icon: Building2 },
] as const;

/** Partner panel navigation (SRS 9.3): sidebar on desktop, scrollable pills on mobile. */
export function PartnerNav() {
  const t = useTranslations("partnerPanel.nav");
  const pathname = usePathname();
  const { user } = useAuth();
  return (
    <nav aria-label={t("label")} className="min-w-0 lg:sticky lg:top-32 lg:self-start">
      {user?.partner?.company && <p className="mb-3 hidden truncate px-3 text-eyebrow text-partner-600 lg:block">{user.partner.company}</p>}
      <ul className="no-scrollbar -mx-4 flex gap-2 overflow-x-auto px-4 lg:mx-0 lg:flex-col lg:gap-1 lg:overflow-visible lg:px-0">
        {ITEMS.map(({ href, key, Icon, ...rest }) => {
          const exact = "exact" in rest && rest.exact;
          const active = exact ? pathname === href : pathname.startsWith(href) && href !== "/trips";
          return (
            <li key={key} className="shrink-0">
              <Link
                href={href}
                aria-current={active ? "page" : undefined}
                className={cn(
                  "flex h-10 items-center gap-2 rounded-pill px-4 text-sm font-semibold whitespace-nowrap lg:h-11 lg:rounded-control lg:px-3 lg:text-base",
                  active ? "bg-partner-600 text-white" : "bg-ink-100 text-ink-900 hover:bg-partner-100 lg:bg-transparent",
                )}
              >
                <Icon className="size-5 shrink-0" aria-hidden />
                {t(key)}
              </Link>
            </li>
          );
        })}
      </ul>
    </nav>
  );
}
