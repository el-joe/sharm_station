import { AppImage } from "@/components/ui/AppImage";
import { buttonClasses } from "@/components/ui/Button";
import { Link } from "@/i18n/navigation";
import type { TripSummary } from "@/lib/api/types";
import { Price } from "@/lib/currency";
import { useTranslations } from "next-intl";

export function NileBanner({ cruise }: { cruise?: TripSummary }) {
  const t = useTranslations("home.nile");
  return (
    <section className="section-y bg-primary-700 text-white" aria-labelledby="nile-title">
      <div className="container-page grid items-center gap-8 lg:grid-cols-2 lg:gap-16">
        <div>
          <p className="text-eyebrow">{t("eyebrow")}</p>
          <h2 id="nile-title" className="text-h2 mt-3">{t("title")}</h2>
          <p className="mt-4 text-white/90 lg:text-lg">{t("text")}</p>
          <div className="mt-6 flex items-center gap-6">
            <Link href={cruise ? `/nile/${cruise.slug}` : "/nile"} className={buttonClasses("cta", "lg")}>
              {t("cta")}
            </Link>
            {cruise && (
              <p className="flex flex-col text-sm">
                {t("from")}
                <Price amount={cruise.price_from} className="text-[32px] font-bold leading-9" />
              </p>
            )}
          </div>
        </div>
        <div className="relative aspect-[560/350] overflow-hidden rounded-image">
          <AppImage src="/images/nile-cruise.webp" alt={t("imageAlt")} fill sizes="(min-width: 1024px) 560px, 100vw" className="object-cover" />
        </div>
      </div>
    </section>
  );
}
