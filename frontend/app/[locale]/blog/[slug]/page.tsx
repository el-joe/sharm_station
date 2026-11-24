import { AppImage } from "@/components/ui/AppImage";
import { buttonClasses } from "@/components/ui/Button";
import { PageIntro } from "@/components/ui/PageIntro";
import { Link } from "@/i18n/navigation";
import { RichHtml } from "@/components/content/RichHtml";
import { getBlogPost, getBlogSlugs, tocFromHtml } from "@/lib/api/content";
import type { Locale } from "@/lib/api/types";
import { pageMetadata } from "@/lib/seo";
import { SITE_NAME, SITE_URL } from "@/lib/site";
import { ArrowLeft } from "lucide-react";
import type { Metadata } from "next";
import { getFormatter, getTranslations } from "next-intl/server";
import { notFound } from "next/navigation";
import { Suspense } from "react";

export async function generateStaticParams() {
  return (await getBlogSlugs()).map((slug) => ({ slug }));
}

export async function generateMetadata({ params }: PageProps<"/[locale]/blog/[slug]">): Promise<Metadata> {
  const { locale, slug } = await params;
  const post = await getBlogPost(slug, locale as Locale);
  if (!post) return {};
  const meta = pageMetadata({ locale, path: `/blog/${slug}`, title: post.seo.title, description: post.seo.description, image: post.cover_image });
  return { ...meta, openGraph: { ...meta.openGraph, type: "article", publishedTime: post.published_at, authors: post.author ? [post.author] : undefined, tags: post.tags } };
}

export default function Page({ params }: PageProps<"/[locale]/blog/[slug]">) {
  return (
    <Suspense fallback={<div className="container-page h-[600px] animate-pulse bg-ink-100" aria-hidden />}>
      <Article params={params} />
    </Suspense>
  );
}

async function Article({ params }: { params: PageProps<"/[locale]/blog/[slug]">["params"] }) {
  const { locale: l, slug } = await params;
  const locale = l as Locale;
  const post = await getBlogPost(slug, locale);
  if (!post) notFound();
  const c = post;
  const toc = tocFromHtml(post.body_html);
  const image = post.cover_image.startsWith("http") ? post.cover_image : `${SITE_URL}${post.cover_image}`;
  const [t, format] = await Promise.all([getTranslations("blog"), getFormatter()]);
  const jsonLd = {
    "@context": "https://schema.org",
    "@type": "BlogPosting",
    headline: c.title,
    description: c.excerpt,
    image,
    datePublished: post.published_at,
    author: { "@type": "Person", name: post.author || SITE_NAME },
    keywords: post.tags.join(", ") || undefined,
    publisher: { "@type": "Organization", name: SITE_NAME },
    inLanguage: locale,
    mainEntityOfPage: `${SITE_URL}/${locale}/blog/${slug}`,
  };
  return (
    <div className="container-page pb-16">
      <script type="application/ld+json" dangerouslySetInnerHTML={{ __html: JSON.stringify(jsonLd).replace(/</g, "\\u003c") }} />
      <PageIntro crumbs={[{ label: t("crumb"), href: "/blog" }, { label: c.title }]} title={c.title} subtitle={c.excerpt}>
        <p className="mt-3 text-sm text-ink-500">
          {post.author && <>{t("by", { author: post.author })} · </>}<time dateTime={post.published_at}>{format.dateTime(new Date(post.published_at), { day: "numeric", month: "long", year: "numeric", timeZone: "UTC" })}</time> · {t("readTime", { count: post.reading_minutes })}
        </p>
      </PageIntro>
      <div className="relative aspect-[16/9] overflow-hidden rounded-card bg-ink-100 md:aspect-[16/6]">
        <AppImage src={post.cover_image} alt="" fill preload sizes="(min-width: 1248px) 1200px, 100vw" className="object-cover" />
      </div>
      <div className="mt-10 grid grid-cols-[minmax(0,1fr)] gap-10 lg:grid-cols-[240px_minmax(0,1fr)]">
        <aside className="lg:sticky lg:top-32 lg:self-start">
          {toc.length > 0 && (
          <nav aria-label={t("inThisGuide")} className="rounded-card bg-sand-50 p-5">
            <h2 className="text-eyebrow text-ink-500">{t("inThisGuide")}</h2>
            <ol className="mt-3 flex flex-col gap-1 text-sm">
              {toc.map((s) => (
                <li key={s.id}><a href={`#${s.id}`} className="block py-1.5 text-ink-700 hover:text-primary-700 hover:underline">{s.heading}</a></li>
              ))}
            </ol>
          </nav>
          )}
        </aside>
        <article className="max-w-3xl">
          <RichHtml html={post.body_html} className="pb-8" />
          {post.related.length > 0 && (
          <section className="rounded-card bg-sand-50 p-6" aria-labelledby="related-h">
            <h2 id="related-h" className="font-display text-xl font-semibold text-ink-900">{t("related")}</h2>
            <div className="mt-4 flex flex-wrap gap-3">
              {post.related.map((r) => <Link key={r.href} href={r.href} className={buttonClasses("outline", "md")}>{r.label}</Link>)}
            </div>
          </section>
          )}
          <Link href="/blog" className="mt-8 inline-flex items-center gap-1 font-semibold text-primary-700 hover:underline">
            <ArrowLeft className="size-4 rtl:rotate-180" aria-hidden /> {t("back")}
          </Link>
        </article>
      </div>
    </div>
  );
}
