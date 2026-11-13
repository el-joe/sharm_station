"use client";

import { Link, usePathname } from "@/i18n/navigation";
import { cn } from "@/lib/utils";
import { useTranslations } from "next-intl";

const ITEMS = [
  { href: "/account/bookings", key: "bookings" },
  { href: "/account/favourites", key: "favourites" },
  { href: "/account/profile", key: "profile" },
] as const;

/** Pill navigation between account sections (Figma "Customer account"). */
export function AccountNav() {
  const t = useTranslations("account.nav");
  const pathname = usePathname();
  return (
    <nav aria-label={t("label")} className="no-scrollbar -mx-4 mt-5 overflow-x-auto px-4">
      <ul className="flex gap-2">
        {ITEMS.map((item) => {
          const active = pathname.startsWith(item.href);
          return (
            <li key={item.href}>
              <Link
                href={item.href}
                aria-current={active ? "page" : undefined}
                className={cn(
                  "inline-flex h-10 items-center rounded-pill px-4 text-sm font-semibold whitespace-nowrap",
                  active ? "bg-primary-700 text-white" : "bg-ink-100 text-ink-900 hover:bg-primary-100",
                )}
              >
                {t(item.key)}
              </Link>
            </li>
          );
        })}
      </ul>
    </nav>
  );
}
