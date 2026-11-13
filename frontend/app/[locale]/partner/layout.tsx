import { AuthGuard } from "@/components/account/AuthGuard";
import { PartnerNav } from "@/components/partner/PartnerNav";

/** /partner/*: active partners only (client guard; the API enforces it too). Pages are noindex. */
export default function PartnerLayout({ children }: LayoutProps<"/[locale]/partner">) {
  return (
    <div className="bg-sand-50">
      <div className="container-page py-6 lg:py-10">
        <AuthGuard partner>
          <div className="grid grid-cols-[minmax(0,1fr)] gap-6 lg:grid-cols-[220px_minmax(0,1fr)] lg:gap-10">
            <PartnerNav />
            <div className="min-w-0">{children}</div>
          </div>
        </AuthGuard>
      </div>
    </div>
  );
}
