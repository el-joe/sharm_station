import { buttonClasses } from "@/components/ui/Button";
import { GoogleMark } from "@/components/ui/GoogleMark";
import { Stars } from "@/components/ui/Stars";
import type { GoogleReviews } from "@/lib/api/types";
import { ExternalLink } from "lucide-react";
import { useFormatter, useTranslations } from "next-intl";

/** Figma ReviewSummary (Google) 62:14903 — SRS 13.4: rating, total, Google attribution, write-a-review CTA. */
export function GoogleReviewSummary({ data }: { data: GoogleReviews }) {
  const t = useTranslations("reviews");
  const format = useFormatter();
  const total = format.number(data.total);
  return (
    <div className="flex flex-col gap-6 rounded-card bg-sand-50 p-6 md:flex-row md:items-center md:justify-between md:p-8">
      <div>
        <p className="flex items-center gap-2 text-sm font-medium text-ink-700">
          <GoogleMark /> {t("googleReviews")}
        </p>
        <p className="mt-2 flex items-baseline gap-2">
          <span className="font-display text-[44px] font-semibold leading-none text-ink-900">{format.number(data.rating, { minimumFractionDigits: 1 })}</span>
          <span className="text-ink-700">{t("outOf5")}</span>
        </p>
        <p className="mt-2 flex items-center gap-2 text-sm text-ink-900">
          <Stars rating={data.rating} label={t("ratingAria", { rating: data.rating })} />
          {format.number(data.rating, { minimumFractionDigits: 1 })} ({t("count", { total })})
        </p>
        <p className="mt-1 text-sm text-ink-700">{t("basedOn", { total })}</p>
      </div>
      <div className="flex flex-col items-start gap-2 md:items-end">
        <a href={data.write_review_url} target="_blank" rel="noopener noreferrer" className={buttonClasses("primary", "md")}>
          {t("write")}
          <ExternalLink className="size-4" aria-hidden />
        </a>
        <p className="text-xs text-ink-700">{t("attribution")}</p>
      </div>
    </div>
  );
}
