import Link from "next/link";
import { notFound } from "next/navigation";
import { readSoftware, readSoftwareSection, SoftwareRelease, WebsiteApiError } from "@/lib/website-api";

export const dynamic = "force-dynamic";

export default async function SoftwareReleasesPage({ params }: { params: Promise<{ slug: string }> }) {
  const { slug } = await params;
  try {
    const [product, section] = await Promise.all([
      readSoftware(slug),
      readSoftwareSection<{ slug: string; current_version: string | null; items: SoftwareRelease[]; truncated: boolean }>(slug, "releases"),
    ]);
    return <main className="mx-auto max-w-4xl px-4 py-10 sm:px-6"><h1 className="text-3xl font-bold">{product.overview.name} Releases</h1><p className="mt-2 text-slate-600">Current version: {section.current_version ?? "Not published"}</p><div className="mt-8 space-y-4">{section.items.map((release) => <article key={release.public_id} className="rounded-2xl border bg-white p-5"><h2 className="text-lg font-bold"><Link href={"/software/" + slug + "/releases/" + encodeURIComponent(release.version)} className="hover:underline">{release.version}</Link></h2><p className="text-sm text-slate-500">{release.release_date}</p><p className="mt-2 text-slate-700">{release.summary}</p></article>)}</div>{section.truncated && <p className="mt-5 text-sm text-slate-500">Older release history is available through the canonical product record.</p>}</main>;
  } catch (error) { if (error instanceof WebsiteApiError && error.status === 404) notFound(); throw error; }
}
