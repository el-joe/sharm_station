import { CheckoutForm } from "@/components/booking/CheckoutForm";
import { pageMetadata } from "@/lib/seo";
import type { Metadata } from "next";
import { getTranslations } from "next-intl/server";
import { locale as rootLocale } from "next/root-params";

export async function generateMetadata(): Promise<Metadata> {
  const locale = await rootLocale();
  const t = await getTranslations("checkout");
  return pageMetadata({ locale, path: "/checkout", title: t("title"), description: t("leadText"), noindex: true });
}

export default async function CheckoutPage() {
  const t = await getTranslations("checkout");
  const steps = [t("steps.details"), t("steps.payment"), t("steps.confirmation")];
  return (
    <div className="container-page py-8 lg:py-12">
      <h1 className="text-h1 text-ink-900">{t("title")}</h1>
      <ol className="mb-8 mt-4 flex flex-wrap gap-2 text-sm" aria-label={t("stepsLabel")}>
        {steps.map((s, i) => (
          <li key={s} aria-current={i === 0 ? "step" : undefined} className={i === 0 ? "rounded-pill bg-primary-700 px-3 py-1.5 font-semibold text-white" : "rounded-pill bg-ink-100 px-3 py-1.5 text-ink-700"}>
            {i + 1}. {s}
          </li>
        ))}
      </ol>
      <CheckoutForm />
    </div>
  );
}
