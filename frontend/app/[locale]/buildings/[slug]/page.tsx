import { BuildingCard } from "@/components/accommodation/BuildingCard";
import { getNationality } from "@/lib/api/nationality-server";
import { MultiRoomBooking } from "@/components/accommodation/MultiRoomBooking";
import { RoomCard } from "@/components/accommodation/RoomCard";
import { MapEmbed } from "@/components/map/MapEmbed";
import { hasCoords } from "@/components/map/types";
import { GoogleReviewsSection } from "@/components/reviews/GoogleReviewsSection";
import { AppImage } from "@/components/ui/AppImage";
import { Breadcrumbs } from "@/components/ui/Breadcrumbs";
import { getBuilding, getCatalogueKeys, getGoogleReviews, notFoundOrMoved } from "@/lib/api/server";
import type { Locale } from "@/lib/api/types";
import { pageMetadata } from "@/lib/seo";
import { MapPin } from "lucide-react";
import type { Metadata } from "next";
import { getTranslations } from "next-intl/server";
import { Suspense } from "react";

export async function generateStaticParams() {
  const keys = await getCatalogueKeys();
  return keys.buildings.map((slug) => ({ slug }));
}

export async function generateMetadata({ params }: PageProps<"/[locale]/buildings/[slug]">): Promise<Metadata> {
  const { locale, slug } = await params;
  const b = await getBuilding(slug, locale as Locale);
  if (!b) return {};
  return pageMetadata({ locale, path: `/buildings/${slug}`, title: b.name, description: b.description, image: b.image.url });
}

export default function BuildingPage({ params }: PageProps<"/[locale]/buildings/[slug]">) {
  return (
    <Suspense fallback={<div className="container-page h-[700px] animate-pulse py-10" aria-hidden />}>
      <Content params={params} />
    </Suspense>
  );
}

async function Content({ params }: { params: PageProps<"/[locale]/buildings/[slug]">["params"] }) {
  const { locale, slug } = await params;
  const b = await getBuilding(slug, locale as Locale, await getNationality());
  if (!b) return notFoundOrMoved("buildings", slug, locale as Locale);
  const [t, tc, tn, tm, reviews] = await Promise.all([getTranslations("building"), getTranslations("common"), getTranslations("nav"), getTranslations("map"), getGoogleReviews(locale as Locale)]);
  return (
    <div className="container-page py-8 lg:py-12">
      <Breadcrumbs className="text-ink-700" label={tc("breadcrumbs")} items={[{ label: tc("home"), href: "/" }, { label: tn("accommodation"), href: "/accommodation" }, { label: b.name }]} />
      <h1 className="text-h1 mt-3 text-ink-900">{b.name}</h1>
      <div className="mt-6 grid gap-8 lg:grid-cols-[1.2fr_1fr]">
        <div className="relative aspect-[16/10] overflow-hidden rounded-image">
          <AppImage src={b.image.url} alt={b.image.alt} fill preload sizes="(min-width: 1024px) 660px, 100vw" className="object-cover" />
        </div>
        <BuildingCard building={b} showLink={false} />
      </div>
      {hasCoords(b.lat, b.lng) && (
        <section className="mt-12" aria-labelledby="location-title">
          <h2 id="location-title" className="text-h3 mb-6 text-ink-900">{tm("location")}</h2>
          <MapEmbed lat={b.lat} lng={b.lng} name={b.name}>
            <p className="flex items-start gap-2 font-semibold text-ink-900"><MapPin className="mt-0.5 size-5 shrink-0 text-cta-600" aria-hidden />{b.address}</p>
          </MapEmbed>
        </section>
      )}
      <section className="mt-12" aria-labelledby="rooms-title">
        <h2 id="rooms-title" className="text-h3 mb-6 text-ink-900">{t("rooms")}</h2>
        <ul className="grid gap-6 sm:grid-cols-2 lg:grid-cols-4">
          {b.rooms.map((r) => (<li key={r.id}><RoomCard room={r} className="h-full" /></li>))}
        </ul>
      </section>
      {b.rooms.length > 0 && (
        <div className="mt-12">
          <MultiRoomBooking building={{ slug: b.slug, name: b.name, location: b.location }} rooms={b.rooms} image={b.image.url} />
        </div>
      )}
      <div className="mt-12">
        <GoogleReviewsSection data={reviews} compact limit={2} />
      </div>
    </div>
  );
}
