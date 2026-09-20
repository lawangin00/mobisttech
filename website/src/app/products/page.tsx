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
  const sort = typeof params.sort === "string" ? params.sort : "oldest";
  const minPrice = typeof params.min_price === "string" ? params.min_price : "";
  const maxPrice = typeof params.max_price === "string" ? params.max_price : "";
  const brand = typeof params.brand === "string" ? params.brand.trim() : "";
  const model = typeof params.model === "string" ? params.model.trim() : "";
  let page;
  try {
    page = await readCatalogue({
      limit: 12,
      q: q || undefined,
      category: category || undefined,
      after: after || undefined,
      sort,
      min_price: minPrice || undefined,
      max_price: maxPrice || undefined,
      brand: brand || undefined,
      model: model || undefined,
    });
  } catch (error) {
    if (error instanceof WebsiteApiError && (error.status === 404 || error.status === 422)) notFound();
    throw error;
  }
  const categories = await readCategories();
  const next = new URLSearchParams();
  if (q) next.set("q", q);
  if (category) next.set("category", category);
  if (sort !== "oldest") next.set("sort", sort);
  if (minPrice) next.set("min_price", minPrice);
  if (maxPrice) next.set("max_price", maxPrice);
  if (brand) next.set("brand", brand);
  if (model) next.set("model", model);
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
        className="mt-8 grid gap-3 rounded-2xl border border-slate-200 bg-white p-4 lg:grid-cols-4 xl:grid-cols-8"
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
        <select name="sort" aria-label="Sort products" defaultValue={sort}
          className="rounded-xl border border-slate-300 px-4 py-3">
          <option value="oldest">Oldest first</option><option value="newest">Newest first</option>
          <option value="price_asc">Price: low to high</option><option value="price_desc">Price: high to low</option>
          <option value="name_asc">Name: A to Z</option><option value="name_desc">Name: Z to A</option>
        </select>
        <input name="min_price" aria-label="Minimum price" type="number" min="0" step="0.01" defaultValue={minPrice} placeholder="Min PKR" className="min-w-0 rounded-xl border border-slate-300 px-3 py-3" />
        <input name="max_price" aria-label="Maximum price" type="number" min="0" step="0.01" defaultValue={maxPrice} placeholder="Max PKR" className="min-w-0 rounded-xl border border-slate-300 px-3 py-3" />
        <input name="brand" aria-label="Brand" maxLength={80} defaultValue={brand} placeholder="Brand" className="min-w-0 rounded-xl border border-slate-300 px-3 py-3" />
        <input name="model" aria-label="Model" maxLength={80} defaultValue={model} placeholder="Model" className="min-w-0 rounded-xl border border-slate-300 px-3 py-3" />
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
            href={`/products?${next.toString()}`} prefetch={false}
            className="rounded-full border border-slate-300 bg-white px-5 py-3 font-semibold"
          >
            Next page →
          </Link>
        </div>
      )}
    </main>
  );
}
