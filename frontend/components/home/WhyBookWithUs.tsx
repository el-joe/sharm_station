import { CreditCard, Lock, MessageCircle, Users } from "lucide-react";
import { useTranslations } from "next-intl";

const ITEMS = [
  { key: "experts", Icon: Users },
  { key: "deposit", Icon: CreditCard },
  { key: "secure", Icon: Lock },
  { key: "support", Icon: MessageCircle },
] as const;

export function WhyBookWithUs() {
  const t = useTranslations("home.why");
  return (
    <section id="why" className="section-y bg-sand-50" aria-labelledby="why-title">
      <div className="container-page">
        <h2 id="why-title" className="text-h2 text-ink-900">{t("title")}</h2>
        <p className="mt-2 text-ink-700 lg:text-lg">{t("subtitle")}</p>
        <ul className="mt-8 grid gap-6 sm:grid-cols-2 md:mt-12 lg:grid-cols-4">
          {ITEMS.map(({ key, Icon }) => (
            <li key={key} className="rounded-card border border-ink-100 bg-white p-8 shadow-card">
              <span className="grid size-14 place-items-center rounded-full bg-primary-100 text-primary-700">
                <Icon className="size-6" aria-hidden />
              </span>
              <h3 className="mt-6 text-[22px] font-semibold leading-[30px] text-ink-900">{t(`${key}.title`)}</h3>
              <p className="mt-3 text-ink-700">{t(`${key}.text`)}</p>
            </li>
          ))}
        </ul>
      </div>
    </section>
  );
}
