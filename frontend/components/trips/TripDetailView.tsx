import { BookingWidget } from "@/components/booking/BookingWidget";
import { AddonOptionCard } from "@/components/booking/AddonOptionCard";
import { SeasonPriceTable } from "@/components/booking/SeasonPriceTable";
import { GoogleReviewsSection } from "@/components/reviews/GoogleReviewsSection";
import { getGoogleReviews, getTrips, getPackages } from "@/lib/api/server";
import type { Locale, TripDetail } from "@/lib/api/types";
import { SITE_URL } from "@/lib/site";
import { Clock, CreditCard, MapPin, Users } from "lucide-react";
import { getTranslations } from "next-intl/server";
import { DetailSection } from "./DetailSection";
import { Faq } from "./Faq";
import { Highlights } from "./Highlights";
import { IncludedList } from "./IncludedList";
import { MeetingPoint } from "./MeetingPoint";
import { NileFacts } from "./NileFacts";
import { PackageStages } from "./PackageStages";
import { Policies } from "./Policies";
import { QuickFacts } from "./QuickFacts";
import { SectionNav } from "./SectionNav";
import { Timeline } from "./Timeline";
import { TripCard } from "./TripCard";
import { TripGallery } from "./TripGallery";
import { TripHero } from "./TripHero";
import { WhatToBring } from "./WhatToBring";
import { productHref } from "./paths";

/** Shared detail page for trips, Nile trips and packages (SRS 13.1 "Trip page"). */
export async function TripDetailView({ trip, locale }: { trip: TripDetail; locale: Locale }) {
  const [t, tc, reviews] = await Promise.all([getTranslations("trip"), getTranslations("common"), getGoogleReviews(locale)]);
  const tn = await getTranslations("nav");
  const tCard = await getTranslations("card");

  const similarSource =
    trip.type === "package"
      ? await getPackages({ per_page: 4 }, locale)
      : await getTrips({ type: trip.type === "nile" ? "nile" : ["single_day", "multi_day"], destination: undefined, per_page: 8 }, locale);
  const similar = similarSource.data.filter((s) => s.slug !== trip.slug).slice(0, 3);

  const listing =
    trip.type === "nile" ? { label: tn("nile"), href: "/nile" } : trip.type === "package" ? { label: tn("packages"), href: "/packages" } : { label: tn("trips"), href: "/trips" };
  const duration =
    trip.days && trip.days > 1
      ? trip.nights
        ? tCard("daysNights", { days: trip.days, nights: trip.nights })
        : tCard("days", { days: trip.days })
      : tCard("hours", { hours: trip.duration_hours ?? 0 });

  // Packages from the API have no single meeting point (transfers are included).
  const hasMeeting = Boolean(trip.meeting_point.text || trip.meeting_point.lat);
  const sections = [
    { id: "overview", key: "overview" },
    { id: "gallery", key: "gallery" },
    { id: "highlights", key: "highlights" },
    { id: "itinerary", key: "itinerary" },
    { id: "prices", key: "prices" },
    { id: "meeting", key: "meeting" },
    { id: "reviews", key: "reviews" },
    ...(trip.faq.length ? [{ id: "faq", key: "faq" }] : []),
  ].filter((s) => (s.id !== "itinerary" || trip.timeline.length > 0) && (s.id !== "meeting" || hasMeeting));

  const jsonLd = {
    "@context": "https://schema.org",
    "@type": "TouristTrip",
    name: trip.title,
    description: trip.summary,
    image: trip.gallery.map((g) => (g.url.startsWith("http") ? g.url : `${SITE_URL}${g.url}`)),
    touristType: trip.booking_types,
    offers: { "@type": "Offer", price: trip.price_from, priceCurrency: "EUR", availability: "https://schema.org/InStock", url: `${SITE_URL}/${locale}${productHref(trip)}` },
    provider: { "@type": "TravelAgency", name: "Sharm Station Viaggi", url: SITE_URL },
  };

  return (
    <>
      <script type="application/ld+json" dangerouslySetInnerHTML={{ __html: JSON.stringify(jsonLd).replace(/</g, "\\u003c") }} />
      <TripHero trip={trip} duration={duration} crumbs={[{ label: tc("home"), href: "/" }, listing, { label: trip.destination.name }]} />
      <SectionNav sections={sections} />
      <div className="container-page grid gap-10 py-10 pb-28 lg:grid-cols-[minmax(0,1fr)_384px] lg:gap-6 lg:py-16 xl:gap-6">
        <div className="flex min-w-0 flex-col gap-14 lg:gap-16">
          <DetailSection id="overview" eyebrow={t("eyebrows.overview")} title={trip.type === "package" ? t("aboutPackage") : t("about")}>
            <p className="text-ink-700 lg:text-lg">{trip.description}</p>
            <QuickFacts
              items={[
                { icon: <Clock />, label: t("facts.duration"), value: duration },
                { icon: <Users />, label: t("facts.group"), value: trip.booking_types.length > 1 ? t("facts.privateOrGroup") : t(trip.booking_types[0] === "private" ? "facts.privateOnly" : "facts.groupOnly") },
                { icon: <MapPin />, label: t("facts.pickup"), value: trip.transfers_included ? t("facts.airportPickup") : t("facts.hotelPickup") },
                { icon: <CreditCard />, label: t("facts.deposit"), value: t("facts.depositValue", { percent: trip.deposit_percent }) },
              ]}
            />
          </DetailSection>

          {trip.stages && trip.stages.length > 0 && (
            <DetailSection id="stages" eyebrow={t("eyebrows.stages")} title={t("stages")}>
              <PackageStages stages={trip.stages} />
            </DetailSection>
          )}

          {trip.vessel && (
            <DetailSection id="cruise" eyebrow={t("eyebrows.cruise")} title={t("onBoard")}>
              <NileFacts vessel={trip.vessel} />
            </DetailSection>
          )}

          <DetailSection
            id="gallery"
            eyebrow={t("eyebrows.gallery")}
            title={t("gallery")}
            aside={<span className="text-eyebrow rounded-pill border border-ink-300 px-3 py-1.5 text-ink-700">{t("photos", { count: trip.gallery.length })}</span>}
          >
            <TripGallery images={trip.gallery} />
          </DetailSection>

          <DetailSection id="highlights" eyebrow={t("eyebrows.highlights")} title={t("highlights")}>
            <Highlights items={trip.highlights} />
          </DetailSection>

          <DetailSection id="included" eyebrow={t("eyebrows.included")} title={t("includedTitle")}>
            <IncludedList included={trip.included} excluded={trip.excluded} />
          </DetailSection>

          {trip.timeline.length > 0 && (
            <DetailSection id="itinerary" eyebrow={trip.timeline.length > 1 ? t("eyebrows.dayByDay") : t("eyebrows.hourByHour")} title={t("itinerary")}>
              <Timeline days={trip.timeline} />
            </DetailSection>
          )}

          <div id="prices" className="scroll-mt-40">
            <SeasonPriceTable
              seasons={trip.price_list}
              unit={trip.pricing_mode === "per_unit" ? "unit" : "person"}
              note={trip.pricing_mode === "per_unit" ? t("pricesNoteUnit") : t("pricesNote")}
            />
          </div>

          {hasMeeting && (
            <DetailSection id="meeting" eyebrow={t("eyebrows.meeting")} title={t("meetingPoint")}>
              <MeetingPoint text={trip.meeting_point.text} lat={trip.meeting_point.lat} lng={trip.meeting_point.lng} label={trip.meeting_point.label} />
            </DetailSection>
          )}

          <WhatToBring items={trip.what_to_bring} />

          {trip.addons.length > 0 && (
            <DetailSection id="addons" eyebrow={t("eyebrows.addons")} title={t("addons")}>
              <p className="-mt-2 mb-4 text-ink-700">{t("addonsText")}</p>
              <ul className="flex flex-col gap-4">
                {trip.addons.map((a) => (
                  <li key={a.id}>
                    <AddonOptionCard addon={a} checked={false} />
                  </li>
                ))}
              </ul>
            </DetailSection>
          )}

          <DetailSection id="policies" eyebrow={t("eyebrows.policies")} title={t("policies")}>
            <Policies cancellation={trip.policies.cancellation} childrenPolicy={trip.policies.children} />
          </DetailSection>

          <GoogleReviewsSection data={reviews} compact limit={2} />

          {trip.faq.length > 0 && (
            <DetailSection id="faq" eyebrow={t("eyebrows.faq")} title={t("faq")}>
              <Faq items={trip.faq} />
            </DetailSection>
          )}
        </div>

        <aside className="lg:sticky lg:top-[190px] lg:max-h-[calc(100dvh-206px)] lg:self-start lg:overflow-y-auto lg:overscroll-contain lg:rounded-card" aria-label={t("bookingAria")}>
          <BookingWidget trip={trip} />
        </aside>
      </div>

      {similar.length > 0 && (
        <section className="section-y bg-sand-50" aria-labelledby="similar-title">
          <div className="container-page">
            <h2 id="similar-title" className="text-h2 mb-8 text-ink-900">{t("similar")}</h2>
            <ul className="grid gap-6 sm:grid-cols-2 lg:grid-cols-3">
              {similar.map((s) => (
                <li key={s.slug}>
                  <TripCard trip={s} className="h-full" />
                </li>
              ))}
            </ul>
          </div>
        </section>
      )}
    </>
  );
}

export function TripDetailSkeleton() {
  return (
    <div aria-hidden>
      <div className="h-[360px] animate-pulse bg-ink-100 md:h-[520px]" />
      <div className="container-page grid gap-6 py-12 lg:grid-cols-[1fr_384px]">
        <div className="flex flex-col gap-4">
          <div className="h-8 w-1/2 animate-pulse rounded bg-ink-100" />
          <div className="h-32 animate-pulse rounded bg-ink-100" />
          <div className="h-80 animate-pulse rounded bg-ink-100" />
        </div>
        <div className="hidden h-[640px] animate-pulse rounded-card bg-ink-100 lg:block" />
      </div>
    </div>
  );
}
