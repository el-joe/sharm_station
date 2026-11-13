import { cn } from "@/lib/utils";
import { getTranslations } from "next-intl/server";
import type { ReactNode } from "react";
import { Breadcrumbs } from "./Breadcrumbs";

/** Breadcrumbs + display title + subtitle used by account, content and legal pages (Figma "Page intro"). */
export async function PageIntro({
  crumbs,
  title,
  subtitle,
  children,
  className,
}: {
  crumbs: { label: string; href?: string }[];
  title: string;
  subtitle?: ReactNode;
  children?: ReactNode;
  className?: string;
}) {
  const tc = await getTranslations("common");
  return (
    <div className={cn("pb-6 pt-6 lg:pt-10", className)}>
      <Breadcrumbs className="text-ink-700" label={tc("breadcrumbs")} items={[{ label: tc("home"), href: "/" }, ...crumbs]} />
      <h1 className="text-h1 mt-3 max-w-4xl text-ink-900">{title}</h1>
      {subtitle && <p className="mt-2 max-w-3xl text-ink-700 lg:text-lg">{subtitle}</p>}
      {children}
    </div>
  );
}
