import { redirect } from "@/i18n/navigation";
import { locale as rootLocale } from "next/root-params";

export default async function AccountIndex() {
  redirect({ href: "/account/bookings", locale: await rootLocale() });
}
