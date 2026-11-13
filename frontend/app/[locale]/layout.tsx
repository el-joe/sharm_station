import "../globals.css";
import { CookieBanner } from "@/components/layout/CookieBanner";
import { Footer } from "@/components/layout/Footer";
import { Header } from "@/components/layout/Header";
import { NewsletterBand } from "@/components/layout/NewsletterBand";
import { Providers } from "@/components/layout/Providers";
import { WhatsAppFab } from "@/components/layout/WhatsAppFab";
import { getDirection, routing } from "@/i18n/routing";
import { getMeta } from "@/lib/api/server";
import type { Locale } from "@/lib/api/types";
import { SITE_NAME, SITE_URL } from "@/lib/site";
import type { Metadata, Viewport } from "next";
import { hasLocale, NextIntlClientProvider } from "next-intl";
import { getTranslations } from "next-intl/server";
import { Fraunces, Inter } from "next/font/google";
import { notFound } from "next/navigation";
import { locale as rootLocale } from "next/root-params";

const inter = Inter({ subsets: ["latin"], variable: "--font-inter", display: "swap" });
const fraunces = Fraunces({ subsets: ["latin"], variable: "--font-fraunces", weight: ["600"], display: "swap" });

export function generateStaticParams() {
  return routing.locales.map((locale) => ({ locale }));
}

export const viewport: Viewport = {
  themeColor: "#101a5c",
  width: "device-width",
  initialScale: 1,
};

export async function generateMetadata(): Promise<Metadata> {
  const t = await getTranslations("meta");
  return {
    metadataBase: new URL(SITE_URL),
    title: { default: t("defaultTitle"), template: `%s | ${SITE_NAME}` },
    description: t("defaultDescription"),
    applicationName: SITE_NAME,
    formatDetection: { telephone: false },
  };
}

export default async function LocaleLayout({ children }: LayoutProps<"/[locale]">) {
  const locale = await rootLocale();
  if (!hasLocale(routing.locales, locale)) notFound();
  const meta = await getMeta(locale as Locale);

  return (
    <html lang={locale} dir={getDirection(locale)} className={`${inter.variable} ${fraunces.variable}`}>
      <body className="flex min-h-dvh flex-col">
        <NextIntlClientProvider>
          <Providers currencies={meta.currencies}>
            <Header />
            <main id="main" className="flex-1">
              {children}
            </main>
            <NewsletterBand />
            <Footer />
            <WhatsAppFab />
            <CookieBanner />
          </Providers>
        </NextIntlClientProvider>
      </body>
    </html>
  );
}
