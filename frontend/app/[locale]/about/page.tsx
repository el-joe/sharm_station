import { StaticDoc } from "@/components/content/StaticDoc";
import { AppImage } from "@/components/ui/AppImage";
import { buttonClasses } from "@/components/ui/Button";
import { Link } from "@/i18n/navigation";
import { getPage } from "@/lib/api/content";
import type { Locale } from "@/lib/api/types";
import { pageMetadata } from "@/lib/seo";
import type { Metadata } from "next";
import { getTranslations } from "next-intl/server";
import { notFound } from "next/navigation";
import { locale as rootLocale } from "next/root-params";

export async function generateMetadata(): Promise<Metadata> {
  const locale = (await rootLocale()) as Locale;
  const doc = await getPage("about", locale);
  return pageMetadata({ locale, path: "/about", title: (await getTranslations("about"))("metaTitle"), description: doc?.seo.description ?? "", image: "/images/nile-cruise.webp" });
}

export default async function Page() {
  const locale = (await rootLocale()) as Locale;
  const [t, tn, doc] = await Promise.all([getTranslations("about"), getTranslations("nav"), getPage("about", locale)]);
  if (!doc) notFound();
  return (
    <>
      <div className="container-page">
        <div className="relative mt-6 aspect-[16/7] overflow-hidden rounded-card bg-ink-100 md:aspect-[16/5]">
          <AppImage src="/images/aswan.webp" alt="" fill preload sizes="(min-width: 1248px) 1200px, 100vw" className="object-cover" />
        </div>
      </div>
      <StaticDoc
        doc={doc}
        crumb={t("crumb")}
        after={
          <div className="mt-6 grid gap-4 sm:grid-cols-2">
            <div className="relative aspect-[4/3] overflow-hidden rounded-card bg-ink-100">
              <AppImage src="/images/luxor.webp" alt="" fill sizes="(min-width: 640px) 380px, 100vw" className="object-cover" />
            </div>
            <div className="flex flex-col justify-center gap-3 rounded-card bg-partner-100 p-6">
              <p className="font-display text-xl font-semibold text-ink-900">{tn("partner")}</p>
              <Link href="/become-a-partner" className={buttonClasses("partner", "md", "self-start")}>{tn("partner")}</Link>
            </div>
          </div>
        }
      />
    </>
  );
}
