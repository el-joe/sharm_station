import { RichHtml } from "@/components/content/RichHtml";
import { ChevronDown } from "lucide-react";

/** Accordion of questions. `answer` is plain text (product FAQ); `answer_html` is editorial HTML from the API (FAQ page). */
export function Faq({ items }: { items: { question: string; answer?: string; answer_html?: string }[] }) {
  return (
    <div className="divide-y divide-ink-100 rounded-card border border-ink-100 bg-white">
      {items.map((f) => (
        <details key={f.question} className="group px-5">
          <summary className="flex min-h-14 cursor-pointer list-none items-center justify-between gap-4 py-3 font-semibold text-ink-900 [&::-webkit-details-marker]:hidden">
            {f.question}
            <ChevronDown className="size-5 shrink-0 transition-transform group-open:rotate-180" aria-hidden />
          </summary>
          {f.answer_html ? <RichHtml html={f.answer_html} className="rich-text-compact pb-4" /> : <p className="pb-4 text-ink-700">{f.answer}</p>}
        </details>
      ))}
    </div>
  );
}
