/**
 * Editorial content from the API (admin "Content" module): static pages, blog and FAQ.
 *
 * Every getter is a Cache Components entry ('use cache') keyed by its arguments
 * (slug + locale), tagged so the admin can revalidate it ("content", "pages",
 * `page:{slug}`, "blog", `blog:{slug}`, "faqs") and kept for minutes.
 *
 * OFFLINE FALLBACK ONLY: when the API is unreachable (network error, timeout,
 * 5xx or a malformed answer) the texts bundled in ../content/* are served
 * instead, converted to the API shape, so legal pages never go blank. A 404
 * from the API is a real "not found" and is never masked by the fallback.
 */
import { cacheLife, cacheTag } from "next/cache";
import { BLOG_FALLBACK, FAQ_FALLBACK, PAGE_FALLBACK } from "../content/fallback";
import { API_URL, PUBLIC_API_URL, USE_MOCKS } from "../site";
import type { Locale } from "./types";

export type PageSlug = "about" | "booking-terms" | "privacy" | "cancellation-policy" | "cookie-policy";

export interface ContentSeo {
  title: string;
  description: string;
}

export interface ContentPage {
  slug: PageSlug;
  locale: Locale;
  title: string;
  intro: string;
  body_html: string;
  seo: ContentSeo;
  updated_at: string | null;
}

export interface BlogPost {
  slug: string;
  locale: Locale;
  title: string;
  excerpt: string;
  cover_image: string;
  body_html: string;
  author: string;
  published_at: string;
  tags: string[];
  reading_minutes: number;
  related: { label: string; href: string }[];
  seo: ContentSeo;
}

export interface FaqCategory {
  id: string;
  category: string;
  items: { question: string; answer_html: string }[];
}

export interface TocItem {
  id: string;
  heading: string;
}

class ContentNotFound extends Error {}

async function getJson<T>(path: string, locale: Locale, query: Record<string, string> = {}): Promise<T> {
  const url = new URL(`${API_URL}${path}`);
  url.searchParams.set("locale", locale);
  for (const [k, v] of Object.entries(query)) url.searchParams.set(k, v);
  const res = await fetch(url, { headers: { Accept: "application/json", "Accept-Language": locale }, signal: AbortSignal.timeout(5000) });
  if (res.status === 404) throw new ContentNotFound(path);
  if (!res.ok) throw new Error(`GET ${path} -> ${res.status}`);
  return (await res.json()) as T;
}

/** API call with the offline fallback; `null` on 404. */
async function withFallback<T>(path: string, call: () => Promise<T>, fallback: () => T): Promise<T | null> {
  if (USE_MOCKS) return fallback();
  try {
    return await call();
  } catch (error) {
    if (error instanceof ContentNotFound) return null;
    console.warn(`[content] API unavailable for ${path}, serving the bundled offline copy: ${(error as Error).message}`);
    return fallback();
  }
}

const API_ORIGIN = (() => {
  try {
    return new URL(PUBLIC_API_URL).origin;
  } catch {
    return "";
  }
})();

/** Cover images: local /images/*, API storage paths or absolute URLs. */
export function contentImage(path: string | null | undefined): string {
  if (!path) return "/images/nile-cruise.webp";
  if (/^https?:\/\//.test(path)) return path;
  if (path.startsWith("/storage/")) return `${API_ORIGIN}${path}`;
  return path.startsWith("/") ? path : `/${path}`;
}

function str(v: unknown, fallback = ""): string {
  return typeof v === "string" ? v : fallback;
}

function mapSeo(raw: unknown, title: string, description: string): ContentSeo {
  const s = (raw ?? {}) as Record<string, unknown>;
  return { title: str(s.title) || title, description: str(s.description) || description };
}

function mapPost(raw: Record<string, unknown>, locale: Locale): BlogPost {
  const title = str(raw.title);
  const excerpt = str(raw.excerpt);
  return {
    slug: str(raw.slug),
    locale,
    title,
    excerpt,
    cover_image: contentImage(raw.cover_image as string | null),
    body_html: str(raw.body_html),
    author: str(raw.author),
    published_at: str(raw.published_at),
    tags: Array.isArray(raw.tags) ? raw.tags.map(String) : [],
    reading_minutes: Number(raw.reading_minutes ?? 1) || 1,
    related: Array.isArray(raw.related)
      ? (raw.related as { label?: unknown; href?: unknown }[]).filter((r) => typeof r?.href === "string" && r.href.startsWith("/")).map((r) => ({ label: str(r.label), href: str(r.href) }))
      : [],
    seo: mapSeo(raw.seo, title, excerpt),
  };
}

/* ------------------------------------------------------------------ getters */

export async function getPage(slug: PageSlug, locale: Locale): Promise<ContentPage | null> {
  "use cache";
  cacheLife("minutes");
  cacheTag("content", "pages", `page:${slug}`);
  return withFallback(
    `/pages/${slug}`,
    async () => {
      const raw = (await getJson<{ data: Record<string, unknown> }>(`/pages/${slug}`, locale)).data;
      if (!raw || typeof raw.body_html !== "string") throw new Error("Unexpected page shape");
      const title = str(raw.title);
      const intro = str(raw.intro);
      return { slug, locale, title, intro, body_html: raw.body_html, seo: mapSeo(raw.seo, title, intro), updated_at: (raw.updated_at as string | null) ?? null };
    },
    () => PAGE_FALLBACK(slug, locale),
  );
}

export async function getBlogPosts(locale: Locale): Promise<BlogPost[]> {
  "use cache";
  cacheLife("minutes");
  cacheTag("content", "blog");
  const posts = await withFallback(
    "/blog",
    async () => {
      const raw = await getJson<{ data: Record<string, unknown>[] }>("/blog", locale, { per_page: "50" });
      if (!Array.isArray(raw.data)) throw new Error("Unexpected blog shape");
      return raw.data.map((p) => mapPost(p, locale));
    },
    () => BLOG_FALLBACK(locale),
  );
  return [...(posts ?? [])].sort((a, b) => b.published_at.localeCompare(a.published_at));
}

export async function getBlogPost(slug: string, locale: Locale): Promise<BlogPost | null> {
  "use cache";
  cacheLife("minutes");
  cacheTag("content", "blog", `blog:${slug}`);
  return withFallback(
    `/blog/${slug}`,
    async () => {
      const raw = (await getJson<{ data: Record<string, unknown> }>(`/blog/${encodeURIComponent(slug)}`, locale)).data;
      if (!raw || typeof raw.body_html !== "string") throw new Error("Unexpected post shape");
      return mapPost(raw, locale);
    },
    () => BLOG_FALLBACK(locale).find((p) => p.slug === slug) ?? null,
  );
}

export async function getFaqs(locale: Locale): Promise<FaqCategory[]> {
  "use cache";
  cacheLife("minutes");
  cacheTag("content", "faqs");
  const faqs = await withFallback(
    "/faqs",
    async () => {
      const raw = await getJson<{ data: Record<string, unknown>[] }>("/faqs", locale);
      if (!Array.isArray(raw.data)) throw new Error("Unexpected FAQ shape");
      return raw.data.map((c, i) => ({
        id: str(c.id) || `category-${i + 1}`,
        category: str(c.category),
        items: Array.isArray(c.items) ? (c.items as Record<string, unknown>[]).map((it) => ({ question: str(it.question), answer_html: str(it.answer_html) })) : [],
      }));
    },
    () => FAQ_FALLBACK(locale),
  );
  return faqs ?? [];
}

/** Blog slugs for generateStaticParams and the sitemap (never empty: Cache Components needs one param). */
export async function getBlogSlugs(): Promise<string[]> {
  const posts = await getBlogPosts("en");
  return posts.length ? posts.map((p) => p.slug) : BLOG_FALLBACK("en").map((p) => p.slug);
}

/* ------------------------------------------------------------------ html helpers */

const decode = (s: string) =>
  s
    .replace(/<[^>]*>/g, "")
    .replace(/&#0?39;|&apos;/g, "'")
    .replace(/&quot;/g, '"')
    .replace(/&lt;/g, "<")
    .replace(/&gt;/g, ">")
    .replace(/&nbsp;/g, " ")
    .replace(/&amp;/g, "&")
    .trim();

/** "On this page" entries: every <h2 id="…"> in the body. */
export function tocFromHtml(html: string): TocItem[] {
  return [...html.matchAll(/<h2\b[^>]*\bid="([^"]+)"[^>]*>([\s\S]*?)<\/h2>/gi)].map((m) => ({ id: m[1], heading: decode(m[2]) }));
}

/** Plain text of an HTML fragment (JSON-LD answers, meta descriptions). */
export function htmlToText(html: string): string {
  return decode(html.replace(/<\/(p|li|h\d)>/gi, " ")).replace(/\s+/g, " ");
}
