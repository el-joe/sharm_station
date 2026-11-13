import { CheckCircle2 } from "lucide-react";
import { useTranslations } from "next-intl";

/**
 * Hero trust chips. Desktop: a row of translucent pills. Mobile: a 2x2 glass
 * grid where every label stays on one line (Figma HomeHero 60:14593).
 */
export function TrustChips({ rating }: { rating: number }) {
  const t = useTranslations("hero.trust");
  const items = [t("freeCancellation"), t("deposit"), t("secure"), t("google", { rating })];
  return (
    <ul className="grid w-full grid-cols-2 gap-x-2 gap-y-3 rounded-card border border-white/25 bg-white/10 p-3 backdrop-blur-md md:flex md:w-auto md:flex-wrap md:justify-center md:gap-3 md:border-0 md:bg-transparent md:p-0 md:backdrop-blur-none">
      {items.map((label) => (
        <li key={label} className="flex min-w-0 items-center gap-1.5 text-white md:rounded-pill md:bg-white/20 md:py-2 md:ps-3 md:pe-4">
          <CheckCircle2 className="size-4 shrink-0 md:size-5" aria-hidden />
          <span className="whitespace-nowrap text-[13px] leading-5 xs:text-sm">{label}</span>
        </li>
      ))}
    </ul>
  );
}
