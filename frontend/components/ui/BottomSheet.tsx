"use client";

import { cn } from "@/lib/utils";
import { X } from "lucide-react";
import { useEffect, useId, useRef, type ReactNode } from "react";

/**
 * Mobile bottom sheet built on the native <dialog> (focus trap, Esc, inert background).
 */
export function BottomSheet({
  open,
  onClose,
  title,
  closeLabel,
  children,
  footer,
  className,
}: {
  open: boolean;
  onClose: () => void;
  title: string;
  closeLabel: string;
  children: ReactNode;
  footer?: ReactNode;
  className?: string;
}) {
  const ref = useRef<HTMLDialogElement>(null);
  const titleId = useId();

  useEffect(() => {
    const dialog = ref.current;
    if (!dialog) return;
    if (open && !dialog.open) dialog.showModal();
    if (!open && dialog.open) dialog.close();
  }, [open]);

  return (
    <dialog
      ref={ref}
      aria-labelledby={titleId}
      onClose={onClose}
      onCancel={(e) => {
        e.preventDefault();
        onClose();
      }}
      onClick={(e) => {
        if (e.target === ref.current) onClose();
      }}
      className={cn(
        "fixed inset-x-0 bottom-0 top-auto m-0 flex max-h-[92dvh] w-full max-w-none flex-col rounded-t-panel bg-white p-0 text-ink-900 backdrop:bg-brand-navy-900/50 open:animate-sheet-up [&:not([open])]:hidden",
        className,
      )}
    >
      <div className="flex justify-center pt-3" aria-hidden>
        <span className="h-1 w-10 rounded-pill bg-ink-300" />
      </div>
      <div className="flex items-center justify-between border-b border-ink-100 px-4 pb-3 pt-2">
        <h2 id={titleId} className="font-display text-2xl font-semibold">
          {title}
        </h2>
        <button type="button" onClick={onClose} className="grid size-11 place-items-center rounded-full hover:bg-ink-100" aria-label={closeLabel}>
          <X className="size-6" aria-hidden />
        </button>
      </div>
      <div className="flex-1 overflow-y-auto overscroll-contain px-4 py-4">{children}</div>
      {footer && <div className="border-t border-ink-100 bg-white px-4 pb-[max(16px,env(safe-area-inset-bottom))] pt-3">{footer}</div>}
    </dialog>
  );
}
