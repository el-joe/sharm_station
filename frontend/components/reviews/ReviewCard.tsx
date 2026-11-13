import { GoogleMark } from "@/components/ui/GoogleMark";
import { Stars } from "@/components/ui/Stars";
import type { GoogleReview } from "@/lib/api/types";
import { useTranslations } from "next-intl";

/** Figma ReviewCard (25:9425) with the Google mark; text shown in its original language. */
export function ReviewCard({ review }: { review: GoogleReview }) {
  const t = useTranslations("reviews");
  const initials = review.author_name
    .split(/\s+/)
    .map((p) => p[0])
    .join("")
    .slice(0, 2)
    .toUpperCase();
  return (
    <article className="flex h-full flex-col gap-3 rounded-card border border-ink-100 bg-white p-5 shadow-card">
      <header className="flex items-start gap-3">
        {review.author_photo_url ? (
          // Google profile photos are external and tiny; a plain img avoids optimiser config.
          // eslint-disable-next-line @next/next/no-img-element
          <img src={review.author_photo_url} alt="" className="size-11 rounded-full object-cover" referrerPolicy="no-referrer" />
        ) : (
          <span className="grid size-11 shrink-0 place-items-center rounded-full bg-primary-100 font-semibold text-primary-700" aria-hidden>
            {initials}
          </span>
        )}
        <div className="min-w-0 flex-1">
          <h3 className="font-semibold text-ink-900">{review.author_name}</h3>
          {review.author_country && <p className="text-sm text-ink-500">{review.author_country}</p>}
        </div>
        <p className="flex shrink-0 items-center gap-2 text-xs text-ink-500">
          {review.relative_time}
          <GoogleMark className="size-4" />
          <span className="sr-only">{t("onGoogle")}</span>
        </p>
      </header>
      <Stars rating={review.rating} size={18} label={t("ratingAria", { rating: review.rating })} />
      <blockquote lang={review.language} className="text-ink-900">
        “{review.text}”
      </blockquote>
    </article>
  );
}
