import Link from "next/link";
import { ProductImage } from "@/components/product-image";
import { money } from "@/lib/storefront";
import { DEFAULT_CATALOGUE_PRESENTATION, type CataloguePresentation, type CatalogueProduct } from "@/lib/website-api";

const defaultCardPresentation: CataloguePresentation = {...DEFAULT_CATALOGUE_PRESENTATION, show_colors:false, show_warranty:false, show_compare:false};

export function ProductCard({
  product,
  priority = false,
  presentation = defaultCardPresentation,
}: {
  product: CatalogueProduct;
  priority?: boolean;
  presentation?: CataloguePresentation;
}) {
  return (
    <article className={`min-w-0 rounded-3xl border border-slate-200 bg-white shadow-sm ${presentation.card_density === "compact" ? "p-2" : "p-4"}`}>
      <Link href={`/products/${product.slug}`} prefetch={false} aria-label={product.name}>
        <ProductImage src={product.image_url} alt={product.name} priority={priority} ratio={presentation.image_ratio} />
      </Link>
      <div className="mt-4 flex items-start justify-between gap-3">
        <div className="min-w-0">
          {presentation.show_brand && <p className="text-xs font-semibold uppercase tracking-wide text-slate-500">
            {product.brand ?? product.category.label}
          </p>}
          <h2 className="mt-1 text-lg font-semibold text-slate-950">
            <Link href={`/products/${product.slug}`} prefetch={false}>{product.name}</Link>
          </h2>
          {presentation.show_specs && <div className="mt-1 flex flex-wrap gap-1 text-xs text-slate-500">
            {product.subcategory && <span>{product.subcategory.label}</span>}
            {product.specs?.ram_gb && <span>{product.specs.ram_gb} GB RAM</span>}
            {product.specs?.storage_gb && <span>{product.specs.storage_gb} GB storage</span>}
            {product.specs?.pta_statuses?.map(status=><span key={status}>{status.replaceAll("_", " ")}</span>)}
            {!product.subcategory && !product.specs?.ram_gb && !product.specs?.storage_gb && product.model && <span>{product.model}</span>}
          </div>}
          {presentation.show_colors && (product.colors?.length ?? 0) > 0 && <p className="mt-1 text-xs text-slate-500">Colors: {product.colors?.join(", ")}</p>}
          {presentation.show_warranty && product.warranty_summary && <p className="mt-1 text-xs text-slate-500">{product.warranty_summary}</p>}
        </div>
        {presentation.badge_behavior !== "hidden" && <span
          className={`shrink-0 px-2.5 py-1 text-xs font-semibold ${presentation.badge_behavior === "status_pill" ? "rounded-full" : ""} ${
            product.availability.in_stock
              ? "bg-emerald-50 text-emerald-700"
              : "bg-slate-100 text-slate-600"
          }`}
        >
          {product.availability.in_stock
            ? `${product.availability.quantity} in stock`
            : "Out of stock"}
        </span>}
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
      {presentation.show_compare && <Link href={`/compare?category=${encodeURIComponent(product.category.code)}&a=${encodeURIComponent(product.slug)}`} className="mt-2 inline-block text-xs underline">Compare</Link>}
    </article>
  );
}
