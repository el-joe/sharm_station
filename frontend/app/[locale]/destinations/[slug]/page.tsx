import { DestinationView } from "@/components/destinations/DestinationView";
import { getNationality } from "@/lib/api/nationality-server";
import { hasCoords } from "@/components/map/types";
import { getDestination, getDestinations, getPackages, getRooms, getTrip } from "@/lib/api/server";
import type { Locale } from "@/lib/api/types";
import { destinationGuide } from "@/lib/content/destinations";
import { pageMetadata } from "@/lib/seo";
import { SITE_URL } from "@/lib/site";
import type { Metadata } from "next";
import { notFound } from "next/navigation";
import { Suspense } from "react";

export async function generateStaticParams() {
  const list = await getDestinations("en");
  return (list.length ? list : [{ slug: "luxor" }]).map((d) => ({ slug: d.slug }));
}

export async function generateMetadata({ params }: PageProps<"/[locale]/destinations/[slug]">): Promise<Metadata> {
  const { locale, slug } = await params;
  const d = await getDestination(slug, locale as Locale);
  if (!d) return {};
  return pageMetadata({ locale, path: `/destinations/${slug}`, title: d.name, description: d.description || d.name, image: d.image });
}

export default function Page({ params }: PageProps<"/[locale]/destinations/[slug]">) {
  return (
    <Suspense fallback={<div className="h-[480px] animate-pulse bg-ink-100" aria-hidden />}>
      <Content params={params} />
    </Suspense>
  );
}

async function Content({ params }: { params: PageProps<"/[locale]/destinations/[slug]">["params"] }) {
  const { locale: l, slug } = await params;
  const locale = l as Locale;
  const nationality = await getNationality();
  const destination = await getDestination(slug, locale, nationality);
  if (!destination) notFound();
  const [rooms, packages, all, tripDetails] = await Promise.all([
    getRooms({ destination: slug }, locale, nationality),
    getPackages({ per_page: 48 }, locale, nationality),
    getDestinations(locale),
    // Trip cards carry no coordinates: the (cached) detail has the meeting point for the map.
    Promise.all(destination.trips.slice(0, 24).map((x) => getTrip(x.slug, locale, nationality).catch(() => null))),
  ]);
  const tripPlaces = tripDetails
    .filter((x): x is NonNullable<typeof x> => Boolean(x) && hasCoords(x!.meeting_point.lat, x!.meeting_point.lng))
    .map((x) => ({ slug: x.slug, title: x.title, type: x.type, lat: x.meeting_point.lat, lng: x.meeting_point.lng, price_from: x.price_from }));
  const name = destination.name.toLowerCase();
  const pkgs = packages.data.filter((p) => p.destination.slug === slug || p.route?.some((r) => r.name.toLowerCase().includes(name) || name.includes(r.name.toLowerCase())));
  const related = all.filter((d) => d.slug !== slug).slice(0, 3);
  const jsonLd = {
    "@context": "https://schema.org",
    "@type": "TouristDestination",
    name: destination.name,
    description: destination.description,
    url: `${SITE_URL}/${locale}/destinations/${slug}`,
    image: destination.image.startsWith("http") ? destination.image : `${SITE_URL}${destination.image}`,
  };
  return (
    <>
      <script type="application/ld+json" dangerouslySetInnerHTML={{ __html: JSON.stringify(jsonLd).replace(/</g, "\\u003c") }} />
      <DestinationView destination={destination} rooms={rooms.data} packages={pkgs} related={related} guide={destinationGuide(slug, locale)} tripPlaces={tripPlaces} />
    </>
  );
}
