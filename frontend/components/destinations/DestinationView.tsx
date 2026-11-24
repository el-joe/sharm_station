import { RoomCard } from "@/components/accommodation/RoomCard";
import { MapView } from "@/components/map/MapView";
import { DESTINATION_CENTERS, hasCoords, type MapPlace } from "@/components/map/types";
import { Faq } from "@/components/trips/Faq";
import { TripCard } from "@/components/trips/TripCard";
import { AppImage } from "@/components/ui/AppImage";
import { Breadcrumbs } from "@/components/ui/Breadcrumbs";
import { buttonClasses } from "@/components/ui/Button";
import { SectionHeader } from "@/components/ui/SectionHeader";
import { Link } from "@/i18n/navigation";
import type { Building, Destination, DestinationSummary, RoomSummary, TripSummary } from "@/lib/api/types";
import type { DestinationGuide } from "@/lib/content/destinations";
import { whatsappHref } from "@/lib/site";
import { CalendarDays, Compass, ExternalLink, Hotel, Star } from "lucide-react";
import { useTranslations } from "next-intl";
import type { ReactNode } from "react";

interface Props {
  destination: Destination & { trips: TripSummary[]; buildings: (Building & { google_rating: number | null; google_total: number | null })[] };
  packages: TripSummary[];
  rooms: RoomSummary[];
  related: DestinationSummary[];
  guide: DestinationGuide;
  /** Trips of this destination that have a meeting-point location. */
  tripPlaces: { slug: string; title: string; type: string; lat: number; lng: number; price_from: number }[];
}

function Section({ id, children, tinted }: { id: string; children: ReactNode; tinted?: boolean }) {
  return (
    <section id={id} className={`scroll-mt-32 py-12 lg:py-16 ${tinted ? "bg-sand-50" : ""}`} aria-labelledby={`${id}-title`}>
      <div className="container-page">{children}</div>
    </section>
  );
}

const grid = "grid gap-6 sm:grid-cols-2 lg:grid-cols-3";

/** Figma Destination / Desktop (104:20562) and Mobile (105:10118). */
export function DestinationView({ destination: d, packages, rooms, related, guide, tripPlaces }: Props) {
  const t = useTranslations("destinations");
  const tc = useTranslations("common");
  const trips = d.trips.filter((x) => x.type === "single_day" || x.type === "multi_day");
  const nile = d.trips.filter((x) => x.type === "nile");
  const rated = d.buildings.filter((b) => b.google_rating);
  const rating = rated.length ? rated.reduce((s, b) => s + (b.google_rating ?? 0), 0) / rated.length : null;
  const reviews = rated.reduce((s, b) => s + (b.google_total ?? 0), 0);

  const facts = [
    { Icon: CalendarDays, label: t("facts.bestTime"), value: guide.bestTime },
    { Icon: Compass, label: t("facts.trips"), value: String(d.trips_count || d.trips.length) },
    { Icon: Hotel, label: t("facts.rooms"), value: String(rooms.length) },
    ...(rating ? [{ Icon: Star, label: t("facts.rating"), value: `${rating.toFixed(1)}${reviews ? ` (${reviews.toLocaleString()})` : ""}` }] : []),
  ];

  const tripPath = (type: string) => (type === "nile" ? "/nile" : type === "package" ? "/packages" : "/trips");
  const places: MapPlace[] = [
    ...d.buildings
      .filter((b) => hasCoords(b.lat, b.lng))
      .map((b): MapPlace => ({
        id: `building-${b.slug}`,
        kind: "building",
        lat: b.lat,
        lng: b.lng,
        title: b.name,
        subtitle: [b.type, b.address].filter(Boolean).join(" · "),
        links: [{ label: t("mapViewStay"), href: `/buildings/${b.slug}` }],
      })),
    ...tripPlaces.map((x): MapPlace => ({
      id: `trip-${x.slug}`,
      kind: "trip",
      lat: x.lat,
      lng: x.lng,
      title: x.title,
      subtitle: t("mapMeetingPoint"),
      links: [{ label: t("mapViewTrip"), href: `${tripPath(x.type)}/${x.slug}`, priceEur: x.price_from }],
    })),
  ];
  const center = DESTINATION_CENTERS[d.slug] ?? null;
  const showMap = places.length > 0 || center !== null;

  const tabs = [
    trips.length || !nile.length ? { id: "trips", key: "trips" } : null,
    nile.length ? { id: "nile", key: "nile" } : null,
    packages.length ? { id: "packages", key: "packages" } : null,
    rooms.length ? { id: "stay", key: "stay" } : null,
    { id: "faq", key: "faq" },
  ].filter(Boolean) as { id: string; key: string }[];

  return (
    <>
      <section className="relative isolate flex min-h-[360px] items-end overflow-hidden text-white md:min-h-[480px]">
        <AppImage src={d.image} alt="" fill preload sizes="100vw" className="-z-20 object-cover" />
        <div className="absolute inset-0 -z-10 bg-gradient-to-b from-brand-navy-900/10 via-brand-navy-900/35 to-brand-navy-900/85" aria-hidden />
        <div className="container-page pb-8 pt-24 md:pb-12">
          <Breadcrumbs label={tc("breadcrumbs")} items={[{ label: tc("home"), href: "/" }, { label: t("crumb"), href: "/destinations" }, { label: d.name }]} />
          <h1 className="text-display mt-3">{d.name}</h1>
          {d.description && <p className="mt-3 max-w-2xl text-base md:text-lg">{d.description}</p>}
        </div>
      </section>

      <div className="container-page relative z-10 -mt-6">
        <dl aria-label={t("facts.label")} className="grid grid-cols-2 gap-4 rounded-card border border-ink-100 bg-white p-4 shadow-card md:p-6 lg:grid-cols-4">
          {facts.map(({ Icon, label, value }) => (
            <div key={label} className="flex items-center gap-3">
              <span className="grid size-10 shrink-0 place-items-center rounded-full bg-primary-100 text-primary-700"><Icon className="size-5" aria-hidden /></span>
              <div>
                <dt className="text-xs text-ink-500">{label}</dt>
                <dd className="font-semibold text-ink-900">{value}</dd>
              </div>
            </div>
          ))}
        </dl>
      </div>

      <nav aria-label={t("sections")} className="sticky top-16 z-20 mt-6 border-b border-ink-100 bg-white lg:top-[116px]">
        <ul className="container-page no-scrollbar flex gap-1 overflow-x-auto md:gap-4">
          {tabs.map((s) => (
            <li key={s.id} className="shrink-0">
              <a href={`#${s.id}`} className="inline-flex h-12 items-center border-b-2 border-transparent px-3 text-ink-700 hover:border-brand-500 hover:text-ink-900">
                {t(`tabs.${s.key}`)}
              </a>
            </li>
          ))}
        </ul>
      </nav>

      {(trips.length > 0 || nile.length === 0) && (
        <Section id="trips">
          <div id="trips-title"><SectionHeader title={t("tripsTitle", { name: d.name })} subtitle={t("tripsSubtitle")} link={{ href: { pathname: "/trips", query: { destination: d.slug } }, label: t("seeAll") }} /></div>
          {trips.length ? (
            <ul className={grid}>{trips.map((x) => <li key={x.slug}><TripCard trip={x} className="h-full" /></li>)}</ul>
          ) : (
            <p className="rounded-card bg-sand-50 p-6 text-ink-700">
              {t("noTrips", { name: d.name })}{" "}
              <a href={whatsappHref(d.name)} target="_blank" rel="noopener noreferrer" className="font-semibold text-primary-700 underline">WhatsApp</a>
            </p>
          )}
        </Section>
      )}

      {nile.length > 0 && (
        <Section id="nile" tinted>
          <div id="nile-title"><SectionHeader title={t("nileTitle", { name: d.name })} subtitle={t("nileSubtitle")} link={{ href: "/nile", label: t("seeAll") }} /></div>
          <ul className={grid}>{nile.map((x) => <li key={x.slug}><TripCard trip={x} className="h-full" /></li>)}</ul>
        </Section>
      )}

      {packages.length > 0 && (
        <Section id="packages">
          <div id="packages-title"><SectionHeader title={t("packagesTitle", { name: d.name })} subtitle={t("packagesSubtitle")} link={{ href: "/packages", label: t("seeAll") }} /></div>
          <ul className={grid}>{packages.slice(0, 6).map((x) => <li key={x.slug}><TripCard trip={x} className="h-full" /></li>)}</ul>
        </Section>
      )}

      {rooms.length > 0 && (
        <Section id="stay" tinted>
          <div id="stay-title"><SectionHeader title={t("stayTitle", { name: d.name })} subtitle={t("staySubtitle")} link={{ href: { pathname: "/accommodation", query: { destination: d.slug } }, label: t("seeAll") }} /></div>
          <ul className="grid gap-6 sm:grid-cols-2 lg:grid-cols-4">{rooms.slice(0, 8).map((r) => <li key={r.id}><RoomCard room={r} className="h-full" /></li>)}</ul>
          {d.buildings.length > 0 && (
            <div className="mt-8">
              <h3 className="text-h4 text-ink-900">{t("buildingsTitle")}</h3>
              <ul className="mt-3 flex flex-wrap gap-2">
                {d.buildings.map((b) => (
                  <li key={b.slug}>
                    <Link href={`/buildings/${b.slug}`} className="inline-flex min-h-11 items-center gap-2 rounded-pill border border-ink-300 bg-white px-4 text-sm font-semibold text-ink-900 hover:border-primary-700">
                      <Hotel className="size-4 text-primary-700" aria-hidden /> {b.name}
                      <span className="text-gold-500" aria-label={`${b.stars}★`}>{"★".repeat(b.stars)}</span>
                    </Link>
                  </li>
                ))}
              </ul>
            </div>
          )}
        </Section>
      )}

      <Section id="why">
        <p className="text-eyebrow mb-3 text-brand-700">{d.name}</p>
        <h2 id="why-title" className="text-h2 text-ink-900">{t("whyTitle", { name: d.name })}</h2>
        <ul className="mt-8 grid gap-6 md:grid-cols-3">
          {guide.why.map((w) => (
            <li key={w.title} className="rounded-card border border-ink-100 bg-white p-6 shadow-card">
              <h3 className="font-display text-xl font-semibold text-ink-900">{w.title}</h3>
              <p className="mt-2 text-ink-700">{w.text}</p>
            </li>
          ))}
        </ul>
      </Section>

      <Section id="map" tinted>
        <div className="mb-6 flex flex-wrap items-end justify-between gap-3">
          <h2 id="map-title" className="text-h2 text-ink-900">{t("mapTitle", { name: d.name })}</h2>
          <a
            href={`https://www.google.com/maps/search/?api=1&query=${encodeURIComponent(`${d.name}, Egypt`)}`}
            target="_blank"
            rel="noopener noreferrer"
            className={buttonClasses("outline", "sm")}
          >
            {t("openMaps", { name: d.name })} <ExternalLink className="size-4" aria-hidden />
          </a>
        </div>
        {showMap ? (
          <MapView places={places} center={center} zoom={11} label={t("mapLabel", { name: d.name })} />
        ) : (
          <p className="rounded-card bg-white p-6 text-ink-700">{t("mapNoPlaces", { name: d.name })}</p>
        )}
      </Section>

      <Section id="faq">
        <h2 id="faq-title" className="text-h2 mb-6 text-ink-900">{t("faqTitle", { name: d.name })}</h2>
        <div className="max-w-4xl"><Faq items={guide.faq} /></div>
      </Section>

      {related.length > 0 && (
        <Section id="related" tinted>
          <div id="related-title"><SectionHeader title={t("relatedTitle")} subtitle={t("relatedSubtitle")} link={{ href: "/destinations", label: t("seeAll") }} /></div>
          <ul className="grid gap-6 sm:grid-cols-3">
            {related.map((r) => (
              <li key={r.slug}>
                <Link href={`/destinations/${r.slug}`} className="group relative flex h-[200px] flex-col justify-end overflow-hidden rounded-image p-5 text-white">
                  <AppImage src={r.image} alt="" fill sizes="(min-width: 640px) 33vw, 100vw" className="-z-0 object-cover transition-transform duration-500 group-hover:scale-105" />
                  <span className="absolute inset-0 bg-gradient-to-b from-brand-navy-900/10 to-brand-navy-900/70" aria-hidden />
                  <span className="relative font-display text-[28px] font-semibold leading-9">{r.name}</span>
                  <span className="relative text-sm">{t("count", { count: r.trips_count })}</span>
                </Link>
              </li>
            ))}
          </ul>
        </Section>
      )}
    </>
  );
}
