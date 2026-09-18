import Link from "next/link";
import { ProductImage } from "@/components/product-image";
import { money } from "@/lib/storefront";
import type { CatalogueProduct } from "@/lib/website-api";

export function ProductCard({
  product,
  priority = false,
}: {
  product: CatalogueProduct;
  priority?: boolean;
}) {
  return (
    <article className="min-w-0 rounded-3xl border border-slate-200 bg-white p-4 shadow-sm">
      <Link href={`/products/${product.slug}`} prefetch={false} aria-label={product.name}>
        <ProductImage src={product.image_url} alt={product.name} priority={priority} />
      </Link>
      <div className="mt-4 flex items-start justify-between gap-3">
        <div className="min-w-0">
          <p className="text-xs font-semibold uppercase tracking-wide text-slate-500">
            {product.brand ?? product.category.label}
          </p>
          <h2 className="mt-1 text-lg font-semibold text-slate-950">
            <Link href={`/products/${product.slug}`} prefetch={false}>{product.name}</Link>
          </h2>
          {product.model && <p className="mt-1 text-sm text-slate-500">{product.model}</p>}
        </div>
        <span
          className={`shrink-0 rounded-full px-2.5 py-1 text-xs font-semibold ${
            product.availability.in_stock
              ? "bg-emerald-50 text-emerald-700"
              : "bg-slate-100 text-slate-600"
          }`}
        >
          {product.availability.in_stock
            ? `${product.availability.quantity} in stock`
            : "Out of stock"}
        </span>
      </div>
      <div className="mt-4 flex items-center justify-between gap-3">
        <strong className="text-lg">{money(product.price, product.currency)}</strong>
        <Link
          href={`/products/${product.slug}`} prefetch={false}
          className="rounded-full bg-slate-950 px-4 py-2 text-sm font-semibold text-white"
        >
          View
        </Link>
      </div>
    </article>
  );
}
