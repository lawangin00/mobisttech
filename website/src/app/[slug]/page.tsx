import type { Metadata } from "next";
import { notFound } from "next/navigation";
import { cache } from "react";
import { readManagedPage, readPolicies, readWebsiteProfile, WebsiteApiError } from "@/lib/website-api";

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
  return {
    title: page.seo_title ?? page.title,
    description: page.meta_description ?? undefined,
    alternates: page.canonical_url ? { canonical: page.canonical_url } : undefined,
    robots: { index: page.is_indexable, follow: page.is_indexable },
    openGraph: { title: page.social_title ?? page.seo_title ?? page.title, description: page.social_description ?? page.meta_description ?? undefined },
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
  return <main className="mx-auto max-w-5xl px-4 py-10 sm:px-6">
    <h1 className="text-3xl font-bold">{page.title}</h1>
    <article className="mt-8 space-y-4 text-slate-700" dangerouslySetInnerHTML={{ __html: page.content }} />
  </main>;
}
