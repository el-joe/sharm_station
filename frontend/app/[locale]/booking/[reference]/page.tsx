import { BookingConfirmation } from "@/components/booking/BookingConfirmation";
import type { Metadata } from "next";
import { getTranslations } from "next-intl/server";
import { Suspense } from "react";

export async function generateMetadata(): Promise<Metadata> {
  const t = await getTranslations("confirmation");
  return { title: t("metaTitle"), robots: { index: false, follow: false } };
}

export default function BookingPage({ params }: PageProps<"/[locale]/booking/[reference]">) {
  return (
    <div className="container-page py-8 lg:py-12">
      <Suspense fallback={<div className="h-[500px] animate-pulse rounded-card bg-ink-100" aria-hidden />}>
        <BookingConfirmation params={params} />
      </Suspense>
    </div>
  );
}
