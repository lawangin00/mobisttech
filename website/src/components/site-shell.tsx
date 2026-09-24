import Image from "next/image";
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

type ManagedNode = { key: string; label: string; href: string | null; external: boolean; children: ManagedNode[] };

function ManagedNavLink({ node }: { node: ManagedNode }) {
  if (!node.href) return null;
  return node.external
    ? <a href={node.href} target="_blank" rel="noopener noreferrer" className="rounded px-3 py-2 hover:bg-slate-100">{node.label}</a>
    : <Link href={node.href} prefetch={false} className="rounded px-3 py-2 hover:bg-slate-100">{node.label}</Link>;
}

function ManagedNavItem({ node }: { node: ManagedNode }) {
  if (!node.children.length) return <ManagedNavLink node={node} />;
  return <details className="relative rounded border border-slate-200 px-1 py-1">
    <summary className="cursor-pointer px-2 py-1">{node.label}</summary>
    <div className="mt-1 grid min-w-40 gap-1 rounded bg-white p-1 sm:absolute sm:z-20 sm:border sm:shadow-md">
      {node.href && <ManagedNavLink node={{ ...node, label: `View ${node.label}`, children: [] }} />}
      {node.children.map(child => <ManagedNavItem key={child.key} node={child} />)}
    </div>
  </details>;
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
  const nodes = new Map<string, ManagedNode>();
  for (const item of content?.navigation ?? []) {
    const href = safeManagedHref(item);
    nodes.set(item.key, { key: item.key, label: item.label, href, external: Boolean(href?.startsWith("https://")), children: [] });
  }
  const managedRoots: ManagedNode[] = [];
  for (const item of content?.navigation ?? []) {
    const node = nodes.get(item.key);
    if (!node) continue;
    if (item.parent_key && nodes.has(item.parent_key)) nodes.get(item.parent_key)!.children.push(node);
    else managedRoots.push(node);
  }
  const managedPaths = new Set([...nodes.values()].map(node => node.href).filter(Boolean));
  const links: Array<{ href: string; label: string; external?: boolean }> = managedPaths.has("/") ? [] : [{ href: "/", label: "Home" }];
  const fallback = [
    routes.has("services") ? { href: "/services", label: "Services" } : null,
    routes.has("enquiry") ? { href: "/enquiry", label: "Enquiry" } : null,
    routes.has("products") ? { href: "/products", label: "Products" } : null,
    routes.has("categories") ? { href: "/categories", label: "Categories" } : null,
    routes.has("compare") ? { href: "/compare", label: "Compare" } : null,
    routes.has("cart") ? { href: "/cart", label: "Cart" } : null,
  ].filter((value): value is { href: string; label: string } => value !== null);
  for (const item of fallback) if (!managedPaths.has(item.href) && !links.some((link) => link.href === item.href)) links.push(item);

  const announcement = content?.promotion?.announcement;
  return (
    <>
    {announcement && <div role="status" aria-label="Site announcement" className="bg-slate-950 px-4 py-2 text-center text-sm text-white">
      {announcement.href ? <a href={announcement.href} className="underline underline-offset-2" rel="noopener noreferrer">{announcement.text}</a> : announcement.text}
    </div>}
    <header className="border-b border-slate-200 bg-white/95">
      <div className="mx-auto flex max-w-7xl flex-wrap items-center justify-between gap-4 px-4 py-4 sm:px-6">
        <Link href="/" prefetch={false} className="inline-flex items-center" aria-label={business?.business_name ?? "mobiST Technologies"}>
          <Image src="/brand/mobist-wordmark.svg" alt={business?.business_name ?? "mobiST Technologies"} width={184} height={92} className="h-10 w-auto max-w-[184px]" priority />
        </Link>
        <nav aria-label="Primary" className="flex flex-wrap items-center gap-1 text-sm font-medium">
          {links.map((item) => item.external ? (
            <a key={item.href} href={item.href} target="_blank" rel="noreferrer" className="rounded-full px-3 py-2 hover:bg-slate-100">{item.label}</a>
          ) : (
            <Link key={item.href} href={item.href} prefetch={false} className="rounded-full px-3 py-2 hover:bg-slate-100">{item.label}</Link>
          ))}
          {managedRoots.map(node => <ManagedNavItem key={node.key} node={node} />)}
          <Link href="/account" prefetch={false} className="rounded-full px-3 py-2 hover:bg-slate-100">Account</Link>
          {!links.some((item) => item.href === "/contact") && business?.business_email && (
            <a href={"mailto:" + business.business_email} className="rounded-full px-3 py-2 hover:bg-slate-100">Contact</a>
          )}
        </nav>
      </div>
    </header>
    </>
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
