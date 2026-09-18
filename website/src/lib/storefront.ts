import "server-only";
import { readBusinessProfile } from "@/lib/business-profile";
import { readWebsiteProfile } from "@/lib/website-api";

export async function readStorefrontContext() {
  const [business, website] = await Promise.all([readBusinessProfile(), readWebsiteProfile()]);
  return { business, website };
}
export function money(value: string, currency = "PKR") {
  const numeric = Number(value);
  return Number.isFinite(numeric)
    ? new Intl.NumberFormat("en-PK", { style: "currency", currency, maximumFractionDigits: 0 }).format(numeric)
    : `${currency} ${value}`;
}
export function modeLabel(mode: string | null | undefined) {
  return mode ? mode.replaceAll("_", " ") : "unpublished";
}
