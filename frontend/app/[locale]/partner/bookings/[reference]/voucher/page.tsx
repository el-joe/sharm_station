import { PartnerVoucher } from "@/components/partner/PartnerVoucher";
import type { Metadata } from "next";
import { getTranslations } from "next-intl/server";
import { Suspense } from "react";

export async function generateMetadata({ params }: PageProps<"/[locale]/partner/bookings/[reference]/voucher">): Promise<Metadata> {
  const { reference } = await params;
  const t = await getTranslations("partnerPanel.voucher");
  return { title: t("metaTitle", { reference }), robots: { index: false, follow: false } };
}

export default function Page({ params }: PageProps<"/[locale]/partner/bookings/[reference]/voucher">) {
  return (
    <Suspense fallback={<div className="h-[600px] animate-pulse rounded-card bg-ink-100" aria-hidden />}>
      <PartnerVoucher params={params} />
    </Suspense>
  );
}
