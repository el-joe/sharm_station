import { SectionHeader } from "@/components/ui/SectionHeader";
import type { GoogleReviews } from "@/lib/api/types";
import { cn } from "@/lib/utils";
import { useTranslations } from "next-intl";
import { GoogleReviewSummary } from "./GoogleReviewSummary";
import { ReviewCard } from "./ReviewCard";

export function GoogleReviewsSection({ data, limit = 3, className, compact = false }: { data: GoogleReviews; limit?: number; className?: string; compact?: boolean }) {
  const t = useTranslations("reviews");
  return (
    <section id="reviews" aria-labelledby="reviews-title" className={cn(!compact && "section-y", className)}>
      <div className={cn(!compact && "container-page")}>
        {compact ? (
          <h2 id="reviews-title" className="text-h3 mb-6 text-ink-900">{t("title")}</h2>
        ) : (
          <div id="reviews-title">
            <SectionHeader title={t("title")} subtitle={t("subtitle")} link={{ href: data.reviews_url, label: t("readAll") }} />
          </div>
        )}
        <GoogleReviewSummary data={data} />
        <ul className={cn("mt-6 grid gap-6", compact ? "md:grid-cols-2" : "md:grid-cols-3")}>
          {data.reviews.slice(0, limit).map((r) => (
            <li key={r.id}>
              <ReviewCard review={r} />
            </li>
          ))}
        </ul>
      </div>
    </section>
  );
}
