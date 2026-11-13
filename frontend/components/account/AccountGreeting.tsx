"use client";

import { useAuth } from "@/lib/auth";
import { useTranslations } from "next-intl";

export function AccountGreeting() {
  const t = useTranslations("account.bookings");
  const { user } = useAuth();
  return <>{t("subtitle", { name: user?.name.split(" ")[0] ?? "none" })}</>;
}
