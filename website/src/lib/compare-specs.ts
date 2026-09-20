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

// Source chooser was phone/tablet only; preserve the target's already accepted
// accessory comparison until product owner explicitly decides that scope difference.
export function publicCompareEligible(product: Pick<ProductDetail, "category">): boolean {
  return ["mobile_phone", "tablet", "accessory"].includes(product.category.code);
}
