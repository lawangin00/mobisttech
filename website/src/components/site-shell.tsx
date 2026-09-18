import Link from "next/link";
import type { BusinessProfile } from "@/lib/business-profile";
import type { WebsiteProfile } from "@/lib/website-api";

export function SiteHeader({
  business,
  profile,
}: {
  business: BusinessProfile | null;
  profile: WebsiteProfile | null;
}) {
  const routes = new Set(profile?.routes ?? []);
  return (
    <header className="border-b border-slate-200 bg-white/95">
      <div className="mx-auto flex max-w-7xl flex-wrap items-center justify-between gap-4 px-4 py-4 sm:px-6">
        <Link href="/" className="text-xl font-bold tracking-tight">
          {business?.business_name ?? "mobiST Technologies"}
        </Link>
        <nav aria-label="Primary" className="flex flex-wrap items-center gap-1 text-sm font-medium">
          <Link href="/" className="rounded-full px-3 py-2 hover:bg-slate-100">
            Home
          </Link>
          {routes.has("products") && (
            <Link href="/products" prefetch={false} className="rounded-full px-3 py-2 hover:bg-slate-100">
              Products
            </Link>
          )}
          {routes.has("categories") && (
            <Link href="/categories" prefetch={false} className="rounded-full px-3 py-2 hover:bg-slate-100">
              Categories
            </Link>
          )}
          {routes.has("compare") && (
            <Link href="/compare" prefetch={false} className="rounded-full px-3 py-2 hover:bg-slate-100">
              Compare
            </Link>
          )}
          {routes.has("cart") && <Link href="/cart" className="rounded-full px-3 py-2 hover:bg-slate-100">Cart</Link>}
          <Link href="/account" className="rounded-full px-3 py-2 hover:bg-slate-100">Account</Link>
          {business?.business_email && (
            <a
              href={`mailto:${business.business_email}`}
              className="rounded-full px-3 py-2 hover:bg-slate-100"
            >
              Contact
            </a>
          )}
        </nav>
      </div>
    </header>
  );
}

export function SiteFooter({
  business,
  profile,
}: {
  business: BusinessProfile | null;
  profile: WebsiteProfile | null;
}) {
  return (
    <footer className="mt-16 border-t border-slate-200 bg-white">
      <div className="mx-auto flex max-w-7xl flex-wrap justify-between gap-6 px-4 py-8 text-sm text-slate-600 sm:px-6">
        <div>
          <strong className="text-slate-950">
            {business?.business_name ?? "mobiST Technologies"}
          </strong>
          <p className="mt-1">Products and digital solutions from one shared platform.</p>
        </div>
        <div className="text-right">
          <p>Website mode: {profile?.mode?.replaceAll("_", " ") ?? "not published"}</p>
          {business?.business_email && (
            <a className="hover:text-slate-950" href={`mailto:${business.business_email}`}>
              {business.business_email}
            </a>
          )}
        </div>
      </div>
    </footer>
  );
}
