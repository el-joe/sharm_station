import { cn } from "@/lib/utils";

/** Sharm Station Viaggi wordmark (sun over waves + name). */
export function Logo({ className, inverted = false }: { className?: string; inverted?: boolean }) {
  return (
    <span className={cn("inline-flex items-center gap-2", className)}>
      <svg viewBox="0 0 40 40" className="size-9 shrink-0" aria-hidden>
        <circle cx="20" cy="20" r="18" fill="none" stroke="#ee8431" strokeWidth="2.5" />
        <path d="M8 19a12 12 0 0 1 24 0z" fill="#ee8431" />
        <path d="M6 24c4-2 8 2 14 0s10-2 14 0M8 29c4-2 8 2 12 0s8-2 12 0" fill="none" stroke="#ee8431" strokeWidth="2" strokeLinecap="round" />
      </svg>
      <span className="flex flex-col leading-none">
        <span className={cn("text-[17px] font-bold tracking-tight", inverted ? "text-white" : "text-primary-700")}>Sharm</span>
        <span className="text-[17px] font-medium tracking-tight text-gold-500">Station</span>
        <span className={cn("font-display text-[10px] italic", inverted ? "text-white/80" : "text-ink-700")}>Viaggi</span>
      </span>
    </span>
  );
}
