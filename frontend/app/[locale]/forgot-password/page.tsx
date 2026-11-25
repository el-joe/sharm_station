import { ForgotPasswordForm } from "@/components/account/ForgotPasswordForm";
import { pageMetadata } from "@/lib/seo";
import type { Metadata } from "next";
import { getTranslations } from "next-intl/server";
import { locale as rootLocale } from "next/root-params";

export async function generateMetadata(): Promise<Metadata> {
  const locale = await rootLocale();
  const t = await getTranslations("passwordReset");
  return pageMetadata({ locale, path: "/forgot-password", title: t("forgotTitle"), description: t("forgotText"), noindex: true });
}

/** /forgot-password: e-mails a link to choose a new password (always the same neutral answer). */
export default async function Page() {
  const t = await getTranslations("passwordReset");
  return (
    <div className="bg-sand-50">
      <div className="container-page flex justify-center py-12 lg:py-20">
        <div className="w-full max-w-md rounded-panel border border-ink-100 bg-white p-6 shadow-card md:p-10">
          <h1 className="text-h2 text-ink-900">{t("forgotTitle")}</h1>
          <p className="mb-6 mt-2 text-ink-700">{t("forgotText")}</p>
          <ForgotPasswordForm />
        </div>
      </div>
    </div>
  );
}
