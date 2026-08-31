import "server-only";

export async function readBackendHealth(): Promise<boolean> {
  const origin = process.env.LARAVEL_API_ORIGIN ?? "http://127.0.0.1:18080";
  if (origin !== "http://127.0.0.1:18080") throw new Error("Foundation API origin must be the isolated Laravel target.");
  try {
    const response = await fetch(`${origin}/api/v1/health`, {
      cache: "no-store", signal: AbortSignal.timeout(3000), redirect: "error",
      headers: { Accept: "application/json" },
    });
    if (!response.ok) return false;
    const body: unknown = await response.json();
    if (typeof body !== "object" || body === null || !("data" in body)) return false;
    const data = body.data;
    return typeof data === "object" && data !== null
      && "service" in data && data.service === "mobisttech-backend"
      && "status" in data && data.status === "ok"
      && "contract" in data && data.contract === "v1";
  } catch { return false; }
}
