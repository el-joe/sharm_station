import { cn } from "@/lib/utils";
import type { ReactNode } from "react";

export function DetailSection({ id, eyebrow, title, children, className, aside }: { id: string; eyebrow?: string; title: string; children: ReactNode; className?: string; aside?: ReactNode }) {
  return (
    <section id={id} aria-labelledby={`${id}-title`} className={cn("scroll-mt-40", className)}>
      {eyebrow && <p className="text-eyebrow mb-3 text-brand-700">{eyebrow}</p>}
      <div className="mb-6 flex items-center justify-between gap-4">
        <h2 id={`${id}-title`} className="text-h3 text-ink-900">
          {title}
        </h2>
        {aside}
      </div>
      {children}
    </section>
  );
}
