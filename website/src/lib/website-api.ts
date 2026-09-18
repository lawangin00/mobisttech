import "server-only";

export type WebsiteMode = "digital_only" | "hybrid" | "commerce_only" | null;
export type WebsiteProfile = {
  mode: WebsiteMode;
  version: number;
  published_at: string | null;
  capabilities: { digital: boolean; commerce: boolean };
  content_scopes: string[];
  copy_variant: WebsiteMode;
  routes: string[];
  api_operations: string[];
  ctas: string[];
  seo_sitemap: {
    discoverable_routes?: string[];
    excluded_routes?: string[];
    historical_routes_indexable?: boolean;
    inactive_capabilities_indexable?: boolean;
  };
  historical_access: Record<string, { allowed: boolean; authenticated_only: boolean; indexable: boolean }>;
  cache_namespace: string;
};
export type Category = { code: string; label: string; products: number };
export type ProductVariant = {
  key: string; label: string; color: string | null; condition: string | null; pta_status: string | null;
  carrier_lock_status: string | null; mdm_status: string | null;
  availability: { in_stock: boolean; quantity: number };
};
export type CatalogueProduct = {
  id: string; listing_id: string; slug: string; name: string; brand: string | null; model: string | null;
  category: { code: string; label: string }; price: string; currency: "PKR";
  availability: { in_stock: boolean; quantity: number }; warranty_type: string | null; image_url: string | null;
};
export type ProductDetail = CatalogueProduct & {
  description: string | null; warranty_summary: string | null; variants: ProductVariant[];
  reviews: Array<{ rating: number; title: string | null; body: string | null; approved_at: string | null }>;
};
export type CataloguePage = {
  items: CatalogueProduct[];
  page: { limit: number; has_more: boolean; next_cursor: string | null };
};

type Envelope<T> = { contract: string; data: T };
const ORIGIN = process.env.LARAVEL_API_ORIGIN ?? "http://127.0.0.1:18080";
if (ORIGIN !== "http://127.0.0.1:18080") throw new Error("Website API origin must be the isolated Laravel target.");

export class WebsiteApiError extends Error {
  constructor(public status: number, message: string) { super(message); }
}

async function get<T>(path: string, freshness: "live" | number = "live"): Promise<T> {
  const response = await fetch(`${ORIGIN}${path}`, {
    redirect: "error",
    headers: { Accept: "application/json" },
    signal: AbortSignal.timeout(4000),
    ...(freshness === "live" ? { cache: "no-store" as const } : { next: { revalidate: freshness } }),
  });
  if (!response.ok) throw new WebsiteApiError(response.status, `Website API request failed: ${path}`);
  const body: unknown = await response.json();
  if (typeof body !== "object" || body === null || !("data" in body)) throw new Error("Website API envelope is invalid.");
  return (body as Envelope<T>).data;
}

export async function readWebsiteProfile(): Promise<WebsiteProfile | null> {
  try { return await get<WebsiteProfile>("/api/v1/website-profile", "live"); }
  catch { return null; }
}
export function readCategories() { return get<Category[]>("/api/v1/catalogue/categories", 60); }
export function readProduct(slug: string) { return get<ProductDetail>(`/api/v1/catalogue/products/${encodeURIComponent(slug)}`, "live"); }
export function readCatalogue(input: { limit?: number; after?: string; category?: string; q?: string } = {}) {
  const query = new URLSearchParams();
  query.set("limit", String(input.limit ?? 12));
  if (input.after) query.set("after", input.after);
  if (input.category) query.set("category", input.category);
  if (input.q) query.set("q", input.q);
  return get<CataloguePage>(`/api/v1/catalogue/products?${query.toString()}`, "live");
}
