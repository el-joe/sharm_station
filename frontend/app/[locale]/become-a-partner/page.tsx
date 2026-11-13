import { PartnerForm } from "@/components/account/PartnerForm";
import { Breadcrumbs } from "@/components/ui/Breadcrumbs";
import { pageMetadata } from "@/lib/seo";
import { BadgePercent, LayoutDashboard, Receipt, Wallet } from "lucide-react";
import type { Metadata } from "next";
import { getTranslations } from "next-intl/server";
import { locale as rootLocale } from "next/root-params";

export async function generateMetadata(): Promise<Metadata> {
  const locale = await rootLocale();
  const t = await getTranslations("partner");
  return pageMetadata({ locale, path: "/become-a-partner", title: t("title"), description: t("subtitle") });
}

export default async function PartnerPage() {
  const [t, tc] = await Promise.all([getTranslations("partner"), getTranslations("common")]);
  const perks = [
    { Icon: BadgePercent, key: "prices" },
    { Icon: Wallet, key: "deposit" },
    { Icon: LayoutDashboard, key: "panel" },
    { Icon: Receipt, key: "statements" },
  ] as const;
  return (
    <>
      <section className="bg-partner-100">
        <div className="container-page py-10 lg:py-16">
          <Breadcrumbs className="text-ink-700" label={tc("breadcrumbs")} items={[{ label: tc("home"), href: "/" }, { label: t("crumb") }]} />
          <h1 className="text-h1 mt-3 max-w-3xl text-ink-900">{t("title")}</h1>
          <p className="mt-3 max-w-2xl text-ink-700 lg:text-lg">{t("subtitle")}</p>
          <ul className="mt-8 grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
            {perks.map(({ Icon, key }) => (
              <li key={key} className="rounded-card bg-white p-5 shadow-card">
                <span className="grid size-11 place-items-center rounded-full bg-partner-100 text-partner-600"><Icon className="size-5" aria-hidden /></span>
                <h2 className="mt-3 font-semibold text-ink-900">{t(`perks.${key}.title`)}</h2>
                <p className="mt-1 text-sm text-ink-700">{t(`perks.${key}.text`)}</p>
              </li>
            ))}
          </ul>
        </div>
      </section>
      <section className="container-page py-10 lg:py-16" aria-labelledby="apply-title">
        <div className="mx-auto max-w-3xl rounded-panel border border-ink-100 bg-white p-6 shadow-card md:p-10">
          <h2 id="apply-title" className="text-h3 text-ink-900">{t("formTitle")}</h2>
          <p className="mb-6 mt-2 text-ink-700">{t("formText")}</p>
          <PartnerForm />
        </div>
      </section>
    </>
  );
}
