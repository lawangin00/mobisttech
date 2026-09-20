import type { Metadata } from "next";
import Link from "next/link";
import { notFound } from "next/navigation";
import { AddToCart } from "@/components/add-to-cart";
import { ProductImage } from "@/components/product-image";
import { money } from "@/lib/storefront";
import { readProduct, readWebsiteProfile, WebsiteApiError } from "@/lib/website-api";
import { readBusinessProfile } from "@/lib/business-profile";

export const dynamic = "force-dynamic";

async function productOr404(slug: string) {
  try {
    return await readProduct(slug);
  } catch (error) {
    if (error instanceof WebsiteApiError && error.status === 404) notFound();
    throw error;
  }
}

export async function generateMetadata({ params }: { params: Promise<{ slug: string }> }): Promise<Metadata> {
  const profile = await readWebsiteProfile();
  if (!profile?.capabilities.commerce) return { robots: { index: false, follow: false } };
  const { slug } = await params;
  const [product, business] = await Promise.all([productOr404(slug), readBusinessProfile()]);
  const description = product.description ?? `${product.name} availability and price at mobiST Technologies.`;
  const canonicalPath = `/products/${encodeURIComponent(product.slug)}`;
  const socialUrl = business ? new URL(canonicalPath, business.public_website).href : undefined;
  return {
    title: product.name,
    description,
    alternates: { canonical: canonicalPath },
    openGraph: { type: "website", title: product.name, description, ...(socialUrl ? { url: socialUrl } : {}) },
    twitter: { card: "summary", title: product.name, description },
  };
}

export default async function ProductPage({ params }: { params: Promise<{ slug: string }> }) {
  const [{ slug }, profile, business] = await Promise.all([params, readWebsiteProfile(), readBusinessProfile()]);
  if (!profile?.capabilities.commerce) notFound();
  const product = await productOr404(slug);
  const jsonLd = {
    "@context": "https://schema.org",
    "@type": "Product",
    name: product.name,
    brand: product.brand ? { "@type": "Brand", name: product.brand } : undefined,
    model: product.model ?? undefined,
    offers: {
      "@type": "Offer",
      priceCurrency: product.currency,
      price: product.price,
      availability: product.availability.in_stock ? "https://schema.org/InStock" : "https://schema.org/OutOfStock",
      ...(business ? { url: new URL(`/products/${encodeURIComponent(product.slug)}`, business.public_website).href } : {}),
    },
  };

  return (
    <main className="mx-auto max-w-7xl px-4 py-10 sm:px-6">
      <script type="application/ld+json" dangerouslySetInnerHTML={{ __html: JSON.stringify(jsonLd).replace(/</g, "\\u003c") }} />
      <p className="text-sm text-slate-500"><Link href="/products" prefetch={false}>Products</Link> / {product.category.label}</p>
      <div className="mt-5 grid gap-8 lg:grid-cols-2">
        <ProductImage src={product.image_url} alt={product.name} priority />
        <div>
          <p className="text-sm font-semibold uppercase tracking-wide text-slate-500">{product.brand ?? product.category.label}</p>
          <h1 className="mt-2 text-4xl font-bold">{product.name}</h1>
          {product.model && <p className="mt-2 text-slate-500">{product.model}</p>}
          {product.subcategory && <p className="mt-2 text-sm text-slate-500">{product.subcategory.label}</p>}
          <p className="mt-6 text-3xl font-bold">{money(product.price, product.currency)}</p>
          <p className={`mt-3 font-semibold ${product.availability.in_stock ? "text-emerald-700" : "text-slate-500"}`}>
            {product.availability.in_stock ? `${product.availability.quantity} available` : "Out of stock"}
          </p>
          <AddToCart product={{ id: product.id, slug: product.slug, name: product.name, price: product.price, available: product.availability.in_stock }} />
          {product.description && <p className="mt-6 whitespace-pre-wrap leading-7 text-slate-700">{product.description}</p>}
          {product.warranty_summary && <div className="mt-5 rounded-2xl bg-slate-50 p-4 text-sm"><strong>Warranty</strong><p className="mt-1">{product.warranty_summary}</p></div>}
        </div>
      </div>
      <section className="mt-12">
        <h2 className="text-2xl font-bold">Available variants</h2>
        <div className="mt-4 grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
          {product.variants.map((variant) => (
            <div key={variant.key} className="rounded-2xl border border-slate-200 bg-white p-4">
              <strong>{variant.label || "Standard"}</strong>
              <p className="mt-1 text-sm text-slate-500">{variant.availability.quantity} available</p>
              <dl className="mt-3 grid grid-cols-2 gap-x-3 gap-y-1 text-xs text-slate-600">
                {variant.color && <><dt>Color</dt><dd>{variant.color}</dd></>}
                {variant.condition && <><dt>Condition</dt><dd>{variant.condition}</dd></>}
                {variant.pta_status && <><dt>PTA</dt><dd>{variant.pta_status}</dd></>}
                {variant.carrier_lock_status && <><dt>Carrier</dt><dd>{variant.carrier_lock_status}</dd></>}
                {variant.mdm_status && <><dt>MDM</dt><dd>{variant.mdm_status}</dd></>}
              </dl>
            </div>
          ))}
        </div>
      </section>
      {product.reviews.length > 0 && <section className="mt-12">
        <h2 className="text-2xl font-bold">Reviews</h2>
        <div className="mt-4 grid gap-4 sm:grid-cols-2">
          {product.reviews.map((review, index) => <article key={index} className="rounded-2xl border bg-white p-5">
            <p className="font-semibold">{review.rating}/5 {review.title ?? ""}</p>
            {review.body && <p className="mt-2 text-sm text-slate-600">{review.body}</p>}
          </article>)}
        </div>
      </section>}
    </main>
  );
}
