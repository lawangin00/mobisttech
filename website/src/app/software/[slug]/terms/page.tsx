import { notFound } from "next/navigation";
import { redirectRetiredSoftwareRoute } from "@/lib/software-route-redirect";
import { readSoftware, readSoftwareSection, WebsiteApiError } from "@/lib/website-api";

export const dynamic = "force-dynamic";

async function load(slug: string) {
  try {
    return await Promise.all([
      readSoftware(slug),
      readSoftwareSection<{ slug: string; revision: number; terms: string }>(slug, "terms"),
    ]);
  } catch (error) {
    if (error instanceof WebsiteApiError && error.status === 404) { await redirectRetiredSoftwareRoute(slug, "/terms"); return null; }
    throw error;
  }
}

export default async function SoftwareTermsPage({ params }: { params: Promise<{ slug: string }> }) {
  const { slug } = await params;
  const result = await load(slug);
  if (!result) notFound();
  const [product, section] = result;
  return <main className="mx-auto max-w-4xl px-4 py-10 sm:px-6"><h1 className="text-3xl font-bold">{product.overview.name} Terms</h1><p className="mt-2 text-sm text-slate-500">Product revision {section.revision}</p><article className="mt-8 text-slate-700" dangerouslySetInnerHTML={{ __html: section.terms }} /></main>;
}
