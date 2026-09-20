// Preserve original public catalogue links without passing deprecated names to Laravel.
// Explicit canonical keys win, even when empty (a user cleared the current filter).
type CatalogueQuery = Record<string, string | string[] | undefined>;
const legacyToCanonical = { pta: "pta_status", ram: "ram_gb", storage: "storage_gb" } as const;

export function normalizeCatalogueFilters(input: CatalogueQuery): CatalogueQuery {
  const normalized: CatalogueQuery = { ...input };
  for (const [legacy, canonical] of Object.entries(legacyToCanonical)) {
    if (normalized[canonical] !== undefined) continue;
    const raw = input[legacy];
    if (typeof raw === "string" && raw.trim() !== "") normalized[canonical] = raw.trim();
  }
  return normalized;
}
