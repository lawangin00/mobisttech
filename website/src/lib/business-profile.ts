import "server-only";

export type BusinessProfile = {
  business_name: string;
  business_email: string;
  public_website: string;
  version: number;
};

export async function readBusinessProfile(): Promise<BusinessProfile | null> {
  const origin = process.env.LARAVEL_API_ORIGIN ?? "http://127.0.0.1:18080";
  if (origin !== "http://127.0.0.1:18080") throw new Error("Website API origin must be the isolated Laravel target.");
  try {
    const response = await fetch(`${origin}/api/v1/business-profile`, { cache: "no-store", redirect: "error", signal: AbortSignal.timeout(3000) });
    if (!response.ok) return null;
    const body: unknown = await response.json();
    if (typeof body !== "object" || body === null || !("data" in body)) return null;
    const value = body.data;
    if (typeof value !== "object" || value === null) return null;
    const candidate = value as Partial<BusinessProfile>;
    if (typeof candidate.business_name !== "string" || typeof candidate.business_email !== "string"
      || typeof candidate.public_website !== "string" || typeof candidate.version !== "number") return null;
    return candidate as BusinessProfile;
  } catch { return null; }
}
