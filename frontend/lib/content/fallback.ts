/**
 * OFFLINE FALLBACK ONLY. The live texts come from the API (admin "Content" module,
 * see ../api/content.ts). These converters turn the bundled copies in this folder
 * into the API shape so pages still render when the API is unreachable.
 * Do not edit texts here to change the website: edit them in the admin.
 */
import type { BlogPost, ContentPage, FaqCategory, PageSlug } from "../api/content";
import type { Locale } from "../api/types";
import { POSTS } from "./blog";
import { FAQ } from "./faq";
import { PAGES, type DocSection } from "./pages";

const esc = (s: string) => s.replace(/&/g, "&amp;").replace(/</g, "&lt;").replace(/>/g, "&gt;").replace(/"/g, "&quot;");

function sectionsToHtml(sections: { id: string; heading: string; paragraphs: string[]; list?: string[] }[]): string {
  return sections
    .map((s) => {
      const list = (s as DocSection).list?.length ? `<ul>${(s as DocSection).list!.map((li) => `<li>${esc(li)}</li>`).join("")}</ul>` : "";
      return `<h2 id="${esc(s.id)}">${esc(s.heading)}</h2>${s.paragraphs.map((p) => `<p>${esc(p)}</p>`).join("")}${list}`;
    })
    .join("\n");
}

export function PAGE_FALLBACK(slug: PageSlug, locale: Locale): ContentPage {
  const doc = PAGES[slug][locale];
  return {
    slug,
    locale,
    title: doc.title,
    intro: doc.intro,
    body_html: sectionsToHtml(doc.sections),
    seo: { title: doc.title, description: doc.description },
    updated_at: doc.updated,
  };
}

export function BLOG_FALLBACK(locale: Locale): BlogPost[] {
  return POSTS.map((p) => {
    const c = p.locales[locale];
    return {
      slug: p.slug,
      locale,
      title: c.title,
      excerpt: c.excerpt,
      cover_image: p.image,
      body_html: sectionsToHtml(c.sections),
      author: p.author,
      published_at: p.published,
      tags: c.tags,
      reading_minutes: p.readMinutes,
      related: p.related.map((r) => ({ label: r.label[locale], href: r.href })),
      seo: { title: c.title, description: c.excerpt },
    };
  });
}

export function FAQ_FALLBACK(locale: Locale): FaqCategory[] {
  return FAQ[locale].map((c) => ({ id: c.id, category: c.title, items: c.items.map((i) => ({ question: i.question, answer_html: `<p>${esc(i.answer)}</p>` })) }));
}
