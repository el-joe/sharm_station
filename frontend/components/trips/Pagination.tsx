import { Link } from "@/i18n/navigation";
import { cn } from "@/lib/utils";
import { ChevronLeft, ChevronRight } from "lucide-react";
import { useTranslations } from "next-intl";

export function Pagination({ page, lastPage, pathname, query }: { page: number; lastPage: number; pathname: string; query: Record<string, string> }) {
  const t = useTranslations("pagination");
  if (lastPage <= 1) return null;
  const href = (p: number) => ({ pathname, query: { ...query, ...(p > 1 ? { page: String(p) } : {}) } });
  const pages = Array.from({ length: lastPage }, (_, i) => i + 1);
  const base = "grid size-11 place-items-center rounded-control font-semibold";
  return (
    <nav aria-label={t("label")} className="mt-10 flex justify-center">
      <ul className="flex items-center gap-1">
        <li>
          {page > 1 ? (
            <Link href={href(page - 1)} className={cn(base, "hover:bg-ink-100")} aria-label={t("previous")}>
              <ChevronLeft className="size-5 rtl:rotate-180" aria-hidden />
            </Link>
          ) : (
            <span className={cn(base, "text-ink-300")} aria-hidden><ChevronLeft className="size-5 rtl:rotate-180" /></span>
          )}
        </li>
        {pages.map((p) => (
          <li key={p}>
            <Link href={href(p)} aria-current={p === page ? "page" : undefined} className={cn(base, p === page ? "bg-primary-700 text-white" : "hover:bg-ink-100")}>
              {p}
            </Link>
          </li>
        ))}
        <li>
          {page < lastPage ? (
            <Link href={href(page + 1)} className={cn(base, "hover:bg-ink-100")} aria-label={t("next")}>
              <ChevronRight className="size-5 rtl:rotate-180" aria-hidden />
            </Link>
          ) : (
            <span className={cn(base, "text-ink-300")} aria-hidden><ChevronRight className="size-5 rtl:rotate-180" /></span>
          )}
        </li>
      </ul>
    </nav>
  );
}
