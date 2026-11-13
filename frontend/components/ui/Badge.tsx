import { cn } from "@/lib/utils";
import { AlertTriangle, Bus, CheckCircle2, CreditCard, Star, UtensilsCrossed } from "lucide-react";
import type { ReactNode } from "react";

export type BadgeTone = "success" | "warning" | "brand" | "info" | "partner" | "navy" | "neutral";

const tones: Record<BadgeTone, string> = {
  success: "bg-success-100 text-ink-900 [&_svg]:text-success",
  warning: "bg-warning-100 text-ink-900 [&_svg]:text-warning",
  brand: "bg-brand-100 text-ink-900 [&_svg]:text-brand-700",
  info: "bg-primary-100 text-ink-900 [&_svg]:text-primary-700",
  partner: "bg-partner-100 text-partner-600",
  navy: "bg-primary-700 text-white",
  neutral: "bg-white text-ink-900",
};

export function Badge({ tone = "success", icon, children, className }: { tone?: BadgeTone; icon?: ReactNode; children: ReactNode; className?: string }) {
  return (
    <span className={cn("inline-flex h-7 items-center gap-1 rounded-pill px-3 text-xs font-medium whitespace-nowrap [&_svg]:size-4 [&_svg]:shrink-0", tones[tone], className)}>
      {icon}
      {children}
    </span>
  );
}

/** Preset badges used on cards (Figma Badge component, 8 types). */
export const BadgeIcons = {
  freeCancellation: <CheckCircle2 aria-hidden />,
  taxIncluded: <CheckCircle2 aria-hidden />,
  fewLeft: <AlertTriangle aria-hidden />,
  breakfast: <UtensilsCrossed aria-hidden />,
  bestSeller: <Star aria-hidden className="fill-current" />,
  deposit: <CreditCard aria-hidden />,
  transfers: <Bus aria-hidden />,
};
