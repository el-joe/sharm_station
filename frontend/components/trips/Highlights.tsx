export function Highlights({ items }: { items: string[] }) {
  return (
    <ol className="grid gap-3 md:grid-cols-2">
      {items.map((h, i) => (
        <li key={h} className="flex items-center gap-3 rounded-image border border-ink-100 bg-white p-4 shadow-card">
          <span className="grid size-8 shrink-0 place-items-center rounded-full bg-gold-500 text-sm font-medium text-brand-navy-900" aria-hidden>
            {String(i + 1).padStart(2, "0")}
          </span>
          <span className="text-sm text-ink-900 md:text-base">{h}</span>
        </li>
      ))}
    </ol>
  );
}
