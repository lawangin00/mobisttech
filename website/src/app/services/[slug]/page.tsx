import type { Metadata } from "next";
import Link from "next/link";
import { notFound } from "next/navigation";
import { cache } from "react";
import { readServices, readWebsiteProfile } from "@/lib/website-api";

const safeText = (value: unknown): string | null =>
  typeof value === "string" && value.trim().length > 0 && value.length <= 2000 ? value.trim() : null;
const safeList = (value: unknown): string[] => Array.isArray(value)
  ? value.slice(0, 12).map(safeText).filter((item): item is string => item !== null) : [];
const safeFaq = (value: unknown): Array<{ question: string; answer: string }> =>
  Array.isArray(value) ? value.slice(0, 12).flatMap((row: unknown) => {
    if (typeof row !== "object" || row === null || Array.isArray(row)) return [];
    const fields = row as Record<string, unknown>;
    const question = safeText(fields.question); const answer = safeText(fields.answer);
    return question && answer ? [{ question, answer }] : [];
  }) : [];

export const dynamic = "force-dynamic";

const service = cache(async function service(slug: string) {
  const profile = await readWebsiteProfile();
  if (!profile?.capabilities.digital) return null;
  return (await readServices()).find((item) => item.slug === slug) ?? null;
});

export async function generateMetadata({ params }: { params: Promise<{ slug: string }> }): Promise<Metadata> {
  const { slug } = await params;
  const item = await service(slug).catch(() => null);
  if (!item) return {};
  const title = item.landing?.seo_title || item.name;
  const description = item.landing?.meta_description || item.short_description;
  return { title, description, openGraph: { title, description }, twitter: { card: "summary", title, description } };
}

export default async function ServicePage({ params }: { params: Promise<{ slug: string }> }) {
  const { slug } = await params;
  const item = await service(slug);
  if (!item) notFound();
  const landing = item.landing;
  const structured = landing?.structured ?? {};
  const hero = safeText(structured.hero_heading);
  const heroBody = safeText(structured.hero_body);
  const problem = safeText(structured.problem);
  const outcome = safeText(structured.outcome);
  const features = safeList(structured.features);
  const deliverables = safeList(structured.deliverables);
  const processSteps = safeList(structured.process);
  const technologies = safeList(structured.technologies);
  const faq = safeFaq(structured.faq);
  return <main className="mx-auto max-w-4xl px-4 py-10 sm:px-6">
    <p className="text-sm text-slate-500">Digital Services</p>
    <h1 className="mt-1 text-3xl font-bold">{hero || item.name}</h1>
    <p className="mt-3 text-lg text-slate-600">{item.short_description}</p>
    {heroBody && <p className="mt-3 text-slate-700">{heroBody}</p>}
    {landing?.content
      ? <article data-testid="published-service-landing" className="mt-6 space-y-4 text-slate-700" dangerouslySetInnerHTML={{ __html: landing.content }} />
      : item.description && <div className="mt-6 whitespace-pre-wrap text-slate-700">{item.description}</div>}
    {(problem || outcome) && <section className="mt-8 grid gap-4 sm:grid-cols-2">
      {problem && <div><h2 className="font-semibold">The challenge</h2><p className="mt-2 whitespace-pre-wrap">{problem}</p></div>}
      {outcome && <div><h2 className="font-semibold">The outcome</h2><p className="mt-2 whitespace-pre-wrap">{outcome}</p></div>}
    </section>}
    {([['Features', features], ['Deliverables', deliverables], ['Process', processSteps], ['Technologies', technologies]] as const)
      .filter(([,values]) => values.length > 0).map(([heading, values]) =>
      <section key={heading} className="mt-8"><h2 className="text-lg font-bold">{heading}</h2>
        <ul className="mt-3 list-inside list-disc space-y-1 text-slate-700">{values.map((value, index) => <li key={index}>{value}</li>)}</ul>
      </section>)}
    {faq.length > 0 && <section className="mt-8"><h2 className="text-lg font-bold">Frequently asked questions</h2>
      <div className="mt-3 space-y-3">{faq.map((row, index) => <details key={index} className="rounded-xl border bg-white p-3"><summary className="cursor-pointer font-semibold">{row.question}</summary><p className="mt-2 whitespace-pre-wrap">{row.answer}</p></details>)}</div>
    </section>}
    {(item.related_pages?.length ?? 0) > 0 && <section className="mt-8"><h2 className="text-lg font-bold">Related work and client feedback</h2>
      <ul className="mt-3 space-y-2">{item.related_pages?.map(row => <li key={row.slug}><Link href={'/' + encodeURIComponent(row.slug)} prefetch={false} className="underline underline-offset-2">{row.title}</Link></li>)}</ul>
    </section>}
    <section className="mt-8 grid gap-5 md:grid-cols-2">
      {item.packages.length > 0 && <div className="rounded-2xl border bg-white p-5"><h2 className="text-lg font-bold">Packages</h2><div className="mt-3 space-y-3">{item.packages.map((offer) => <div key={offer.public_id} className="rounded-xl bg-slate-50 p-3"><strong>{offer.name}</strong><p className="text-sm text-slate-600">{offer.price ? "PKR " + offer.price : offer.pricing_type}</p></div>)}</div></div>}
      {item.addons.length > 0 && <div className="rounded-2xl border bg-white p-5"><h2 className="text-lg font-bold">Add-ons</h2><div className="mt-3 space-y-3">{item.addons.map((offer) => <div key={offer.public_id} className="rounded-xl bg-slate-50 p-3"><strong>{offer.name}</strong><p className="text-sm text-slate-600">{offer.price ? "PKR " + offer.price : offer.pricing_type}</p></div>)}</div></div>}
    </section>
    <Link href={"/enquiry?service=" + encodeURIComponent(item.slug)} prefetch={false} className="mt-8 inline-block rounded-xl bg-slate-950 px-5 py-3 font-semibold text-white">Discuss this service</Link>
  </main>;
}
