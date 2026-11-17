import { Link } from "@/i18n/navigation";
import { Logo } from "@/components/ui/Logo";
import { PHONE_DISPLAY, whatsappHref } from "@/lib/site";
import { AccountMenu, FavouritesLink } from "@/components/account/AccountMenu";
import { NationalityChip } from "@/components/nationality/NationalityChip";
import { MessageCircle, Phone } from "lucide-react";
import { getTranslations } from "next-intl/server";
import { Suspense } from "react";
import { CurrencySwitcher } from "./CurrencySwitcher";
import { LanguageSwitcher, LanguageSwitcherFallback } from "./LanguageSwitcher";
import { MobileDrawer } from "./MobileDrawer";
import { NAV_ITEMS } from "./nav";

export async function Header() {
  const t = await getTranslations();
  return (
    <header className="sticky top-0 z-40 bg-white">
      <a href="#main" className="sr-only z-50 rounded-control bg-primary-700 px-4 py-2 text-white focus:not-sr-only focus:absolute focus:start-4 focus:top-2">
        {t("common.skipToContent")}
      </a>
      <div className="hidden bg-primary-700 text-white md:block">
        <div className="container-page flex h-11 items-center justify-between text-sm">
          <div className="flex items-center gap-6">
            <a href={`tel:${PHONE_DISPLAY.replace(/\s/g, "")}`} className="inline-flex items-center gap-2 hover:underline">
              <Phone className="size-4" aria-hidden /> {PHONE_DISPLAY}
            </a>
            <a href={whatsappHref()} target="_blank" rel="noopener noreferrer" className="inline-flex items-center gap-2 hover:underline">
              <MessageCircle className="size-4" aria-hidden /> {t("header.whatsapp")}
            </a>
          </div>
          <div className="flex items-center gap-1.5">
            <NationalityChip />
            <Suspense fallback={<LanguageSwitcherFallback />}>
              <LanguageSwitcher />
            </Suspense>
            <CurrencySwitcher />
          </div>
        </div>
      </div>
      <div className="border-b border-ink-100">
        <div className="container-page flex h-16 items-center justify-between gap-4 lg:h-20">
          <div className="flex items-center gap-2">
            <MobileDrawer />
            <Link href="/" aria-label={t("common.homeAria")} className="shrink-0">
              <Logo />
            </Link>
          </div>
          <nav aria-label={t("header.mainNav")} className="hidden lg:block">
            <ul className="flex items-center gap-1 xl:gap-3">
              {NAV_ITEMS.map((item) => (
                <li key={item.key}>
                  <Link href={item.href} className="rounded-control px-2.5 py-2 text-base font-semibold text-ink-900 hover:bg-ink-100 hover:text-primary-700">
                    {t(`nav.${item.key}`)}
                  </Link>
                </li>
              ))}
            </ul>
          </nav>
          <div className="flex items-center gap-1 sm:gap-2">
            <FavouritesLink />
            <AccountMenu />
          </div>
        </div>
      </div>
    </header>
  );
}
