import { AuthForm } from "@/components/account/AuthForm";
import { pageMetadata } from "@/lib/seo";
import type { Metadata } from "next";
import { getTranslations } from "next-intl/server";
import { locale as rootLocale } from "next/root-params";

export async function generateMetadata(): Promise<Metadata> {
  const locale = await rootLocale();
  const t = await getTranslations("auth");
  return pageMetadata({ locale, path: "/sign-in", title: t("signInTitle"), description: t("subtitle"), noindex: true });
}

export default async function Page() {
  const t = await getTranslations("auth");
  return (
    <div className="bg-sand-50">
      <div className="container-page flex justify-center py-12 lg:py-20">
        <div className="w-full max-w-md rounded-panel border border-ink-100 bg-white p-6 shadow-card md:p-10">
          <h1 className="text-h2 text-ink-900">{t("signInTitle")}</h1>
          <p className="mb-6 mt-2 text-ink-700">{t("subtitle")}</p>
          <AuthForm mode="sign-in" />
        </div>
      </div>
    </div>
  );
}
