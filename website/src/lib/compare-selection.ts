// Four public comparison slots match the source catalogue's bounded selection contract.
export const comparisonSlotNames = ["a", "b", "c", "d"] as const;
type CompareQuery = Record<string, string | string[] | undefined>;

const safeSlug = (value: unknown): string => typeof value === "string" && value.length <= 160
  && /^[a-z0-9]+(?:-[a-z0-9]+)*$/.test(value) ? value : "";

export function comparisonSlots(params: CompareQuery): string[] {
  const named = comparisonSlotNames.map((key) => safeSlug(params[key]));
  if (comparisonSlotNames.some((key) => params[key] !== undefined)) return named;
  const legacy = params.products;
  if (typeof legacy !== "string" || legacy.length > 643) return named;
  return comparisonSlotNames.map((_, index) => safeSlug(legacy.split(",", 4)[index]?.trim()));
}

export function comparisonSlugs(slots: readonly string[]): string[] {
  return [...new Set(slots.filter(Boolean))].slice(0, comparisonSlotNames.length);
}
