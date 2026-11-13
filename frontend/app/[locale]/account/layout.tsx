import { AuthGuard } from "@/components/account/AuthGuard";

/** /account/*: client-side guard (signed-out visitors go to /sign-in?returnTo=…). Pages are noindex. */
export default function AccountLayout({ children }: LayoutProps<"/[locale]/account">) {
  return (
    <div className="container-page pb-16">
      <AuthGuard>{children}</AuthGuard>
    </div>
  );
}
