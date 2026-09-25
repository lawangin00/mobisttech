import type { Metadata } from "next";
import Link from "next/link";
import { notFound } from "next/navigation";
import { readWebsiteProfile } from "@/lib/website-api";

export const dynamic = "force-dynamic";
export const metadata: Metadata = {
  title: "Knowledge",
  description: "Digital Solutions FAQs, insights and service-related guides.",
};

export default async function KnowledgePage() {
  const profile = await readWebsiteProfile();
  if (!profile?.capabilities.digital) notFound();
  const items = (profile.content.pages ?? []).filter(page => ["faq", "insight", "guide"].includes(page.purpose));
  return <main className="mx-auto max-w-5xl px-4 py-10 sm:px-6">
    <p className="text-sm text-slate-500">Digital Solutions</p>
    <h1 className="mt-1 text-3xl font-bold">Knowledge</h1>
    <p className="mt-3 text-slate-600">FAQs, insights and guides published by mobiST Technologies.</p>
    {items.length === 0 ? <p className="mt-8 text-slate-500">No published knowledge content yet.</p> :
      <div className="mt-8 grid gap-4 sm:grid-cols-2">
        {items.map(item => <article key={item.slug} className="rounded-2xl border bg-white p-5">
          <p className="text-xs font-semibold uppercase tracking-wide text-slate-500">{item.purpose.replaceAll("_"," ")}</p>
          <h2 className="mt-1 text-xl font-bold"><Link href={"/"+encodeURIComponent(item.slug)} prefetch={false} className="underline-offset-4 hover:underline">{item.title}</Link></h2>
          {item.category && <p className="mt-2 text-sm text-slate-600">{item.category}</p>}
          {(item.tags?.length ?? 0) > 0 && <div className="mt-3 flex flex-wrap gap-1">{item.tags!.map(tag => <span key={tag} className="rounded-full border px-2 py-0.5 text-xs text-slate-600">{tag}</span>)}</div>}
        </article>)}
      </div>}
  </main>;
}
