import { buttonClasses } from "@/components/ui/Button";
import { Link } from "@/i18n/navigation";
import { Compass } from "lucide-react";
import { getTranslations } from "next-intl/server";

export default async function NotFound() {
  const t = await getTranslations("notFound");
  return (
    <section className="container-page flex flex-col items-center py-24 text-center lg:py-32">
      <span className="grid size-20 place-items-center rounded-full bg-brand-100 text-brand-700">
        <Compass className="size-10" aria-hidden />
      </span>
      <p className="text-eyebrow mt-6 text-brand-700">404</p>
      <h1 className="text-h1 mt-2 text-ink-900">{t("title")}</h1>
      <p className="mt-3 max-w-lg text-ink-700">{t("text")}</p>
      <div className="mt-8 flex flex-wrap justify-center gap-3">
        <Link href="/" className={buttonClasses("cta", "lg")}>
          {t("home")}
        </Link>
        <Link href="/trips" className={buttonClasses("outline", "lg")}>
          {t("trips")}
        </Link>
      </div>
    </section>
  );
}
