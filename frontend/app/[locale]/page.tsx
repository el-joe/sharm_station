import { CategoryTiles } from "@/components/home/CategoryTiles";
import { DestinationGrid } from "@/components/home/DestinationGrid";
import { FeaturedTrips } from "@/components/home/FeaturedTrips";
import { HomeHero } from "@/components/home/HomeHero";
import { NileBanner } from "@/components/home/NileBanner";
import { PackageRouteBand } from "@/components/home/PackageRouteBand";
import { TransfersSection } from "@/components/home/TransfersSection";
import { WhyBookWithUs } from "@/components/home/WhyBookWithUs";
import { RoomCard } from "@/components/accommodation/RoomCard";
import { GoogleReviewsSection } from "@/components/reviews/GoogleReviewsSection";
import { TripCard } from "@/components/trips/TripCard";
import { SectionHeader } from "@/components/ui/SectionHeader";
import { getHome, getTransferCatalog } from "@/lib/api/server";
import type { Locale } from "@/lib/api/types";
import { pageMetadata } from "@/lib/seo";
import { SITE_NAME, SITE_URL } from "@/lib/site";
import type { Metadata } from "next";
import { getTranslations } from "next-intl/server";
import { locale as rootLocale } from "next/root-params";

export async function generateMetadata(): Promise<Metadata> {
  const locale = await rootLocale();
  const t = await getTranslations("meta");
  return pageMetadata({ locale, path: "/", title: t("homeTitle"), description: t("defaultDescription"), image: "/images/cairo.webp" });
}

export default async function HomePage() {
  const locale = (await rootLocale()) as Locale;
  const [home, t] = await Promise.all([getHome(locale), getTranslations("home")]);
  const catalog = await getTransferCatalog(locale);
  const hubs = catalog.hubs;
  const hotels = [...new Map(home.rooms.map((r) => [r.building.slug, { slug: r.building.slug, name: r.building.name, stars: r.building.stars, city: r.building.location }])).values()];
  const classic = home.packages[0];
  const cruise = home.nile.find((n) => (n.days ?? 0) > 1) ?? home.nile[0];

  const jsonLd = {
    "@context": "https://schema.org",
    "@type": "TravelAgency",
    name: SITE_NAME,
    url: `${SITE_URL}/${locale}`,
    image: `${SITE_URL}/images/cairo.webp`,
    telephone: "+201234567890",
    address: { "@type": "PostalAddress", addressLocality: "Sharm El Sheikh", addressCountry: "EG" },
  };

  return (
    <>
      <script type="application/ld+json" dangerouslySetInnerHTML={{ __html: JSON.stringify(jsonLd).replace(/</g, "\\u003c") }} />
      <HomeHero destinations={home.destinations} hubs={hubs} hotels={hotels} rating={home.hero_stats.rating} />
      <CategoryTiles />
      <DestinationGrid destinations={home.destinations} />

      <section className="section-y" aria-labelledby="featured-title">
        <div className="container-page">
          <div id="featured-title">
            <SectionHeader title={t("featured.title")} subtitle={t("featured.subtitle")} link={{ href: "/trips", label: t("featured.all") }} />
          </div>
          <FeaturedTrips trips={home.featured_trips} />
        </div>
      </section>

      <section className="section-y bg-sand-50" aria-labelledby="stay-title">
        <div className="container-page">
          <div id="stay-title">
            <SectionHeader title={t("stay.title")} subtitle={t("stay.subtitle")} link={{ href: "/accommodation", label: t("stay.all") }} />
          </div>
          <ul className="grid gap-6 sm:grid-cols-2 lg:grid-cols-4">
            {home.rooms.slice(0, 4).map((room) => (
              <li key={room.id}>
                <RoomCard room={room} nights={3} className="h-full" />
              </li>
            ))}
          </ul>
        </div>
      </section>

      <NileBanner cruise={cruise} />

      <section className="section-y" aria-labelledby="packages-title">
        <div className="container-page">
          <div id="packages-title">
            <SectionHeader title={t("packages.title")} subtitle={t("packages.subtitle")} link={{ href: "/packages", label: t("packages.all") }} />
          </div>
          {classic?.route && (
            <PackageRouteBand
              stops={classic.route.map((r, i) => ({ name: r.name, text: t(`packages.stop${Math.min(i, 2)}` as "packages.stop0", { days: r.days }) }))}
            />
          )}
          <ul className="grid gap-6 md:grid-cols-2 lg:grid-cols-3">
            {home.packages.slice(0, 3).map((p) => (
              <li key={p.slug}>
                <TripCard trip={p} className="h-full" />
              </li>
            ))}
          </ul>
        </div>
      </section>

      <TransfersSection catalog={catalog} />
      <WhyBookWithUs />
      <GoogleReviewsSection data={home.google_reviews} />
    </>
  );
}
