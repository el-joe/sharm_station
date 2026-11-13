import { AppImage } from "@/components/ui/AppImage";
import type { DestinationSummary, TransferHub } from "@/lib/api/types";
import { useTranslations } from "next-intl";
import { HeroSearchCard } from "./HeroSearchCard";
import { TrustChips } from "./TrustChips";

/** Figma HomeHero (60:14593): photo, glass panel, H1, HeroSearchCard, TrustChips. */
export function HomeHero({ destinations, hubs, rating }: { destinations: DestinationSummary[]; hubs: TransferHub[]; rating: number }) {
  const t = useTranslations("hero");
  return (
    <section className="relative isolate overflow-hidden" aria-labelledby="hero-title">
      <AppImage src="/images/cairo.webp" alt="" fill preload sizes="100vw" className="-z-20 object-cover" />
      <div className="absolute inset-0 -z-10 bg-gradient-to-b from-brand-navy-900/10 to-brand-navy-900/65" aria-hidden />
      <div className="container-page pb-12 pt-4 md:py-10 lg:pb-24 lg:pt-9">
        <div className="mx-auto flex max-w-[1080px] flex-col items-center gap-4 rounded-panel border border-white/30 bg-brand-navy-900/40 px-3 pb-4 pt-8 text-center backdrop-blur-md md:gap-5 md:px-10 md:pb-9 md:pt-11">
          <h1 id="hero-title" className="text-display text-white">
            {t.rich("title", { accent: (chunks) => <span className="text-gold-500 max-md:block">{chunks}</span> })}
          </h1>
          <p className="max-w-[720px] text-base text-white md:text-lg">
            <span className="md:hidden">{t("subtitleShort")}</span>
            <span className="max-md:hidden">{t("subtitle")}</span>
          </p>
          <div className="w-full max-w-[1000px]">
            <HeroSearchCard destinations={destinations} hubs={hubs} />
          </div>
          <TrustChips rating={rating} />
        </div>
      </div>
    </section>
  );
}
