import type { Metadata } from "next";
import { notFound } from "next/navigation";
import { ProductImage } from "@/components/product-image";
import { money } from "@/lib/storefront";
import { comparisonSlotNames, comparisonSlots, comparisonSlugs } from "@/lib/compare-selection";
import { compareCategoryCodes, publicCompareEligible, publicCompareSpec, resolveCompareCategory, sameCategoryProducts } from "@/lib/compare-specs";
import { readCatalogue, readProduct, readWebsiteProfile, WebsiteApiError } from "@/lib/website-api";

export const dynamic = "force-dynamic";
export const metadata: Metadata = {
  title: "Compare products",
  description: "Compare public product price, stock and variant availability.",
  robots: { index: false, follow: true },
};

export default async function Compare({ searchParams }: { searchParams: Promise<Record<string, string | string[] | undefined>> }) {
  const [params, profile] = await Promise.all([searchParams, readWebsiteProfile()]);
  if (!profile?.capabilities.commerce) notFound();
  const rawSearch = typeof params.q === "string" ? params.q.trim() : "";
  const q = rawSearch.length >= 2 && rawSearch.length <= 80 ? rawSearch : "";
  const requestedSlots = comparisonSlots(params);
  const slugs = comparisonSlugs(requestedSlots);
  const published = (await Promise.all(slugs.map(async (slug) => {
    try { return await readProduct(slug); }
    catch (error) {
      if (error instanceof WebsiteApiError && error.status === 404) return null;
      throw error;
    }
  }))).filter((value): value is NonNullable<typeof value> => value !== null && publicCompareEligible(value));

  const category = resolveCompareCategory(params.category, published);
  const products = sameCategoryProducts(published, category);
  const allowedSlugs = new Set(products.map((product) => product.slug));
  const slots = requestedSlots.map((slug) => allowedSlugs.has(slug) ? slug : "");
  const catalogue = category ? await readCatalogue({ limit: 24, q: q || undefined, category }) : { items: [] };
  const choices = sameCategoryProducts(catalogue.items, category);
  for (const product of products) {
    if (!choices.some((choice) => choice.slug === product.slug)) choices.push(product);
  }

  return (
    <main className="mx-auto max-w-7xl px-4 py-10 sm:px-6">
      <h1 className="text-4xl font-bold">Compare products</h1>
      <p className="mt-3 text-slate-600">Compare up to four published products from the same category only. Mobiles, tablets and accessories are separate comparison groups.</p>
      <form action="/compare" className="mt-7 flex flex-wrap gap-3 rounded-2xl border bg-white p-4">
        <label htmlFor="comparison-category" className="self-center font-semibold">Compare category</label>
        <select id="comparison-category" name="category" aria-label="Compare category" defaultValue={category ?? ""} required
          className="min-w-0 flex-1 rounded-xl border p-3">
          <option value="">Choose category</option>
          {compareCategoryCodes.map((code) => <option key={code} value={code}>{({ mobile_phone: "Mobiles", tablet: "Tablets", accessory: "Accessories" })[code]}</option>)}
        </select>
        <button className="rounded-xl border px-5 py-3 font-semibold">Choose category</button>
      </form>
      {category && <p className="mt-3 text-sm text-slate-600">Only products in the selected category can be compared. Choosing a different category clears previous selections.</p>}
      {category && <form action="/compare" className="mt-4 flex flex-wrap gap-3 rounded-2xl border bg-white p-4">
        <input type="hidden" name="category" value={category ?? ""} />
        {comparisonSlotNames.map((name, index) => <input key={name} type="hidden" name={name} value={slots[index]} />)}
        <input name="q" aria-label="Search comparison products" defaultValue={q} minLength={2} maxLength={80} placeholder="Search published products" className="min-w-0 flex-1 rounded-xl border p-3" />
        <button className="rounded-xl border px-5 py-3 font-semibold">Find products</button>
      </form>}
      {category && <form action="/compare" className="mt-3 grid gap-3 rounded-2xl border bg-white p-4 sm:grid-cols-2 lg:grid-cols-4">
        <input type="hidden" name="category" value={category ?? ""} />
        {q && <input type="hidden" name="q" value={q} />}
        {comparisonSlotNames.map((name, index) => (
          <select key={name} name={name} aria-label={`Comparison product ${index + 1}`}
            defaultValue={slots[index]} className="min-w-0 rounded-xl border p-3">
            <option value="">{["First", "Second", "Third", "Fourth"][index]} product</option>
            {choices.map((product) => <option key={product.slug} value={product.slug}>{product.name}</option>)}
          </select>
        ))}
        <button className="rounded-xl bg-slate-950 px-5 py-3 font-semibold text-white">Compare</button>
      </form>}
      {products.length > 0 && <div className="mt-8 grid gap-5 sm:grid-cols-2">
        {products.map((product) => <article key={product.id} className="rounded-3xl border bg-white p-5">
          <ProductImage src={product.image_url} alt={product.name} />
          <h2 className="mt-4 text-2xl font-bold">{product.name}</h2>
          <p className="mt-2 text-xl font-semibold">{money(product.price, product.currency)}</p>
          <p className="mt-2 text-sm">{product.availability.in_stock ? `${product.availability.quantity} in stock` : "Out of stock"}</p>
          <p className="mt-4 text-sm text-slate-600">{product.variants.length} variant{product.variants.length === 1 ? "" : "s"}</p>
        </article>)}
      </div>}
      {products.length > 0 && <div className="mt-8 overflow-x-auto rounded-2xl border bg-white" data-testid="compare-specs">
        <table className="w-full min-w-[720px] border-collapse text-left text-sm">
          <caption className="px-4 py-3 text-left font-semibold">Public product specifications</caption>
          <thead><tr><th scope="col" className="border-b p-3">Specification</th>
            {products.map((product) => <th scope="col" key={product.id} className="border-b p-3">{product.name}</th>)}
          </tr></thead>
          <tbody>{([
            ["Category", (product: typeof products[number]) => product.category.label],
            ["Brand", (product: typeof products[number]) => product.brand || "—"],
            ["Model", (product: typeof products[number]) => product.model || "—"],
            ["RAM", (product: typeof products[number]) => publicCompareSpec(product, "ram")],
            ["Storage", (product: typeof products[number]) => publicCompareSpec(product, "storage")],
            ["SIM", (product: typeof products[number]) => publicCompareSpec(product, "sim")],
            ["Condition", (product: typeof products[number]) => publicCompareSpec(product, "condition")],
            ["PTA status", (product: typeof products[number]) => publicCompareSpec(product, "pta")],
            ["Colors", (product: typeof products[number]) => publicCompareSpec(product, "colors")],
            ["Warranty", (product: typeof products[number]) => product.warranty_summary || "—"],
          ] as const).map(([label, value]) => <tr key={label}>
            <th scope="row" className="border-b p-3 font-semibold">{label}</th>
            {products.map((product) => <td key={product.id} className="border-b p-3">{value(product)}</td>)}
          </tr>)}</tbody>
        </table>
      </div>}
    </main>
  );
}
