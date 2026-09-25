import type { Metadata } from "next";
import Link from "next/link";
import { notFound } from "next/navigation";
import { cache } from "react";
import { readManagedPage, readPolicies, readWebsiteProfile, WebsiteApiError } from "@/lib/website-api";
import { readBusinessProfile } from "@/lib/business-profile";

const caseText = (value: unknown): string | null =>
  typeof value === "string" && value.trim().length > 0 && value.length <= 2000 ? value.trim() : null;
const caseList = (value: unknown): string[] => Array.isArray(value)
  ? value.slice(0, 12).map(caseText).filter((entry): entry is string => entry !== null) : [];

export const dynamic = "force-dynamic";

const resolve = cache(async function resolve(slug: string) {
  const profile = await readWebsiteProfile();
  const isPolicy = profile?.content.policies.some((item) => item.slug === slug) ?? false;

  if (isPolicy) {
    const policies = await readPolicies();
    const policy = policies.find((item) => item.slug === slug);
    return policy ? { kind: "policy" as const, policy } : null;
  }

  try {
    return { kind: "page" as const, page: await readManagedPage(slug) };
  } catch (error) {
    if (error instanceof WebsiteApiError && error.status === 404) return null;
    throw error;
  }
});

export async function generateMetadata({ params }: { params: Promise<{ slug: string }> }): Promise<Metadata> {
  const { slug } = await params;
  const item = await resolve(slug).catch(() => null);
  if (!item) return {};
  if (item.kind === "policy") return { title: item.policy.title, robots: { index: true, follow: true } };
  const page = item.page.snapshot;
  const imageId = page.social_image_media_id;
  const business = imageId ? await readBusinessProfile() : null;
  const image = imageId && business ? new URL(`/${encodeURIComponent(page.slug)}/media/${imageId}`, business.public_website).href : undefined;
  return {
    title: page.seo_title ?? page.title,
    description: page.meta_description ?? undefined,
    alternates: page.canonical_url ? { canonical: page.canonical_url } : undefined,
    robots: { index: page.is_indexable, follow: page.is_indexable },
    openGraph: { title: page.social_title ?? page.seo_title ?? page.title, description: page.social_description ?? page.meta_description ?? undefined, ...(image ? { images: [{ url: image }] } : {}) },
    twitter: image ? { card: "summary_large_image", images: [image] } : undefined,
  };
}

export default async function PublishedContentPage({ params }: { params: Promise<{ slug: string }> }) {
  const { slug } = await params;
  const item = await resolve(slug);
  if (!item) notFound();

  if (item.kind === "policy") {
    return <main className="mx-auto max-w-4xl px-4 py-10 sm:px-6">
      <h1 className="text-3xl font-bold">{item.policy.title}</h1>
      <p className="mt-2 text-sm text-slate-500">Version {item.policy.version} · Effective {item.policy.effective_date}</p>
      <article className="mt-8 space-y-4 text-slate-700" dangerouslySetInnerHTML={{ __html: item.policy.content }} />
    </main>;
  }

  const page = item.page.snapshot;
  const story = page.content_purpose === "case_study" ? page.structured_content : null;
  const testimonial = page.content_purpose === "digital_testimonial" ? page.structured_content : null;
  const testimonialCases = Array.isArray(testimonial?.case_study_slugs)
    ? testimonial.case_study_slugs.slice(0, 20).filter((value): value is string => typeof value === "string" && value.length > 0 && value.length <= 160) : [];
  const knowledge = ["faq", "insight", "guide"].includes(page.content_purpose) ? page.structured_content : null;
  const knowledgeCategory = caseText(knowledge?.category);
  const knowledgeTags = caseList(knowledge?.tags);
  const faqItems = page.content_purpose === "faq" && Array.isArray(knowledge?.items)
    ? knowledge.items.slice(0, 50).flatMap((row: unknown) => {
        if (typeof row !== "object" || row === null || Array.isArray(row)) return [];
        const fields = row as Record<string, unknown>;
        const question = caseText(fields.question);
        const answer = typeof fields.answer === "string" && fields.answer.length <= 5000 ? fields.answer : null;
        return question && answer ? [{ question, answer }] : [];
      }) : [];
  const disclosure = story?.client_disclosure;
  const category = caseText(story?.category);
  const industry = disclosure === 'named' || disclosure === 'industry_only' ? caseText(story?.industry) : null;
  const problem = caseText(story?.problem);
  const solution = caseText(story?.solution);
  const technologies = caseList(story?.technologies);
  const outcomes = caseList(story?.outcomes);
  const screenshots = Array.isArray(story?.screenshot_media_ids)
    ? story.screenshot_media_ids.slice(0, 12).filter((value): value is number => Number.isInteger(value) && Number(value) > 0) : [];
  const relatedTestimonials = item.page.related_testimonials ?? [];
  return <main className={(page.template === "wide" ? "max-w-7xl" : "max-w-5xl") + " mx-auto px-4 py-10 sm:px-6"} data-page-template={page.template}>
    <h1 className="text-3xl font-bold">{page.title}</h1>
    {story && <p className="mt-2 text-sm text-slate-500" data-testid="case-disclosure">
      {category && <span>{category} · </span>}{industry && <span>{industry} · </span>}
      {disclosure === "anonymous" ? "Anonymous case study" : disclosure === "industry_only" ? "Industry-only case study" : "Published case study"}
    </p>}
    {testimonial && <p className="mt-2 text-sm text-slate-500" data-testid="digital-testimonial-status">Published client feedback</p>}
    {knowledge && <div className="mt-2 flex flex-wrap gap-2 text-sm text-slate-500" data-testid="knowledge-taxonomy">
      {knowledgeCategory && <span>{knowledgeCategory}</span>}
      {knowledgeTags.map(tag => <span key={tag} className="rounded-full border px-2 py-0.5">{tag}</span>)}
    </div>}
    <article className="mt-8 space-y-4 text-slate-700" dangerouslySetInnerHTML={{ __html: page.content }} />
    {faqItems.length > 0 && <section className="mt-8" data-testid="reusable-faq"><h2 className="text-lg font-bold">Frequently asked questions</h2><div className="mt-3 space-y-3">{faqItems.map((row,index) => <details key={index} className="rounded-xl border bg-white p-3"><summary className="cursor-pointer font-semibold">{row.question}</summary><div className="mt-2 text-slate-700" dangerouslySetInnerHTML={{__html:row.answer}} /></details>)}</div></section>}
    {knowledge && page.service_slugs.length > 0 && <section className="mt-6"><h2 className="font-semibold">Related services</h2><ul className="mt-2 space-y-1">{page.service_slugs.map(serviceSlug => <li key={serviceSlug}><Link prefetch={false} className="underline underline-offset-2" href={'/services/'+encodeURIComponent(serviceSlug)}>{serviceSlug.replaceAll('-', ' ')}</Link></li>)}</ul></section>}
    {testimonialCases.length > 0 && <section className="mt-6"><h2 className="font-semibold">Related case studies</h2><ul className="mt-2 space-y-1">{testimonialCases.map(caseSlug => <li key={caseSlug}><Link prefetch={false} className="underline underline-offset-2" href={'/' + encodeURIComponent(caseSlug)}>{caseSlug.replaceAll('-', ' ')}</Link></li>)}</ul></section>}
    {story && (problem || solution) && <section className="mt-8 grid gap-4 sm:grid-cols-2">
      {problem && <div><h2 className="font-semibold">The challenge</h2><p className="mt-2 whitespace-pre-wrap">{problem}</p></div>}
      {solution && <div><h2 className="font-semibold">The solution</h2><p className="mt-2 whitespace-pre-wrap">{solution}</p></div>}
    </section>}
    {story && technologies.length > 0 && <section className="mt-6"><h2 className="font-semibold">Technologies</h2>
      <ul className="mt-2 list-inside list-disc">{technologies.map((technology, index) => <li key={index}>{technology}</li>)}</ul>
    </section>}
    {story && outcomes.length > 0 && <section className="mt-6"><h2 className="font-semibold">Outcomes</h2>
      <ul className="mt-2 list-inside list-disc">{outcomes.map((outcome, index) => <li key={index}>{outcome}</li>)}</ul>
    </section>}
    {story && screenshots.length > 0 && <section className="mt-8"><h2 className="font-semibold">Project media</h2><div className="mt-3 grid gap-3 sm:grid-cols-2">{screenshots.map((mediaId,index) => <img key={mediaId} src={'/'+encodeURIComponent(page.slug)+'/media/'+mediaId} alt={page.title+' screenshot '+(index+1)} loading="lazy" className="h-auto w-full rounded-xl border" />)}</div></section>}
    {story && relatedTestimonials.length > 0 && <section className="mt-8"><h2 className="font-semibold">Client feedback</h2><ul className="mt-2 space-y-1">{relatedTestimonials.map(row => <li key={row.slug}><Link prefetch={false} className="underline underline-offset-2" href={'/'+encodeURIComponent(row.slug)}>{row.title}</Link></li>)}</ul></section>}
  </main>;
}
