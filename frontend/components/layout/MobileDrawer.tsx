"use client";

import { Link } from "@/i18n/navigation";
import { buttonClasses } from "@/components/ui/Button";
import { Menu, X } from "lucide-react";
import { useTranslations } from "next-intl";
import { Suspense, useEffect, useRef, useState } from "react";
import { CurrencySwitcher } from "./CurrencySwitcher";
import { LanguageSwitcher, LanguageSwitcherFallback } from "./LanguageSwitcher";
import { DRAWER_EXTRA, NAV_ITEMS } from "./nav";
import { useAuth } from "@/lib/auth";
import { Logo } from "@/components/ui/Logo";

export function MobileDrawer() {
  const t = useTranslations();
  const [open, setOpen] = useState(false);
  const { status, isPartner } = useAuth();
  const ref = useRef<HTMLDialogElement>(null);

  useEffect(() => {
    const d = ref.current;
    if (!d) return;
    if (open && !d.open) d.showModal();
    if (!open && d.open) d.close();
  }, [open]);

  const close = () => setOpen(false);

  return (
    <>
      <button type="button" className="grid size-11 place-items-center rounded-full text-ink-900 hover:bg-ink-100 lg:hidden" onClick={() => setOpen(true)} aria-label={t("header.openMenu")} aria-expanded={open}>
        <Menu className="size-6" aria-hidden />
      </button>
      <dialog
        ref={ref}
        onClose={close}
        onClick={(e) => e.target === ref.current && close()}
        aria-label={t("header.menu")}
        className="fixed inset-y-0 start-0 m-0 h-dvh max-h-none w-[min(88vw,360px)] max-w-none bg-white p-0 text-ink-900 backdrop:bg-brand-navy-900/50 open:animate-fade-in [&:not([open])]:hidden"
      >
        <div className="flex h-full flex-col">
          <div className="flex items-center justify-between border-b border-ink-100 px-4 py-3">
            <Logo />
            <button type="button" onClick={close} className="grid size-11 place-items-center rounded-full hover:bg-ink-100" aria-label={t("common.close")}>
              <X className="size-6" aria-hidden />
            </button>
          </div>
          <nav aria-label={t("header.mainNav")} className="flex-1 overflow-y-auto px-2 py-4">
            <ul className="flex flex-col">
              {[...NAV_ITEMS, ...DRAWER_EXTRA].map((item) => (
                <li key={item.key}>
                  <Link href={item.href} onClick={close} className="flex min-h-12 items-center rounded-control px-3 text-lg font-semibold hover:bg-ink-100">
                    {t(`nav.${item.key}`)}
                  </Link>
                </li>
              ))}
            </ul>
          </nav>
          <div className="flex flex-col gap-4 border-t border-ink-100 p-4">
            <div className="flex items-center gap-2">
              <Suspense fallback={<LanguageSwitcherFallback className="border border-ink-300" />}>
                <LanguageSwitcher className="border border-ink-300" />
              </Suspense>
              <CurrencySwitcher className="border border-ink-300" />
            </div>
            {status === "authenticated" ? (
              <div className="grid grid-cols-2 gap-2">
                <Link href="/account/bookings" onClick={close} className={buttonClasses("outline", "md", "w-full px-2")}>
                  {t("header.myBookings")}
                </Link>
                {isPartner ? (
                  <Link href="/partner" onClick={close} className={buttonClasses("partner", "md", "w-full px-2")}>
                    {t("header.partnerPanel")}
                  </Link>
                ) : (
                  <Link href="/account/profile" onClick={close} className={buttonClasses("outline", "md", "w-full px-2")}>
                    {t("header.profile")}
                  </Link>
                )}
              </div>
            ) : (
              <Link href="/sign-in" onClick={close} className={buttonClasses("outline", "md", "w-full")}>
                {t("header.signIn")}
              </Link>
            )}
          </div>
        </div>
      </dialog>
    </>
  );
}
