import type { Metadata } from "next";
import { routing } from "@/i18n/routing";
import { SITE_NAME, SITE_URL } from "./site";

/**
 * Per-page metadata with canonical + hreflang alternates for every locale.
 * `path` is the locale-less pathname, e.g. "/trips/pyramids".
 */
export function pageMetadata({
  locale,
  path,
  title,
  description,
  image,
  noindex,
}: {
  locale: string;
  path: string;
  title: string;
  description: string;
  image?: string;
  noindex?: boolean;
}): Metadata {
  const clean = path === "/" ? "" : path;
  const languages: Record<string, string> = Object.fromEntries(routing.locales.map((l) => [l, `${SITE_URL}/${l}${clean}`]));
  languages["x-default"] = `${SITE_URL}/${routing.defaultLocale}${clean}`;
  const url = `${SITE_URL}/${locale}${clean}`;
  const images = image ? [{ url: image.startsWith("http") ? image : `${SITE_URL}${image}` }] : undefined;
  return {
    title,
    description,
    alternates: { canonical: url, languages },
    openGraph: {
      type: "website",
      siteName: SITE_NAME,
      title,
      description,
      url,
      locale: locale === "it" ? "it_IT" : "en_GB",
      images,
    },
    twitter: { card: "summary_large_image", title, description, images: images?.map((i) => i.url) },
    robots: noindex ? { index: false, follow: false } : undefined,
  };
}
