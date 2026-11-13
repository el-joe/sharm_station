import { AppImage } from "@/components/ui/AppImage";
import { SectionHeader } from "@/components/ui/SectionHeader";
import type { TransferCatalog } from "@/lib/api/types";
import { useTranslations } from "next-intl";
import { TransferMiniForm } from "./TransferMiniForm";

/** CR-3 (revised 13 Nov 2026): the airport image comes first, then the transfer form (desktop and mobile). */
export function TransfersSection({ catalog }: { catalog: TransferCatalog }) {
  const t = useTranslations("home.transfers");
  return (
    <section className="section-y bg-sand-50" aria-labelledby="transfers-title">
      <div className="container-page">
        <div id="transfers-title">
          <SectionHeader title={t("title")} subtitle={t("subtitle")} link={{ href: "/transfers", label: t("all") }} />
        </div>
        <div className="flex flex-col gap-6">
          <div className="relative h-[220px] overflow-hidden rounded-image md:h-[360px]">
            <AppImage src="/images/airport.webp" alt={t("imageAlt")} fill sizes="(min-width: 1248px) 1200px, 100vw" className="object-cover" />
            <span className="absolute inset-0 bg-gradient-to-b from-brand-navy-900/10 to-brand-navy-900/50" aria-hidden />
          </div>
          <TransferMiniForm catalog={catalog} />
        </div>
      </div>
    </section>
  );
}
