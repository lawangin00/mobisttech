import "server-only";
import { cache } from "react";

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
  business: { business_name: string; business_email: string; public_website: string; version: number };
  content: ContentIndex;
};
export type CataloguePresentation = {
  grid_desktop: 3 | 4 | 5 | 6; grid_tablet: 2 | 3 | 4; grid_mobile: 1 | 2;
  image_ratio: "16:9" | "4:3" | "1:1"; card_density: "compact" | "comfortable";
  items_per_page: 12 | 24 | 36 | 48;
  default_sort: "newest" | "oldest" | "price_asc" | "price_desc" | "name_asc" | "name_desc";
  badge_behavior: "status_text" | "status_pill" | "hidden";
  show_brand: boolean; show_specs: boolean; show_colors: boolean;
  show_warranty: boolean; show_compare: boolean; show_out_of_stock: true;
};
export const DEFAULT_CATALOGUE_PRESENTATION: CataloguePresentation = {
  grid_desktop: 3, grid_tablet: 2, grid_mobile: 1, image_ratio: "4:3",
  card_density: "comfortable", items_per_page: 12, default_sort: "oldest",
  badge_behavior: "status_pill", show_brand: true, show_specs: true, show_colors: true,
  show_warranty: true, show_compare: true, show_out_of_stock: true,
};
export type Category = { code: string; label: string; products: number };
export type ProductVariant = {
  key: string; label: string; color: string | null; condition: string | null; pta_status: string | null;
  carrier_lock_status: string | null; mdm_status: string | null;
  availability: { in_stock: boolean; quantity: number };
};
export type CatalogueProduct = {
  id: string; listing_id: string; slug: string; name: string; brand: string | null; model: string | null;
  category: { code: string; label: string }; subcategory: {code:string;label:string}|null; price: string; currency: "PKR";
  availability: { in_stock: boolean; quantity: number }; warranty_type: string | null; warranty_summary?: string | null; specs?: {ram_gb:number|null;storage_gb:number|null;pta_statuses:string[]}; colors?: string[]; image_url: string | null;
};
export type ProductDetail = CatalogueProduct & {
  description: string | null; warranty_summary: string | null; variants: ProductVariant[];
  device: { ram_gb: number | null; storage_gb: number | null; sim: string | null };
  reviews: Array<{ rating: number; title: string | null; body: string | null; approved_at: string | null }>;
};
export type CataloguePage = {
  items: CatalogueProduct[];
  page: { limit: number; has_more: boolean; next_cursor: string | null };
};
export type ManagedPage = {
  public_id: string; version: number; published_at: string | null; sha256: string;
  related_testimonials?: Array<{ slug: string; title: string }>;
  snapshot: {
    title: string; slug: string; content: string; template: string; show_in_navigation: boolean;
    seo_title: string | null; meta_description: string | null; canonical_url: string | null;
    social_title: string | null; social_description: string | null; social_image_media_id: number | null; is_indexable: boolean;
    content_purpose: string; capability_scope: string; structured_content: Record<string, unknown>; service_slugs: string[];
  };
};
export type Policy = {
  type: string; slug: string; title: string; footer_destination: string | null;
  effective_date: string; version: number; content: string; content_sha256: string;
};
export type ServiceOffer = { public_id: string; code: string; name: string; pricing_type: string; price: string | null; currency: "PKR"; version: number };
export type ServiceLanding = {
  slug: string; title: string; content: string;
  seo_title: string | null; meta_description: string | null;
  structured: Record<string, unknown>;
};
export type RelatedServicePage = { slug: string; title: string; purpose: "case_study" | "digital_testimonial"; display_order?: number | null };
export type DigitalService = {
  slug: string; name: string; category: string | null; short_description: string; description: string | null;
  price_type: "quote" | "fixed" | "starting_from" | "package"; price: string | null;
  packages: ServiceOffer[]; addons: ServiceOffer[];
  landing?: ServiceLanding | null; related_pages?: RelatedServicePage[];
};
export type ConsultationAvailability = {
  enabled: boolean; timezone: string | null;
  weekly_availability: Array<{ day: number; start: string; end: string }>;
};
export type SoftwareRelease = {
  public_id: string; version: string; release_date: string; summary: string;
  notes: { added?: string[]; changed?: string[]; fixed?: string[]; security?: string[] }; sha256: string;
};
export type SitePromotion = {
  announcement: { text: string; href: string | null; scope: string } | null;
  banners: Array<{ title: string; body: string; href: string | null; scope: string }>;
};
export type GlobalSeo = { title: string | null; description: string | null; social_title: string | null; social_description: string | null; canonical_url: string | null };
export type ContentIndex = {
  catalogue?: CataloguePresentation;
  homepage: { sections?: Array<{ key: "hero" | "products" | "solutions" | "about" | "contact"; enabled: boolean; order: number }> };
  header_footer: { footer_description?: string | null; footer_copyright?: string | null; show_account?: boolean; show_contact?: boolean; show_policies?: boolean; show_search?: boolean; show_cart?: boolean; sticky?: boolean; footer_show_logo?: boolean; footer_show_navigation?: boolean; footer_navigation_layout?: "one_column" | "two_columns"; contact_cta?: "hidden" | "contact"; contact_cta_label?: string | null };
  seo: GlobalSeo;
  theme: Partial<Record<"primary" | "primary_hover" | "secondary" | "accent" | "background" | "surface" | "text" | "muted_text" | "border", string>>;
  branding: Record<"main_logo" | "wordmark" | "header_logo" | "footer_logo" | "square_icon" | "favicon" | "social_image", number | null>;
  promotion: SitePromotion;
  pages: Array<{ slug: string; title: string; purpose: string; scope: string; show_in_navigation: boolean; category?: string | null; tags?: string[]; service_slugs?: string[] }>;
  policies: Array<Pick<Policy, "type" | "slug" | "title" | "footer_destination" | "effective_date" | "version">>;
  software: Array<{ slug: string; name: string; routes: Record<string, string>; sitemap: boolean }>;
  navigation: Array<{
    key: string; parent_key: string | null; label: string; destination_type: "page" | "route" | "url"; destination_key: string | null;
    destination_payload: Record<string, unknown> | null; target_behavior: "same_tab" | "new_tab"; scope: string;
  }>;
};
export type SoftwareOverview = {
  public_id: string; slug: string; revision: number; published_at: string | null; current_version: string | null;
  overview: {
    name: string; summary: string; overview: string; features: Array<{ title: string; description: string }>;
    platforms: string[]; system_requirements: string; limitations: string[]; support: Record<string, unknown>;
    cta: Record<string, unknown>; seo: Record<string, unknown>;
    logo_media_id: number | null; hero_media_id: number | null; screenshot_media_ids: number[];
  };
  latest_releases: SoftwareRelease[];
  routes: Record<string, string>; sha256: string;
};

type Envelope<T> = { contract: string; data: T };
const ORIGIN = process.env.LARAVEL_API_ORIGIN ?? "http://127.0.0.1:18080";
const API_TIMEOUT_MS = Number(process.env.WEBSITE_API_TIMEOUT_MS ?? "4000");
if (!Number.isFinite(API_TIMEOUT_MS) || API_TIMEOUT_MS < 1000 || API_TIMEOUT_MS > 30000) throw new Error("WEBSITE_API_TIMEOUT_MS is invalid.");
if (ORIGIN !== "http://127.0.0.1:18080") throw new Error("Website API origin must be the isolated Laravel target.");

export class WebsiteApiError extends Error {
  constructor(public status: number, message: string) { super(message); }
}

async function get<T>(path: string, freshness: "live" | number = "live"): Promise<T> {
  const response = await fetch(`${ORIGIN}${path}`, {
    redirect: "error",
    headers: { Accept: "application/json" },
    signal: AbortSignal.timeout(API_TIMEOUT_MS),
    ...(freshness === "live" ? { cache: "no-store" as const } : { next: { revalidate: freshness } }),
  });
  if (!response.ok) throw new WebsiteApiError(response.status, `Website API request failed: ${path}`);
  const body: unknown = await response.json();
  if (typeof body !== "object" || body === null || !("data" in body)) throw new Error("Website API envelope is invalid.");
  return (body as Envelope<T>).data;
}

async function readWebsiteProfileUncached(): Promise<WebsiteProfile | null> {
  try {
    return await get<WebsiteProfile>("/api/v1/website-profile", "live");
  } catch (error) {
    if (error instanceof WebsiteApiError && error.status === 404) return null;
    throw error;
  }
}
export const readWebsiteProfile = cache(readWebsiteProfileUncached);
export function readCategories() { return get<Category[]>("/api/v1/catalogue/categories", 60); }
export function readProduct(slug: string) { return get<ProductDetail>(`/api/v1/catalogue/products/${encodeURIComponent(slug)}`, "live"); }
export function readCatalogue(input: { limit?: number; after?: string; category?: string; subcategory?: string; availability?: string; q?: string; sort?: string; min_price?: string; max_price?: string; brand?: string; model?: string; condition?: string; pta_status?: string; ram_gb?: string; storage_gb?: string } = {}) {
  const query = new URLSearchParams();
  query.set("limit", String(input.limit ?? 12));
  if (input.after) query.set("after", input.after);
  if (input.category) query.set("category", input.category);
  if (input.subcategory) query.set("subcategory", input.subcategory);
  if (input.availability) query.set("availability", input.availability);
  if (input.q) query.set("q", input.q);
  if (input.sort) query.set("sort", input.sort);
  if (input.min_price) query.set("min_price", input.min_price);
  if (input.max_price) query.set("max_price", input.max_price);
  if (input.brand) query.set("brand", input.brand);
  if (input.model) query.set("model", input.model);
  if (input.condition) query.set("condition", input.condition);
  if (input.pta_status) query.set("pta_status", input.pta_status);
  if (input.ram_gb) query.set("ram_gb", input.ram_gb);
  if (input.storage_gb) query.set("storage_gb", input.storage_gb);
  return get<CataloguePage>(`/api/v1/catalogue/products?${query.toString()}`, "live");
}
export function readContentIndex() { return get<ContentIndex>("/api/v1/content", "live"); }
export function readManagedPage(slug: string) { return get<ManagedPage>(`/api/v1/content/pages/${encodeURIComponent(slug)}`, "live"); }
export function readPolicies() { return get<{ items: Policy[] }>("/api/v1/content/policies", "live").then((value) => value.items); }
export function readServices() { return get<{ items: DigitalService[] }>("/api/v1/services", "live").then((value) => value.items); }
export function readConsultationAvailability() { return get<ConsultationAvailability>("/api/v1/consultation", 60); }
export function readSoftware(slug: string) { return get<SoftwareOverview>(`/api/v1/software/${encodeURIComponent(slug)}`, "live"); }
export function readSoftwareSection<T>(slug: string, section: "privacy" | "terms" | "faq" | "releases") {
  return get<T>(`/api/v1/software/${encodeURIComponent(slug)}/${section}`, "live");
}

export function readSoftwareRedirect(path: string) { return get<{ to_path: string }>(`/api/v1/software-redirect?path=${encodeURIComponent(path)}`, "live"); }
