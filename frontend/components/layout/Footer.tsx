import { Link } from "@/i18n/navigation";
import { Logo } from "@/components/ui/Logo";
import { Lock } from "lucide-react";
import { getTranslations } from "next-intl/server";
import { Suspense } from "react";
import { CurrencySwitcher } from "./CurrencySwitcher";
import { LanguageSwitcher, LanguageSwitcherFallback } from "./LanguageSwitcher";

const SOCIAL = [
  { label: "Facebook", short: "f", href: "https://facebook.com" },
  { label: "Instagram", short: "ig", href: "https://instagram.com" },
  { label: "YouTube", short: "yt", href: "https://youtube.com" },
  { label: "TikTok", short: "tt", href: "https://tiktok.com" },
];

export async function Footer() {
  const t = await getTranslations();
  const year = 2026;
  const explore = [
    { label: t("nav.trips"), href: "/trips" },
    { label: t("nav.nile"), href: "/nile" },
    { label: t("nav.packages"), href: "/packages" },
    { label: t("nav.accommodation"), href: "/accommodation" },
    { label: t("nav.transfers"), href: "/transfers" },
    { label: t("footer.destinations"), href: "/destinations" },
  ];
  const company = [
    { label: t("footer.about"), href: "/about" },
    { label: t("footer.contact"), href: "/contact" },
    { label: t("footer.faq"), href: "/faq" },
    { label: t("footer.blog"), href: "/blog" },
    { label: t("nav.partner"), href: "/become-a-partner" },
    { label: t("footer.reviews"), href: "/#reviews" },
  ];
  const legal = [
    { label: t("footer.privacy"), href: "/privacy" },
    { label: t("footer.terms"), href: "/booking-terms" },
    { label: t("footer.cancellation"), href: "/cancellation-policy" },
    { label: t("footer.cookiePolicy"), href: "/cookie-policy" },
  ];
  const cols = [
    { title: t("footer.explore"), links: explore },
    { title: t("footer.company"), links: company },
    { title: t("footer.legal"), links: legal },
  ];
  return (
    <footer className="bg-footer text-white">
      <div className="container-page py-12 lg:py-16">
        <div className="grid gap-10 lg:grid-cols-[1.4fr_1fr_1fr_1fr]">
          <div className="max-w-sm">
            <Logo inverted />
            <p className="mt-4 text-white/80">{t("footer.aboutText")}</p>
            <ul className="mt-6 flex gap-3">
              {SOCIAL.map((s) => (
                <li key={s.short}>
                  <a href={s.href} target="_blank" rel="noopener noreferrer" aria-label={s.label} className="grid size-11 place-items-center rounded-full border border-white/30 text-xs hover:bg-white/10">
                    {s.short}
                  </a>
                </li>
              ))}
            </ul>
          </div>
          {cols.map((col) => (
            <nav key={col.title} aria-label={col.title}>
              <h2 className="mb-4 font-semibold">{col.title}</h2>
              <ul className="flex flex-col gap-2.5">
                {col.links.map((l) => (
                  <li key={l.label}>
                    <Link href={l.href} className="text-white/80 hover:text-white hover:underline">
                      {l.label}
                    </Link>
                  </li>
                ))}
              </ul>
            </nav>
          ))}
        </div>
        <div className="mt-10 flex flex-col gap-4 border-t border-white/15 pt-6 md:flex-row md:items-center md:justify-between">
          <div className="flex flex-wrap items-center gap-2 text-sm">
            <span className="me-1 text-white/80">{t("footer.weAccept")}</span>
            {["Visa", "Mastercard", "PayPal", t("footer.bankTransfer"), "InstaPay"].map((m) => (
              <span key={m} className="rounded-control bg-white/15 px-3 py-1.5 text-xs">
                {m}
              </span>
            ))}
          </div>
          <p className="inline-flex items-center gap-2 text-sm text-white/80">
            <Lock className="size-4" aria-hidden /> {t("footer.secure")}
          </p>
        </div>
        <div className="mt-6 flex flex-col gap-4 border-t border-white/15 pt-6 md:flex-row md:items-center md:justify-between">
          <p className="text-sm text-white/80">{t("footer.copyright", { year })}</p>
          <div className="flex items-center gap-2">
            <Suspense fallback={<LanguageSwitcherFallback />}>
              <LanguageSwitcher />
            </Suspense>
            <CurrencySwitcher />
          </div>
        </div>
      </div>
    </footer>
  );
}
