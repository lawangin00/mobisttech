import type { Metadata } from "next";
import Link from "next/link";
import { notFound } from "next/navigation";
import { readSoftware, WebsiteApiError } from "@/lib/website-api";

export const dynamic = "force-dynamic";

async function load(slug: string) {
  try { return await readSoftware(slug); }
  catch (error) { if (error instanceof WebsiteApiError && error.status === 404) return null; throw error; }
}

export async function generateMetadata({ params }: { params: Promise<{ slug: string }> }): Promise<Metadata> {
  const { slug } = await params;
  const product = await load(slug).catch(() => null);
  return product ? { title: product.overview.name, description: product.overview.summary } : {};
}

export default async function SoftwareOverviewPage({ params }: { params: Promise<{ slug: string }> }) {
  const { slug } = await params;
  const product = await load(slug);
  if (!product) notFound();
  return <main className="mx-auto max-w-5xl px-4 py-10 sm:px-6">
    <p className="text-sm text-slate-500">Software Product</p>
    <h1 className="mt-1 text-3xl font-bold">{product.overview.name}</h1>
    <p className="mt-3 text-lg text-slate-600">{product.overview.summary}</p>
    {product.current_version && <p className="mt-2 text-sm text-slate-500">Current version: {product.current_version}</p>}

    <article className="mt-8 space-y-4 text-slate-700" dangerouslySetInnerHTML={{ __html: product.overview.overview }} />

    {product.overview.features?.length > 0 && <section className="mt-10">
      <h2 className="text-2xl font-bold">Features</h2>
      <div className="mt-4 grid gap-4 md:grid-cols-2">{product.overview.features.map((feature) => <article key={feature.title} className="rounded-2xl border bg-white p-5"><h3 className="font-bold">{feature.title}</h3><p className="mt-2 text-slate-600">{feature.description}</p></article>)}</div>
    </section>}

    <section className="mt-10 grid gap-5 md:grid-cols-2">
      <div className="rounded-2xl border bg-white p-5"><h2 className="font-bold">Platforms</h2><ul className="mt-2 list-disc pl-5 text-slate-700">{product.overview.platforms?.map((item) => <li key={item}>{item}</li>)}</ul></div>
      <div className="rounded-2xl border bg-white p-5"><h2 className="font-bold">System requirements</h2><div className="mt-2 text-slate-700" dangerouslySetInnerHTML={{ __html: product.overview.system_requirements }} /></div>
    </section>

    {product.overview.limitations?.length > 0 && <section className="mt-8"><h2 className="font-bold">Limitations</h2><ul className="mt-2 list-disc pl-5 text-slate-700">{product.overview.limitations.map((item) => <li key={item}>{item}</li>)}</ul></section>}

    <nav aria-label="Software documentation" className="mt-10 flex flex-wrap gap-3">
      <Link href={product.routes.privacy} className="rounded-xl border px-4 py-2">Privacy</Link>
      <Link href={product.routes.terms} className="rounded-xl border px-4 py-2">Terms</Link>
      <Link href={product.routes.faq} className="rounded-xl border px-4 py-2">FAQ</Link>
      <Link href={product.routes.releases} className="rounded-xl border px-4 py-2">Releases</Link>
    </nav>
  </main>;
}
