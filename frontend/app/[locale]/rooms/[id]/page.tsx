import { BuildingCard } from "@/components/accommodation/BuildingCard";
import { RoomBookingWidget } from "@/components/accommodation/RoomBookingWidget";
import { RoomCard } from "@/components/accommodation/RoomCard";
import { SeasonPriceTable } from "@/components/booking/SeasonPriceTable";
import { DetailSection } from "@/components/trips/DetailSection";
import { TripGallery } from "@/components/trips/TripGallery";
import { Badge, BadgeIcons } from "@/components/ui/Badge";
import { Breadcrumbs } from "@/components/ui/Breadcrumbs";
import { getCatalogueKeys, getRoom } from "@/lib/api/server";
import type { Locale } from "@/lib/api/types";
import { pageMetadata } from "@/lib/seo";
import { SITE_URL } from "@/lib/site";
import { BedDouble, Maximize, Users } from "lucide-react";
import type { Metadata } from "next";
import { getTranslations } from "next-intl/server";
import { notFound } from "next/navigation";
import { Suspense } from "react";

export async function generateStaticParams() {
  const keys = await getCatalogueKeys();
  return keys.rooms.map((id) => ({ id: String(id) }));
}

export async function generateMetadata({ params }: PageProps<"/[locale]/rooms/[id]">): Promise<Metadata> {
  const { locale, id } = await params;
  const room = await getRoom(Number(id), locale as Locale);
  if (!room) return {};
  return pageMetadata({ locale, path: `/rooms/${id}`, title: `${room.name} · ${room.building.name}`, description: room.description, image: room.image.url });
}

export default function RoomPage({ params }: PageProps<"/[locale]/rooms/[id]">) {
  return (
    <Suspense fallback={<div className="container-page h-[800px] animate-pulse py-10" aria-hidden />}>
      <RoomContent params={params} />
    </Suspense>
  );
}

async function RoomContent({ params }: { params: PageProps<"/[locale]/rooms/[id]">["params"] }) {
  const { locale, id } = await params;
  const room = Number.isInteger(Number(id)) ? await getRoom(Number(id), locale as Locale) : null;
  if (!room) notFound();
  const [t, tc, tn] = await Promise.all([getTranslations("roomPage"), getTranslations("common"), getTranslations("nav")]);

  const jsonLd = {
    "@context": "https://schema.org",
    "@type": "HotelRoom",
    name: room.name,
    description: room.description,
    image: room.gallery.map((g) => (g.url.startsWith("http") ? g.url : `${SITE_URL}${g.url}`)),
    bed: room.bed_label,
    occupancy: { "@type": "QuantitativeValue", maxValue: room.max_total },
    containedInPlace: { "@type": "Hotel", name: room.building.name, address: room.building_detail.address, starRating: { "@type": "Rating", ratingValue: room.building.stars } },
  };

  return (
    <>
      <script type="application/ld+json" dangerouslySetInnerHTML={{ __html: JSON.stringify(jsonLd).replace(/</g, "\\u003c") }} />
      <div className="container-page pb-28 pt-6 lg:pb-16 lg:pt-10">
        <Breadcrumbs className="text-ink-700" label={tc("breadcrumbs")} items={[{ label: tc("home"), href: "/" }, { label: tn("accommodation"), href: "/accommodation" }, { label: room.building.name, href: `/buildings/${room.building.slug}` }, { label: room.name }]} />
        <div className="mt-4 flex flex-col gap-2 md:flex-row md:items-end md:justify-between">
          <div>
            <h1 className="text-h1 text-ink-900">{room.name}</h1>
            <p className="mt-1 text-ink-700">{room.type_label} · {room.building.name}, {room.building.location}</p>
          </div>
          {room.tax_included && <Badge tone="success" icon={BadgeIcons.taxIncluded} className="self-start md:self-auto">{t("taxIncluded")}</Badge>}
        </div>

        <div className="mt-8 grid gap-10 lg:grid-cols-[minmax(0,1fr)_384px] lg:gap-6">
          <div className="flex min-w-0 flex-col gap-12">
            <TripGallery images={room.gallery} />
            <DetailSection id="room" title={t("about")}>
              <ul className="mb-4 flex flex-wrap gap-4 text-ink-700">
                <li className="inline-flex items-center gap-2"><Users className="size-5" aria-hidden />{t("capacity", { adults: room.max_adults, children: room.max_children })}</li>
                <li className="inline-flex items-center gap-2"><BedDouble className="size-5" aria-hidden />{room.bed_label}</li>
                <li className="inline-flex items-center gap-2"><Maximize className="size-5" aria-hidden />{room.size_m2} m²</li>
              </ul>
              <p className="text-ink-700 lg:text-lg">{room.description}</p>
              <ul className="mt-4 flex flex-wrap gap-2">
                {room.amenities.map((a) => (<li key={a} className="rounded-pill bg-primary-100 px-3 py-1.5 text-sm text-primary-700">{a}</li>))}
              </ul>
            </DetailSection>
            <SeasonPriceTable seasons={room.price_list} unit="night" priceKey="price_per_night" note={room.tax_included ? t("pricesNoteTax") : t("pricesNote")} />
            <DetailSection id="building" title={t("building")}>
              <BuildingCard building={room.building_detail} />
            </DetailSection>
            {room.other_rooms.length > 0 && (
              <DetailSection id="other" title={t("otherRooms")}>
                <ul className="grid gap-6 sm:grid-cols-2">
                  {room.other_rooms.map((r) => (<li key={r.id}><RoomCard room={r} className="h-full" /></li>))}
                </ul>
              </DetailSection>
            )}
          </div>
          <aside className="lg:sticky lg:top-28 lg:max-h-[calc(100dvh-128px)] lg:self-start lg:overflow-y-auto lg:overscroll-contain lg:rounded-card" aria-label={t("bookingAria")}>
            <RoomBookingWidget room={room} />
          </aside>
        </div>
      </div>
    </>
  );
}
