import type { Metadata } from "next";
import Link from "next/link";
import { notFound } from "next/navigation";
import { readServices, readWebsiteProfile } from "@/lib/website-api";

export const dynamic = "force-dynamic";

async function service(slug: string) {
  const profile = await readWebsiteProfile();
  if (!profile?.capabilities.digital) return null;
  return (await readServices()).find((item) => item.slug === slug) ?? null;
}

export async function generateMetadata({ params }: { params: Promise<{ slug: string }> }): Promise<Metadata> {
  const { slug } = await params;
  const item = await service(slug).catch(() => null);
  return item ? { title: item.name, description: item.short_description } : {};
}

export default async function ServicePage({ params }: { params: Promise<{ slug: string }> }) {
  const { slug } = await params;
  const item = await service(slug);
  if (!item) notFound();
  return <main className="mx-auto max-w-4xl px-4 py-10 sm:px-6">
    <p className="text-sm text-slate-500">Digital Services</p>
    <h1 className="mt-1 text-3xl font-bold">{item.name}</h1>
    <p className="mt-3 text-lg text-slate-600">{item.short_description}</p>
    {item.description && <div className="mt-6 whitespace-pre-wrap text-slate-700">{item.description}</div>}
    <section className="mt-8 grid gap-5 md:grid-cols-2">
      {item.packages.length > 0 && <div className="rounded-2xl border bg-white p-5"><h2 className="text-lg font-bold">Packages</h2><div className="mt-3 space-y-3">{item.packages.map((offer) => <div key={offer.public_id} className="rounded-xl bg-slate-50 p-3"><strong>{offer.name}</strong><p className="text-sm text-slate-600">{offer.price ? "PKR " + offer.price : offer.pricing_type}</p></div>)}</div></div>}
      {item.addons.length > 0 && <div className="rounded-2xl border bg-white p-5"><h2 className="text-lg font-bold">Add-ons</h2><div className="mt-3 space-y-3">{item.addons.map((offer) => <div key={offer.public_id} className="rounded-xl bg-slate-50 p-3"><strong>{offer.name}</strong><p className="text-sm text-slate-600">{offer.price ? "PKR " + offer.price : offer.pricing_type}</p></div>)}</div></div>}
    </section>
    <Link href={"/enquiry?service=" + encodeURIComponent(item.slug)} className="mt-8 inline-block rounded-xl bg-slate-950 px-5 py-3 font-semibold text-white">Discuss this service</Link>
  </main>;
}
