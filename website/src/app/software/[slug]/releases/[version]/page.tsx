import { notFound } from "next/navigation";
import { readSoftware, readSoftwareSection, SoftwareRelease, WebsiteApiError } from "@/lib/website-api";

export const dynamic = "force-dynamic";

async function load(slug: string) {
  try {
    return await Promise.all([
      readSoftware(slug),
      readSoftwareSection<{ items: SoftwareRelease[] }>(slug, "releases"),
    ]);
  } catch (error) {
    if (error instanceof WebsiteApiError && error.status === 404) return null;
    throw error;
  }
}

export default async function SoftwareReleasePage({ params }: { params: Promise<{ slug: string; version: string }> }) {
  const { slug, version } = await params;
  const result = await load(slug);
  if (!result) notFound();
  const [product, section] = result;
  const release = section.items.find((item) => item.version === version);
  if (!release) notFound();
  const groups = [["Added", release.notes.added], ["Changed", release.notes.changed], ["Fixed", release.notes.fixed], ["Security", release.notes.security]] as const;
  return <main className="mx-auto max-w-4xl px-4 py-10 sm:px-6"><p className="text-sm text-slate-500">{product.overview.name} release</p><h1 className="mt-1 text-3xl font-bold">{release.version}</h1><p className="mt-2 text-sm text-slate-500">{release.release_date}</p><p className="mt-5 text-slate-700">{release.summary}</p><div className="mt-8 space-y-6">{groups.map(([label, items]) => items && items.length > 0 ? <section key={label}><h2 className="font-bold">{label}</h2><ul className="mt-2 list-disc pl-5 text-slate-700">{items.map((item) => <li key={item}>{item}</li>)}</ul></section> : null)}</div></main>;
}
