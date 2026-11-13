import { cn } from "@/lib/utils";
import { Star } from "lucide-react";

/** Read-only star rating. `label` is the accessible text (e.g. "4.8 out of 5"). */
export function Stars({ rating, size = 16, className, label }: { rating: number; size?: number; className?: string; label: string }) {
  return (
    <span className={cn("inline-flex items-center gap-0.5", className)} role="img" aria-label={label}>
      {Array.from({ length: 5 }, (_, i) => {
        const fill = Math.max(0, Math.min(1, rating - i));
        return (
          <span key={i} className="relative inline-block" style={{ width: size, height: size }} aria-hidden>
            <Star className="absolute inset-0 text-ink-300" style={{ width: size, height: size }} />
            <span className="absolute inset-0 overflow-hidden" style={{ width: `${fill * 100}%` }}>
              <Star className="fill-gold-500 text-gold-500" style={{ width: size, height: size }} />
            </span>
          </span>
        );
      })}
    </span>
  );
}
