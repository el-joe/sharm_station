import { Link } from "@/i18n/navigation";
import { cn } from "@/lib/utils";
import { Fragment } from "react";

export function Breadcrumbs({ items, label, className }: { items: { label: string; href?: string }[]; label: string; className?: string }) {
  return (
    <nav aria-label={label} className={cn("text-eyebrow", className)}>
      <ol className="flex flex-wrap items-center gap-2">
        {items.map((item, i) => (
          <Fragment key={i}>
            <li>
              {item.href ? (
                <Link href={item.href} className="hover:underline">
                  {item.label}
                </Link>
              ) : (
                <span aria-current="page">{item.label}</span>
              )}
            </li>
            {i < items.length - 1 && (
              <li aria-hidden className="opacity-70">
                /
              </li>
            )}
          </Fragment>
        ))}
      </ol>
    </nav>
  );
}
