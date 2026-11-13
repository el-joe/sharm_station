import { AppImage } from "@/components/ui/AppImage";
import { SectionHeader } from "@/components/ui/SectionHeader";
import { Link } from "@/i18n/navigation";
import type { DestinationSummary } from "@/lib/api/types";
import { cn } from "@/lib/utils";
import { useTranslations } from "next-intl";

// Mosaic spans from the Figma "Destinations grid" (636/276/240 then 396/396/360 on a 1200 grid).
const SPANS = ["lg:col-span-6", "lg:col-span-3", "lg:col-span-3", "lg:col-span-4", "lg:col-span-4", "lg:col-span-4"];

export function DestinationGrid({ destinations }: { destinations: DestinationSummary[] }) {
  const t = useTranslations("home.destinations");
  return (
    <section className="section-y bg-sand-50" aria-labelledby="dest-title">
      <div className="container-page">
        <div id="dest-title">
          <SectionHeader title={t("title")} subtitle={t("subtitle")} link={{ href: "/destinations", label: t("all") }} />
        </div>
        <ul className="no-scrollbar -mx-4 flex snap-x snap-mandatory gap-4 overflow-x-auto px-4 md:mx-0 md:grid md:grid-cols-2 md:gap-6 md:overflow-visible md:px-0 lg:grid-cols-12">
          {destinations.slice(0, 6).map((d, i) => (
            <li key={d.slug} className={cn("w-[78vw] max-w-[320px] shrink-0 snap-start md:w-auto md:max-w-none", SPANS[i])}>
              <Link href={`/destinations/${d.slug}`} className="group relative flex h-[200px] flex-col overflow-hidden rounded-image p-6 text-white md:h-[280px]">
                <AppImage src={d.image} alt="" fill sizes="(min-width: 1024px) 50vw, 80vw" className="-z-0 object-cover transition-transform duration-500 group-hover:scale-105" />
                <span className="absolute inset-0 bg-gradient-to-b from-brand-navy-900/10 to-brand-navy-900/65" aria-hidden />
                <span className="relative font-display text-[28px] font-semibold leading-9">{d.name}</span>
                <span className="relative text-sm">{t("count", { count: d.trips_count })}</span>
              </Link>
            </li>
          ))}
        </ul>
      </div>
    </section>
  );
}
