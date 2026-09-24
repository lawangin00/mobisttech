import type { Metadata } from "next";
import Link from "next/link";
import { notFound } from "next/navigation";
import { cache } from "react";
import { readSoftware, WebsiteApiError } from "@/lib/website-api";
import { readBusinessProfile } from "@/lib/business-profile";

export const dynamic = "force-dynamic";

const load = cache(async function load(slug: string) {
  try { return await readSoftware(slug); }
  catch (error) { if (error instanceof WebsiteApiError && error.status === 404) return null; throw error; }
});

export async function generateMetadata({ params }: { params: Promise<{ slug: string }> }): Promise<Metadata> {
  const { slug } = await params;
  const product = await load(slug).catch(() => null);
  if (!product) return {};
  const seo = product.overview.seo;
  const text = (value: unknown) => typeof value === "string" && value.trim() ? value.trim() : null;
  const title = text(seo.title) ?? product.overview.name;
  const description = text(seo.description) ?? product.overview.summary;
  const socialTitle = text(seo.social_title) ?? title;
  const socialDescription = text(seo.social_description) ?? description;
  const canonicalPath = `/software/${encodeURIComponent(product.slug)}`;
  const canonical = text(seo.canonical_url) ?? canonicalPath;
  const business = await readBusinessProfile();
  const socialUrl = business ? new URL(canonicalPath, business.public_website).href : undefined;
  const mediaId = Number(seo.social_image_media_id);
  const socialImage = Number.isSafeInteger(mediaId) && mediaId > 0 && business
    ? new URL(`/software/${encodeURIComponent(product.slug)}/media/${mediaId}`, business.public_website).href : undefined;
  return {
    title, description,
    alternates: { canonical },
    openGraph: { type: "website", title: socialTitle, description: socialDescription, ...(socialUrl ? { url: socialUrl } : {}), ...(socialImage ? { images: [{ url: socialImage }] } : {}) },
    twitter: { card: "summary", title: socialTitle, description: socialDescription },
  };
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
    {product.overview.hero_media_id && <img src={`/software/${encodeURIComponent(product.slug)}/media/${product.overview.hero_media_id}`} alt={`${product.overview.name} hero image`} className="mt-6 max-h-80 w-full rounded-xl object-contain" />}
    {product.overview.logo_media_id && <img src={`/software/${encodeURIComponent(product.slug)}/media/${product.overview.logo_media_id}`} alt={`${product.overview.name} logo`} className="mt-5 max-h-24 w-auto object-contain" />}
    {(product.overview.screenshot_media_ids?.length ?? 0) > 0 && <section aria-label="Software screenshots" className="mt-5 grid gap-3 sm:grid-cols-2">{product.overview.screenshot_media_ids.map(id => <img key={id} src={`/software/${encodeURIComponent(product.slug)}/media/${id}`} alt={`${product.overview.name} screenshot`} className="max-h-80 w-full rounded-xl object-contain" />)}</section>}

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
