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
export const metadata: Metadata = {
  title: "Products",
  description: "Browse live mobiST product availability and pricing.",
  alternates: { canonical: "/products" },
};

export default async function Products({
  searchParams,
}: {
  searchParams: Promise<Record<string, string | string[] | undefined>>;
}) {
  const params = await searchParams;
  const profile = await readWebsiteProfile();
  if (!profile?.capabilities.commerce) notFound();

  const rawQuery = typeof params.q === "string" ? params.q.trim() : "";
  const q = rawQuery.length >= 2 ? rawQuery : "";
  const category = typeof params.category === "string" ? params.category : "";
  const after = typeof params.after === "string" ? params.after : "";
  let page;
  try {
    page = await readCatalogue({
      limit: 12,
      q: q || undefined,
      category: category || undefined,
      after: after || undefined,
    });
  } catch (error) {
    if (error instanceof WebsiteApiError && (error.status === 404 || error.status === 422)) notFound();
    throw error;
  }
  const categories = await readCategories();
  const next = new URLSearchParams();
  if (q) next.set("q", q);
  if (category) next.set("category", category);
  if (page.page.next_cursor) next.set("after", page.page.next_cursor);

  return (
    <main className="mx-auto max-w-7xl px-4 py-10 sm:px-6">
      <div className="max-w-3xl">
        <p className="text-sm font-semibold uppercase tracking-wide text-slate-500">
          Live POS-backed catalogue
        </p>
        <h1 className="mt-2 text-4xl font-bold">Products</h1>
        <p className="mt-3 text-slate-600">
          Search and availability are read from the shared catalogue contract. Published
          out-of-stock listings remain visible.
        </p>
      </div>
      <form
        action="/products"
        className="mt-8 grid gap-3 rounded-2xl border border-slate-200 bg-white p-4 sm:grid-cols-[1fr_16rem_auto]"
      >
        <input
          name="q"
          defaultValue={q}
          minLength={2}
          maxLength={80}
          placeholder="Search product name"
          className="rounded-xl border border-slate-300 px-4 py-3"
        />
        <select
          name="category"
          defaultValue={category}
          className="rounded-xl border border-slate-300 px-4 py-3"
        >
          <option value="">All categories</option>
          {categories.map((item) => (
            <option key={item.code} value={item.code}>
              {item.label} ({item.products})
            </option>
          ))}
        </select>
        <button className="rounded-xl bg-slate-950 px-5 py-3 font-semibold text-white">
          Search
        </button>
      </form>
      <div className="mt-8 grid gap-5 sm:grid-cols-2 lg:grid-cols-3">
        {page.items.map((product, index) => (
          <ProductCard key={product.id} product={product} priority={index < 2} />
        ))}
      </div>
      {page.items.length === 0 && (
        <div className="mt-8 rounded-2xl border border-slate-200 bg-white p-8 text-center text-slate-600">
          No products match this search.
        </div>
      )}
      {page.page.has_more && (
        <div className="mt-8 flex justify-center">
          <Link
            href={`/products?${next.toString()}`}
            className="rounded-full border border-slate-300 bg-white px-5 py-3 font-semibold"
          >
            Next page →
          </Link>
        </div>
      )}
    </main>
  );
}
