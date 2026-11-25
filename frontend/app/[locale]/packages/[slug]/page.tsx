import { TripDetailSkeleton, TripDetailView } from "@/components/trips/TripDetailView";
import { getNationality } from "@/lib/api/nationality-server";
import { getCatalogueKeys, getPackage, notFoundOrMoved } from "@/lib/api/server";
import type { Locale } from "@/lib/api/types";
import { pageMetadata } from "@/lib/seo";
import type { Metadata } from "next";
import { notFound } from "next/navigation";
import { Suspense } from "react";

export async function generateStaticParams() {
  const keys = await getCatalogueKeys();
  return keys.packages.map((slug) => ({ slug }));
}

export async function generateMetadata({ params }: PageProps<"/[locale]/packages/[slug]">): Promise<Metadata> {
  const { locale, slug } = await params;
  const trip = await getPackage(slug, locale as Locale);
  if (!trip) return {};
  return pageMetadata({
    locale,
    path: `/packages/${slug}`,
    title: trip.seo?.title ?? trip.title,
    description: trip.seo?.description ?? trip.summary,
    image: trip.image.url,
  });
}

export default function Page({ params }: PageProps<"/[locale]/packages/[slug]">) {
  return (
    <Suspense fallback={<TripDetailSkeleton />}>
      <Content params={params} />
    </Suspense>
  );
}

async function Content({ params }: { params: PageProps<"/[locale]/packages/[slug]">["params"] }) {
  const { locale, slug } = await params;
  const nationality = await getNationality();
  const trip = await getPackage(slug, locale as Locale, nationality);
  if (!trip) return notFoundOrMoved("packages", slug, locale as Locale);
  if (!(trip.type === "package")) notFound();
  return <TripDetailView trip={trip} locale={locale as Locale} nationality={nationality} />;
}
