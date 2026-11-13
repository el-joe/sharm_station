import { cn } from "@/lib/utils";
import { Loader2 } from "lucide-react";
import type { ButtonHTMLAttributes, ReactNode } from "react";

export type ButtonVariant = "cta" | "primary" | "outline" | "ghost" | "partner" | "danger";
export type ButtonSize = "sm" | "md" | "lg";

const variants: Record<ButtonVariant, string> = {
  cta: "bg-cta-600 text-white hover:bg-cta-700 active:bg-cta-700",
  primary: "bg-primary-700 text-white hover:bg-brand-navy-900",
  outline: "border border-primary-700 text-primary-700 bg-white hover:bg-primary-100",
  ghost: "text-primary-700 hover:bg-primary-100",
  partner: "bg-partner-600 text-white hover:brightness-95",
  danger: "bg-error text-white hover:brightness-95",
};

const sizes: Record<ButtonSize, string> = {
  sm: "h-9 px-3 text-sm",
  md: "h-11 px-5 text-base",
  lg: "h-[52px] px-6 text-base",
};

/** Class helper so links can look like buttons (Figma "Button" component). */
export function buttonClasses(variant: ButtonVariant = "cta", size: ButtonSize = "md", className?: string) {
  return cn(
    "inline-flex select-none items-center justify-center gap-2 rounded-control font-semibold whitespace-nowrap transition-colors disabled:cursor-not-allowed disabled:opacity-50",
    variants[variant],
    sizes[size],
    className,
  );
}

export interface ButtonProps extends ButtonHTMLAttributes<HTMLButtonElement> {
  variant?: ButtonVariant;
  size?: ButtonSize;
  loading?: boolean;
  icon?: ReactNode;
}

export function Button({ variant = "cta", size = "md", loading, icon, className, children, disabled, type = "button", ...props }: ButtonProps) {
  return (
    <button type={type} className={buttonClasses(variant, size, className)} disabled={disabled || loading} aria-busy={loading || undefined} {...props}>
      {loading ? <Loader2 className="size-5 animate-spin" aria-hidden /> : icon}
      {children}
    </button>
  );
}
