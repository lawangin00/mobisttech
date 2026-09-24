import { notFound } from "next/navigation";
import { redirectRetiredSoftwareRoute } from "@/lib/software-route-redirect";
import { readSoftware, readSoftwareSection, WebsiteApiError } from "@/lib/website-api";

export const dynamic = "force-dynamic";

async function load(slug: string) {
  try {
    return await Promise.all([
      readSoftware(slug),
      readSoftwareSection<{ slug: string; revision: number; faq: Array<{ question: string; answer: string }> }>(slug, "faq"),
    ]);
  } catch (error) {
    if (error instanceof WebsiteApiError && error.status === 404) { await redirectRetiredSoftwareRoute(slug, "/faq"); return null; }
    throw error;
  }
}

export default async function SoftwareFaqPage({ params }: { params: Promise<{ slug: string }> }) {
  const { slug } = await params;
  const result = await load(slug);
  if (!result) notFound();
  const [product, section] = result;
  return <main className="mx-auto max-w-4xl px-4 py-10 sm:px-6"><h1 className="text-3xl font-bold">{product.overview.name} FAQ</h1><p className="mt-2 text-sm text-slate-500">Product revision {section.revision}</p><div className="mt-8 space-y-4">{section.faq.map((item) => <article key={item.question} className="rounded-2xl border bg-white p-5"><h2 className="font-bold">{item.question}</h2><div className="mt-2 text-slate-700" dangerouslySetInnerHTML={{ __html: item.answer }} /></article>)}</div></main>;
}
