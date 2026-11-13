import type { ReactNode } from "react";

export function QuickFacts({ items }: { items: { icon: ReactNode; label: string; value: string }[] }) {
  return (
    <ul className="mt-6 grid grid-cols-2 gap-3 md:grid-cols-4">
      {items.map((f) => (
        <li key={f.label} className="rounded-image border border-ink-100 bg-white p-4 shadow-card">
          <span className="grid size-8 place-items-center rounded-full bg-brand-100 text-brand-700 [&_svg]:size-4" aria-hidden>
            {f.icon}
          </span>
          <p className="text-eyebrow mt-3 text-ink-500">{f.label}</p>
          <p className="mt-1 text-sm text-ink-900 md:text-base">{f.value}</p>
        </li>
      ))}
    </ul>
  );
}
