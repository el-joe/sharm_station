import type { BookingStatus, PaymentStatus } from "@/lib/api/types";
import { CheckCircle2, CircleDashed, Clock, CreditCard, Eye, ShieldCheck, Undo2, XCircle } from "lucide-react";
import { useTranslations } from "next-intl";
import { Badge, type BadgeTone } from "./Badge";

const MAP: Record<BookingStatus | PaymentStatus, { tone: BadgeTone; Icon: typeof Clock }> = {
  awaiting_payment: { tone: "warning", Icon: Clock },
  awaiting_receipt_review: { tone: "info", Icon: Eye },
  proof_rejected: { tone: "warning", Icon: XCircle },
  deposit_paid: { tone: "info", Icon: CreditCard },
  confirmed: { tone: "success", Icon: ShieldCheck },
  fully_paid: { tone: "success", Icon: CheckCircle2 },
  completed: { tone: "neutral", Icon: CheckCircle2 },
  cancelled: { tone: "neutral", Icon: XCircle },
  refunded: { tone: "neutral", Icon: Undo2 },
  failed: { tone: "warning", Icon: XCircle },
};

/** Booking / payment status pill (Figma admin "Status" chips). */
export function StatusBadge({ status, className }: { status: BookingStatus | PaymentStatus; className?: string }) {
  const t = useTranslations("status");
  const { tone, Icon } = MAP[status] ?? { tone: "neutral" as const, Icon: CircleDashed };
  return (
    <Badge tone={tone} icon={<Icon aria-hidden />} className={className ?? "border border-current/10"}>
      {MAP[status] ? t(status) : status}
    </Badge>
  );
}
