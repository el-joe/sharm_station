import { AppImage } from "@/components/ui/AppImage";
import { buttonClasses } from "@/components/ui/Button";
import { Link } from "@/i18n/navigation";
import type { Destination, DestinationSummary } from "@/lib/api/types";
import { useTranslations } from "next-intl";

/** Destination card (Figma "Discover": photo, name, short text, Explore button). */
export function DestinationCard({ destination: d, priority }: { destination: DestinationSummary & Partial<Pick<Destination, "description">>; priority?: boolean }) {
  const t = useTranslations("destinations");
  return (
    <article className="group relative flex h-full flex-col overflow-hidden rounded-card border border-ink-100 bg-white shadow-card transition-shadow hover:shadow-raised">
      <div className="relative aspect-[4/3] overflow-hidden bg-ink-100">
        <AppImage src={d.image} alt="" fill preload={priority} sizes="(min-width: 1024px) 384px, (min-width: 640px) 50vw, 100vw" className="object-cover transition-transform duration-500 group-hover:scale-105" />
      </div>
      <div className="flex flex-1 flex-col gap-2 p-5">
        <h2 className="font-display text-2xl font-semibold text-ink-900">
          <Link href={`/destinations/${d.slug}`} className="after:absolute after:inset-0 after:rounded-card">
            {d.name}
          </Link>
        </h2>
        <p className="text-sm text-ink-500">{t("count", { count: d.trips_count })}</p>
        {d.description && <p className="text-ink-700">{d.description}</p>}
        <span className={buttonClasses("outline", "sm", "pointer-events-none mt-auto self-start")} aria-hidden>
          {t("explore", { name: d.name })}
        </span>
      </div>
    </article>
  );
}
