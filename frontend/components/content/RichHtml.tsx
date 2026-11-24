import { cn } from "@/lib/utils";

/**
 * Renders editorial HTML from the API (pages, blog bodies, FAQ answers).
 *
 * The backend already sanitizes body_html when it is saved. As defence in depth
 * this component re-filters it against a strict allowlist before using
 * dangerouslySetInnerHTML: unknown tags are dropped (their text is kept),
 * <script>/<style>/<iframe>… are removed with their content, only the listed
 * attributes survive, and links may only point to http(s), mailto, tel,
 * relative paths or #anchors.
 */
const ALLOWED_TAGS = new Set(["h2", "h3", "h4", "p", "ul", "ol", "li", "a", "strong", "b", "em", "i", "u", "br", "blockquote", "table", "thead", "tbody", "tr", "th", "td", "hr", "span", "small", "sup", "sub"]);
const ALLOWED_ATTRS: Record<string, Set<string>> = {
  "*": new Set(["id"]),
  a: new Set(["href", "title", "target", "rel"]),
  th: new Set(["scope", "colspan", "rowspan"]),
  td: new Set(["colspan", "rowspan"]),
};
const DROP_WITH_CONTENT = /<(script|style|iframe|object|embed|noscript|template|svg|math)\b[\s\S]*?<\/\1\s*>/gi;
const SAFE_HREF = /^(https?:|mailto:|tel:|\/|#)/i;

function filterAttributes(tag: string, attrs: string): string {
  const out: string[] = [];
  for (const m of attrs.matchAll(/([a-zA-Z_:][-a-zA-Z0-9_:.]*)\s*(?:=\s*("([^"]*)"|'([^']*)'|([^\s"'=<>`]+)))?/g)) {
    const name = m[1].toLowerCase();
    const value = m[3] ?? m[4] ?? m[5] ?? "";
    if (!ALLOWED_ATTRS["*"].has(name) && !ALLOWED_ATTRS[tag]?.has(name)) continue;
    if (name === "href" && !SAFE_HREF.test(value.trim())) continue;
    if (name === "target" && value !== "_blank") continue;
    out.push(`${name}="${value.replace(/"/g, "&quot;")}"`);
  }
  if (tag === "a" && /target="_blank"/.test(out.join(" ")) && !out.some((a) => a.startsWith("rel="))) out.push('rel="noopener noreferrer"');
  return out.length ? ` ${out.join(" ")}` : "";
}

export function sanitizeHtml(html: string): string {
  return html
    .replace(/<!--[\s\S]*?-->/g, "")
    .replace(DROP_WITH_CONTENT, "")
    .replace(/<\/?([a-zA-Z][a-zA-Z0-9]*)\b([^>]*)>/g, (full, rawTag: string, attrs: string) => {
      const tag = rawTag.toLowerCase();
      if (!ALLOWED_TAGS.has(tag)) return "";
      if (full.startsWith("</")) return `</${tag}>`;
      return `<${tag}${filterAttributes(tag, attrs)}>`;
    });
}

export function RichHtml({ html, className }: { html: string; className?: string }) {
  // Trusted editorial HTML (sanitized server-side by the API and again by sanitizeHtml above).
  return <div className={cn("rich-text", className)} dangerouslySetInnerHTML={{ __html: sanitizeHtml(html) }} />;
}
