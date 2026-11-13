import { AppImage } from "@/components/ui/AppImage";
import { PageIntro } from "@/components/ui/PageIntro";
import { Link } from "@/i18n/navigation";
import type { Locale } from "@/lib/api/types";
import { POSTS } from "@/lib/content/blog";
import { pageMetadata } from "@/lib/seo";
import { parseISODate } from "@/lib/utils";
import { ArrowRight } from "lucide-react";
import type { Metadata } from "next";
import { getFormatter, getTranslations } from "next-intl/server";
import { locale as rootLocale } from "next/root-params";

export async function generateMetadata(): Promise<Metadata> {
  const locale = await rootLocale();
  const t = await getTranslations("blog");
  return pageMetadata({ locale, path: "/blog", title: t("metaTitle"), description: t("subtitle"), image: POSTS[0]?.image });
}

export default async function Page() {
  const locale = (await rootLocale()) as Locale;
  const [t, format] = await Promise.all([getTranslations("blog"), getFormatter()]);
  const posts = [...POSTS].sort((a, b) => b.published.localeCompare(a.published));
  return (
    <div className="container-page pb-16">
      <PageIntro crumbs={[{ label: t("crumb") }]} title={t("title")} subtitle={t("subtitle")} />
      <ul className="grid gap-8 md:grid-cols-2">
        {posts.map((p, i) => {
          const c = p.locales[locale];
          return (
            <li key={p.slug}>
              <article className="group relative flex h-full flex-col overflow-hidden rounded-card border border-ink-100 bg-white shadow-card transition-shadow hover:shadow-raised">
                <div className="relative aspect-[16/9] overflow-hidden bg-ink-100">
                  <AppImage src={p.image} alt="" fill preload={i === 0} sizes="(min-width: 768px) 600px, 100vw" className="object-cover transition-transform duration-500 group-hover:scale-105" />
                  {i === 0 && <span className="text-eyebrow absolute start-4 top-4 rounded-pill bg-gold-500 px-2.5 py-1 text-brand-navy-900">{t("featured")}</span>}
                </div>
                <div className="flex flex-1 flex-col gap-3 p-6">
                  <p className="text-sm text-ink-500">
                    <time dateTime={p.published}>{format.dateTime(parseISODate(p.published), { day: "numeric", month: "long", year: "numeric" })}</time> · {t("readTime", { count: p.readMinutes })}
                  </p>
                  <h2 className="font-display text-2xl font-semibold text-ink-900">
                    <Link href={`/blog/${p.slug}`} className="after:absolute after:inset-0 after:rounded-card">{c.title}</Link>
                  </h2>
                  <p className="text-ink-700">{c.excerpt}</p>
                  <ul className="flex flex-wrap gap-2">
                    {c.tags.map((tag) => <li key={tag} className="rounded-pill bg-primary-100 px-2.5 py-0.5 text-xs font-medium text-primary-700">{tag}</li>)}
                  </ul>
                  <span className="mt-auto inline-flex items-center gap-1 pt-2 font-semibold text-primary-700" aria-hidden>
                    {t("readMore")} <ArrowRight className="size-4 rtl:rotate-180" />
                  </span>
                </div>
              </article>
            </li>
          );
        })}
      </ul>
    </div>
  );
}
