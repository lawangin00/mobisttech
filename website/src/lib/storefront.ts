import "server-only";
import { cache } from "react";
import { readWebsiteProfile } from "@/lib/website-api";

export const readStorefrontContext = cache(async function readStorefrontContext() {
  const website = await readWebsiteProfile();
  return { business: website?.business ?? null, website, content: website?.content ?? null };
});
export function money(value: string, currency = "PKR") {
  const numeric = Number(value);
  return Number.isFinite(numeric)
    ? new Intl.NumberFormat("en-PK", { style: "currency", currency, maximumFractionDigits: 0 }).format(numeric)
    : `${currency} ${value}`;
}
export function modeLabel(mode: string | null | undefined) {
  return mode ? mode.replaceAll("_", " ") : "unpublished";
}
