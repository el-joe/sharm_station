"use client";

import { Link, usePathname } from "@/i18n/navigation";
import { useAuth } from "@/lib/auth";
import { cn } from "@/lib/utils";
import { Building2, CalendarCheck, FileText, LayoutDashboard, PlusCircle, Tag } from "lucide-react";
import { useTranslations } from "next-intl";

const ITEMS = [
  { href: "/partner", key: "dashboard", Icon: LayoutDashboard, exact: true },
  { href: "/partner/new", key: "newBooking", Icon: PlusCircle },
  { href: "/partner/bookings", key: "bookings", Icon: CalendarCheck },
  { href: "/partner/statements", key: "statements", Icon: FileText },
  { href: "/partner/price-list", key: "priceList", Icon: Tag },
  { href: "/partner/profile", key: "profile", Icon: Building2 },
] as const;

/** Partner panel navigation (Figma 254:35008): company card + sidebar on desktop, scrollable pills on mobile. */
export function PartnerNav() {
  const t = useTranslations("partnerPanel.nav");
  const pathname = usePathname();
  const { user } = useAuth();
  const company = user?.partner?.company ?? "";
  const initials = company
    .split(/\s+/)
    .filter(Boolean)
    .slice(0, 2)
    .map((w) => w[0]?.toUpperCase())
    .join("");

  return (
    <nav aria-label={t("label")} className="min-w-0 lg:sticky lg:top-32 lg:self-start lg:rounded-card lg:border lg:border-ink-100 lg:bg-white lg:p-3 lg:shadow-card">
      {company && (
        <div className="mb-2 hidden items-center gap-3 border-b border-ink-100 px-2 pb-3 pt-1 lg:flex">
          <span className="grid size-10 shrink-0 place-items-center rounded-full bg-partner-100 text-sm font-bold text-partner-600" aria-hidden>
            {initials}
          </span>
          <span className="min-w-0">
            <span className="block truncate font-semibold text-ink-900">{company}</span>
            <span className="block truncate text-xs text-ink-500">{t("role")}</span>
          </span>
        </div>
      )}
      <ul className="no-scrollbar -mx-4 flex gap-2 overflow-x-auto px-4 lg:mx-0 lg:flex-col lg:gap-0.5 lg:overflow-visible lg:px-0">
        {ITEMS.map(({ href, key, Icon, ...rest }) => {
          const exact = "exact" in rest && rest.exact;
          const active = exact ? pathname === href : pathname === href || pathname.startsWith(`${href}/`);
          return (
            <li key={key} className="shrink-0">
              <Link
                href={href}
                aria-current={active ? "page" : undefined}
                className={cn(
                  "flex h-10 items-center gap-2 rounded-pill px-4 text-sm font-semibold whitespace-nowrap lg:h-11 lg:rounded-control lg:px-3",
                  active ? "bg-partner-600 text-white lg:bg-partner-100 lg:text-partner-600" : "bg-white text-ink-900 hover:bg-partner-100 lg:bg-transparent",
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
