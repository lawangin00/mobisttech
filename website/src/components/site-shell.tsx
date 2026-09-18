import Link from "next/link";
import type { BusinessProfile } from "@/lib/business-profile";
import type { ContentIndex, WebsiteProfile } from "@/lib/website-api";

function safeManagedHref(item: ContentIndex["navigation"][number]) {
  if (item.destination_type === "route" || item.destination_type === "page") {
    const key = item.destination_key?.trim();
    if (!key) return null;
    return key === "home" ? "/" : "/" + key.replace(/^\/+/, "");
  }
  const raw = item.destination_payload?.url;
  if (typeof raw !== "string") return null;
  if (raw.startsWith("/") && !raw.startsWith("//")) return raw;
  try {
    const url = new URL(raw);
    return url.protocol === "https:" ? url.toString() : null;
  } catch {
    return null;
  }
}

export function SiteHeader({
  business,
  profile,
  content,
}: {
  business: BusinessProfile | null;
  profile: WebsiteProfile | null;
  content: ContentIndex | null;
}) {
  const routes = new Set(profile?.routes ?? []);
  const links: Array<{ href: string; label: string; external?: boolean }> = [{ href: "/", label: "Home" }];
  for (const item of content?.navigation ?? []) {
    const href = safeManagedHref(item);
    if (href && !links.some((link) => link.href === href)) {
      links.push({ href, label: item.label, external: href.startsWith("https://") });
    }
  }
  const fallback = [
    routes.has("services") ? { href: "/services", label: "Services" } : null,
    routes.has("enquiry") ? { href: "/enquiry", label: "Enquiry" } : null,
    routes.has("products") ? { href: "/products", label: "Products" } : null,
    routes.has("categories") ? { href: "/categories", label: "Categories" } : null,
    routes.has("compare") ? { href: "/compare", label: "Compare" } : null,
    routes.has("cart") ? { href: "/cart", label: "Cart" } : null,
  ].filter((value): value is { href: string; label: string } => value !== null);
  for (const item of fallback) if (!links.some((link) => link.href === item.href)) links.push(item);

  return (
    <header className="border-b border-slate-200 bg-white/95">
      <div className="mx-auto flex max-w-7xl flex-wrap items-center justify-between gap-4 px-4 py-4 sm:px-6">
        <Link href="/" className="text-xl font-bold tracking-tight">
          {business?.business_name ?? "mobiST Technologies"}
        </Link>
        <nav aria-label="Primary" className="flex flex-wrap items-center gap-1 text-sm font-medium">
          {links.map((item) => item.external ? (
            <a key={item.href} href={item.href} target="_blank" rel="noreferrer" className="rounded-full px-3 py-2 hover:bg-slate-100">{item.label}</a>
          ) : (
            <Link key={item.href} href={item.href} prefetch={false} className="rounded-full px-3 py-2 hover:bg-slate-100">{item.label}</Link>
          ))}
          <Link href="/account" prefetch={false} className="rounded-full px-3 py-2 hover:bg-slate-100">Account</Link>
          {!links.some((item) => item.href === "/contact") && business?.business_email && (
            <a href={"mailto:" + business.business_email} className="rounded-full px-3 py-2 hover:bg-slate-100">Contact</a>
          )}
        </nav>
      </div>
    </header>
  );
}

export function SiteFooter({
  business,
  profile,
  content,
}: {
  business: BusinessProfile | null;
  profile: WebsiteProfile | null;
  content: ContentIndex | null;
}) {
  return (
    <footer className="mt-16 border-t border-slate-200 bg-white">
      <div className="mx-auto max-w-7xl px-4 py-8 text-sm text-slate-600 sm:px-6">
        <div className="flex flex-wrap justify-between gap-6">
          <div>
            <strong className="text-slate-950">{business?.business_name ?? "mobiST Technologies"}</strong>
            <p className="mt-1">Products and digital solutions from one shared platform.</p>
          </div>
          <div className="text-right">
            <p>Website mode: {profile?.mode?.replaceAll("_", " ") ?? "not published"}</p>
            {business?.business_email && <a className="hover:text-slate-950" href={"mailto:" + business.business_email}>{business.business_email}</a>}
          </div>
        </div>
        {(content?.policies.length ?? 0) > 0 && <nav aria-label="Policies" className="mt-6 flex flex-wrap gap-x-4 gap-y-2 border-t pt-5">
          {content?.policies.map((policy) => <Link key={policy.slug} href={"/" + policy.slug} prefetch={false} className="hover:text-slate-950">{policy.title}</Link>)}
        </nav>}
      </div>
    </footer>
  );
}
