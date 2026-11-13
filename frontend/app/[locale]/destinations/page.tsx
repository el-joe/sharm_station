import { DestinationCard } from "@/components/destinations/DestinationCard";
import { buttonClasses } from "@/components/ui/Button";
import { EmptyState } from "@/components/ui/EmptyState";
import { PageIntro } from "@/components/ui/PageIntro";
import { Link } from "@/i18n/navigation";
import { getDestinations } from "@/lib/api/server";
import type { Locale } from "@/lib/api/types";
import { pageMetadata } from "@/lib/seo";
import { MapPinned, Route } from "lucide-react";
import type { Metadata } from "next";
import { getTranslations } from "next-intl/server";
import { locale as rootLocale } from "next/root-params";

export async function generateMetadata(): Promise<Metadata> {
  const locale = await rootLocale();
  const t = await getTranslations("destinations");
  return pageMetadata({ locale, path: "/destinations", title: t("metaTitle"), description: t("subtitle"), image: "/images/luxor.webp" });
}

export default async function Page() {
  const locale = (await rootLocale()) as Locale;
  const [t, destinations] = await Promise.all([getTranslations("destinations"), getDestinations(locale)]);
  return (
    <div className="container-page pb-16">
      <PageIntro crumbs={[{ label: t("crumb") }]} title={t("title")} subtitle={t("subtitle")} />
      {destinations.length === 0 ? (
        <EmptyState icon={<MapPinned aria-hidden />} title={t("emptyTitle")} text={t("emptyText")} />
      ) : (
        <ul className="grid gap-6 sm:grid-cols-2 lg:grid-cols-3">
          {destinations.map((d, i) => (
            <li key={d.slug}>
              <DestinationCard destination={d} priority={i < 3} />
            </li>
          ))}
        </ul>
      )}
      <section className="mt-12 flex flex-col items-start gap-4 rounded-card bg-primary-700 p-6 text-white md:flex-row md:items-center md:justify-between md:p-8" aria-labelledby="route-title">
        <div className="flex items-start gap-4">
          <span className="grid size-12 shrink-0 place-items-center rounded-full bg-white/15"><Route className="size-6" aria-hidden /></span>
          <div>
            <h2 id="route-title" className="font-display text-2xl font-semibold">{t("routeTitle")}</h2>
            <p className="mt-1 text-white/85">{t("routeText")}</p>
          </div>
        </div>
        <Link href="/packages" className={buttonClasses("cta", "md")}>{t("routeCta")}</Link>
      </section>
    </div>
  );
}
