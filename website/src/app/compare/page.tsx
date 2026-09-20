import type { Metadata } from "next";
import { notFound } from "next/navigation";
import { ProductImage } from "@/components/product-image";
import { money } from "@/lib/storefront";
import { readCatalogue, readProduct, readWebsiteProfile, WebsiteApiError } from "@/lib/website-api";

export const dynamic = "force-dynamic";
export const metadata: Metadata = {
  title: "Compare products",
  description: "Compare public product price, stock and variant availability.",
  robots: { index: false, follow: true },
};

export default async function Compare({ searchParams }: { searchParams: Promise<{ a?: string; b?: string }> }) {
  const [params, profile] = await Promise.all([searchParams, readWebsiteProfile()]);
  if (!profile?.capabilities.commerce) notFound();
  const catalogue = await readCatalogue({ limit: 24 });
  const slugs = [...new Set([params.a, params.b].filter((value): value is string => typeof value === "string" && Boolean(value)))];
  const products = (await Promise.all(slugs.map(async (slug) => {
    try { return await readProduct(slug); }
    catch (error) {
      if (error instanceof WebsiteApiError && error.status === 404) return null;
      throw error;
    }
  }))).filter((value): value is NonNullable<typeof value> => Boolean(value));

  return (
    <main className="mx-auto max-w-7xl px-4 py-10 sm:px-6">
      <h1 className="text-4xl font-bold">Compare products</h1>
      <p className="mt-3 text-slate-600">Compare public price, stock and variant availability without loading cart or checkout code.</p>
      <form className="mt-7 grid gap-3 rounded-2xl border bg-white p-4 sm:grid-cols-[1fr_1fr_auto]">
        <select name="a" defaultValue={params.a ?? ""} className="rounded-xl border p-3">
          <option value="">First product</option>
          {catalogue.items.map((product) => <option key={product.slug} value={product.slug}>{product.name}</option>)}
        </select>
        <select name="b" defaultValue={params.b ?? ""} className="rounded-xl border p-3">
          <option value="">Second product</option>
          {catalogue.items.map((product) => <option key={product.slug} value={product.slug}>{product.name}</option>)}
        </select>
        <button className="rounded-xl bg-slate-950 px-5 py-3 font-semibold text-white">Compare</button>
      </form>
      {products.length > 0 && <div className="mt-8 grid gap-5 sm:grid-cols-2">
        {products.map((product) => <article key={product.id} className="rounded-3xl border bg-white p-5">
          <ProductImage src={product.image_url} alt={product.name} />
          <h2 className="mt-4 text-2xl font-bold">{product.name}</h2>
          <p className="mt-2 text-xl font-semibold">{money(product.price, product.currency)}</p>
          <p className="mt-2 text-sm">{product.availability.in_stock ? `${product.availability.quantity} in stock` : "Out of stock"}</p>
          <p className="mt-4 text-sm text-slate-600">{product.variants.length} variant{product.variants.length === 1 ? "" : "s"}</p>
        </article>)}
      </div>}
    </main>
  );
}
