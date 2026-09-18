import { notFound } from "next/navigation";
import { readSoftware, readSoftwareSection, WebsiteApiError } from "@/lib/website-api";

export const dynamic = "force-dynamic";

export default async function SoftwarePrivacyPage({ params }: { params: Promise<{ slug: string }> }) {
  const { slug } = await params;
  try {
    const [product, section] = await Promise.all([
      readSoftware(slug),
      readSoftwareSection<{ slug: string; revision: number; privacy: string }>(slug, "privacy"),
    ]);
    return <main className="mx-auto max-w-4xl px-4 py-10 sm:px-6"><h1 className="text-3xl font-bold">{product.overview.name} Privacy</h1><p className="mt-2 text-sm text-slate-500">Product revision {section.revision}</p><article className="mt-8 text-slate-700" dangerouslySetInnerHTML={{ __html: section.privacy }} /></main>;
  } catch (error) { if (error instanceof WebsiteApiError && error.status === 404) notFound(); throw error; }
}
