import { whatsappHref } from "@/lib/site";
import { getTranslations } from "next-intl/server";

export async function WhatsAppFab() {
  const t = await getTranslations("common");
  return (
    <a
      href={whatsappHref(t("whatsappMessage"))}
      target="_blank"
      rel="noopener noreferrer"
      aria-label={t("whatsappAria")}
      className="fixed bottom-24 end-4 z-30 grid size-14 place-items-center rounded-full bg-[#25D366] text-white shadow-raised transition-transform hover:scale-105 lg:bottom-6 lg:end-6"
    >
      <svg viewBox="0 0 32 32" className="size-7" aria-hidden fill="currentColor">
        <path d="M16 3C9 3 3.3 8.6 3.3 15.6c0 2.2.6 4.4 1.7 6.3L3.2 29l7.3-1.9c1.8 1 3.9 1.5 6 1.5 7 0 12.7-5.7 12.7-12.6C29.2 8.6 23 3 16 3zm0 23.2c-1.9 0-3.8-.5-5.4-1.5l-.4-.2-4.3 1.1 1.2-4.2-.3-.4a10.5 10.5 0 0 1-1.6-5.5C5.2 9.8 10 5.1 16 5.1s10.9 4.7 10.9 10.5-5 10.6-10.9 10.6zm6-7.9c-.3-.2-1.9-.9-2.2-1-.3-.1-.5-.2-.7.2l-1 1.2c-.2.2-.4.2-.7.1-.3-.2-1.4-.5-2.6-1.6-1-.9-1.6-1.9-1.8-2.2-.2-.3 0-.5.1-.7l.5-.6.3-.5c.1-.2 0-.4 0-.6l-1-2.4c-.3-.6-.5-.5-.7-.5h-.6c-.2 0-.6.1-.9.4-.3.3-1.2 1.1-1.2 2.7s1.2 3.2 1.4 3.4c.2.2 2.4 3.6 5.8 5 2.8 1.1 3.4.9 4 .8.6-.1 1.9-.8 2.2-1.5.3-.8.3-1.4.2-1.5-.1-.2-.3-.3-.6-.4z" />
      </svg>
    </a>
  );
}
