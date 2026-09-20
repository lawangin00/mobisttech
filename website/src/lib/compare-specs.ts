import type { ProductDetail } from "@/lib/website-api";

// Only project-safe public POS configuration and sellable/available variant fields.
export function publicCompareSpec(product: ProductDetail, field: "ram" | "storage" | "sim" | "condition" | "pta" | "colors"): string {
  if (field === "ram") return product.device.ram_gb === null ? "—" : `${product.device.ram_gb} GB`;
  if (field === "storage") return product.device.storage_gb === null ? "—" : `${product.device.storage_gb} GB`;
  if (field === "sim") return product.device.sim || "—";
  const attribute = field === "pta" ? "pta_status" : field === "colors" ? "color" : "condition";
  const values = [...new Set(product.variants.map((variant) => variant[attribute]).filter(
    (value): value is string => typeof value === "string" && value.trim() !== "" && !["unknown", "n/a", "not applicable"].includes(value.toLowerCase()),
  ))];
  return values.length ? values.join(" / ") : "—";
}

// Owner-approved policy: compare products within the SAME category only.
// Mobiles, tablets, and accessories may each be compared to their own category.
export function publicCompareEligible(product: Pick<ProductDetail, "category">): boolean {
  return compareCategoryCodes.includes(product.category.code as CompareCategory);
}

export const compareCategoryCodes = ["mobile_phone", "tablet", "accessory"] as const;
export type CompareCategory = typeof compareCategoryCodes[number];

export function resolveCompareCategory(requested: unknown, candidates: readonly Pick<ProductDetail, "category">[]): CompareCategory | null {
  if (typeof requested === "string" && compareCategoryCodes.includes(requested as CompareCategory))
    return requested as CompareCategory;
  const first = candidates.find(publicCompareEligible);
  return first ? first.category.code as CompareCategory : null;
}

export function sameCategoryProducts<T extends Pick<ProductDetail, "category">>(products: readonly T[], category: CompareCategory | null): T[] {
  return category ? products.filter((product) => publicCompareEligible(product) && product.category.code === category) : [];
}
