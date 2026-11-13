import { cn } from "@/lib/utils";
import type { ReactNode } from "react";

/** Empty / coming-soon / error block used across account and partner pages. */
export function EmptyState({
  icon,
  title,
  text,
  action,
  tone = "neutral",
  className,
}: {
  icon?: ReactNode;
  title: string;
  text?: ReactNode;
  action?: ReactNode;
  tone?: "neutral" | "partner" | "warning";
  className?: string;
}) {
  const tones = {
    neutral: "bg-sand-50 [&_.icon]:bg-white [&_.icon]:text-primary-700",
    partner: "bg-partner-100 [&_.icon]:bg-white [&_.icon]:text-partner-600",
    warning: "bg-warning-100 [&_.icon]:bg-white [&_.icon]:text-warning",
  };
  return (
    <div className={cn("flex flex-col items-center rounded-card px-6 py-10 text-center", tones[tone], className)} role="status">
      {icon && <span className="icon grid size-14 place-items-center rounded-full [&_svg]:size-7">{icon}</span>}
      <h2 className="text-h4 mt-4 text-ink-900">{title}</h2>
      {text && <div className="mt-2 max-w-xl text-ink-700">{text}</div>}
      {action && <div className="mt-6 flex flex-wrap justify-center gap-3">{action}</div>}
    </div>
  );
}
