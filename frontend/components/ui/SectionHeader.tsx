import { Link } from "@/i18n/navigation";
import { cn } from "@/lib/utils";
import { ArrowRight } from "lucide-react";
import type { ComponentProps } from "react";

const linkClass = "group inline-flex shrink-0 items-center gap-1.5 font-semibold text-primary-700 hover:underline";

export function SectionHeader({
  title,
  subtitle,
  eyebrow,
  link,
  className,
  as: Heading = "h2",
}: {
  title: string;
  subtitle?: string;
  eyebrow?: string;
  link?: { href: ComponentProps<typeof Link>["href"]; label: string };
  className?: string;
  as?: "h1" | "h2";
}) {
  return (
    <div className={cn("mb-8 flex flex-col gap-4 md:mb-12 md:flex-row md:items-end md:justify-between", className)}>
      <div className="max-w-3xl">
        {eyebrow && <p className="text-eyebrow mb-3 text-brand-700">{eyebrow}</p>}
        <Heading className="text-h2 text-ink-900">{title}</Heading>
        {subtitle && <p className="mt-2 text-base text-ink-700 lg:text-lg">{subtitle}</p>}
      </div>
      {link &&
        (typeof link.href === "string" && /^https?:/.test(link.href) ? (
          <a href={link.href} target="_blank" rel="noopener noreferrer" className={linkClass}>
            {link.label}
            <ArrowRight className="size-4 transition-transform group-hover:translate-x-0.5 rtl:rotate-180" aria-hidden />
          </a>
        ) : (
          <Link href={link.href} className={linkClass}>
            {link.label}
            <ArrowRight className="size-4 transition-transform group-hover:translate-x-0.5 rtl:rotate-180" aria-hidden />
          </Link>
        ))}
    </div>
  );
}
