import { buttonClasses } from "@/components/ui/Button";
import { Link } from "@/i18n/navigation";
import type { Building } from "@/lib/api/types";
import { Clock, MapPin, Star } from "lucide-react";
import { useTranslations } from "next-intl";

/** Building card on the room page (SRS 6.3). */
export function BuildingCard({ building, showLink = true }: { building: Building; showLink?: boolean }) {
  const t = useTranslations("building");
  return (
    <div className="rounded-card border border-ink-100 bg-white p-6 shadow-card">
      <div className="flex flex-wrap items-start justify-between gap-3">
        <div>
          <p className="text-eyebrow text-brand-700">{building.type}</p>
          <h3 className="mt-1 text-[22px] font-semibold text-ink-900">{building.name}</h3>
          <p className="mt-1 inline-flex" role="img" aria-label={t("stars", { count: building.stars })}>
            {Array.from({ length: building.stars }, (_, i) => <Star key={i} className="size-4 fill-gold-500 text-gold-500" aria-hidden />)}
          </p>
        </div>
        {building.taxes_included && <span className="rounded-pill bg-success-100 px-3 py-1 text-xs font-medium text-ink-900">{t("taxesIncluded")}</span>}
      </div>
      <p className="mt-3 flex items-start gap-2 text-ink-700"><MapPin className="mt-0.5 size-4 shrink-0" aria-hidden />{building.address}</p>
      <p className="mt-2 flex items-center gap-2 text-ink-700"><Clock className="size-4 shrink-0" aria-hidden />{t("times", { in: building.check_in_from, out: building.check_out_until })}</p>
      <p className="mt-3 text-ink-700">{building.description}</p>
      <ul className="mt-4 flex flex-wrap gap-2">
        {building.amenities.map((a) => (
          <li key={a} className="rounded-pill bg-primary-100 px-3 py-1 text-sm text-primary-700">{a}</li>
        ))}
      </ul>
      <p className="mt-4 rounded-image bg-sand-50 p-3 text-sm text-ink-700"><span className="font-semibold text-ink-900">{t("policy")}:</span> {building.cancellation_policy}</p>
      <div className="mt-4 flex flex-wrap gap-3">
        <a href={`https://www.google.com/maps/search/?api=1&query=${building.lat},${building.lng}`} target="_blank" rel="noopener noreferrer" className={buttonClasses("outline", "sm", "h-11")}>{t("map")}</a>
        {showLink && <Link href={`/buildings/${building.slug}`} className={buttonClasses("ghost", "sm", "h-11")}>{t("view")}</Link>}
      </div>
    </div>
  );
}
