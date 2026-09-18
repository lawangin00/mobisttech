import type { Metadata } from "next";
import Link from "next/link";
import { notFound } from "next/navigation";
import { ProductCard } from "@/components/product-card";
import {
  readCatalogue,
  readCategories,
  readWebsiteProfile,
  WebsiteApiError,
} from "@/lib/website-api";

export const dynamic = "force-dynamic";

export async function generateMetadata({
  params,
}: {
  params: Promise<{ category: string }>;
}): Promise<Metadata> {
  const profile = await readWebsiteProfile();
  if (!profile?.capabilities.commerce) return { robots: { index: false, follow: false } };
  const { category } = await params;
  const categories = await readCategories().catch(() => []);
  const found = categories.find((item) => item.code === category);
  return {
    title: found?.label ?? "Category",
    description: found ? `Browse ${found.label} products at mobiST Technologies.` : undefined,
    alternates: found ? { canonical: `/categories/${encodeURIComponent(category)}` } : undefined,
  };
}

export default async function CategoryPage({
  params,
  searchParams,
}: {
  params: Promise<{ category: string }>;
  searchParams: Promise<{ after?: string }>;
}) {
  const [{ category }, query, profile] = await Promise.all([
    params,
    searchParams,
    readWebsiteProfile(),
  ]);
  if (!profile?.capabilities.commerce) notFound();
  const categories = await readCategories();
  const found = categories.find((item) => item.code === category);
  if (!found) notFound();

  let page;
  try {
    page = await readCatalogue({ limit: 12, category, after: query.after });
  } catch (error) {
    if (error instanceof WebsiteApiError && error.status === 422) notFound();
    throw error;
  }

  return (
    <main className="mx-auto max-w-7xl px-4 py-10 sm:px-6">
      <p className="text-sm text-slate-500">
        <Link href="/categories">Categories</Link> /
      </p>
      <h1 className="mt-3 text-4xl font-bold">{found.label}</h1>
      <p className="mt-2 text-slate-600">{found.products} published products</p>
      <div className="mt-8 grid gap-5 sm:grid-cols-2 lg:grid-cols-3">
        {page.items.map((product, index) => (
          <ProductCard key={product.id} product={product} priority={index < 2} />
        ))}
      </div>
      {page.page.has_more && page.page.next_cursor && (
        <div className="mt-8">
          <Link
            href={`/categories/${encodeURIComponent(category)}?after=${encodeURIComponent(page.page.next_cursor)}`}
            className="rounded-full border border-slate-300 bg-white px-5 py-3 font-semibold"
          >
            Next page →
          </Link>
        </div>
      )}
    </main>
  );
}
