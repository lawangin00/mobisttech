const BACKEND = process.env.LARAVEL_API_ORIGIN ?? "http://127.0.0.1:18080";
if (BACKEND !== "http://127.0.0.1:18080") throw new Error("Public API proxy must use the isolated Laravel target.");

export async function POST(request: Request) {
  const ownOrigin = new URL(request.url).origin;
  const origin = request.headers.get("origin");
  if (origin && origin !== ownOrigin) return new Response("Origin mismatch.", { status: 403 });
  if (!request.headers.get("content-type")?.toLowerCase().startsWith("application/json")) {
    return new Response("JSON body required.", { status: 415 });
  }
  const idempotencyKey = request.headers.get("idempotency-key")?.trim();
  if (!idempotencyKey || idempotencyKey.length > 120) return new Response("Idempotency-Key required.", { status: 422 });

  const body = await request.text();
  if (body.length > 25000) return new Response("Request body too large.", { status: 413 });

  const upstream = await fetch(BACKEND + "/api/v1/enquiries", {
    method: "POST",
    redirect: "error",
    cache: "no-store",
    signal: AbortSignal.timeout(8000),
    headers: {
      Accept: "application/json",
      "Content-Type": "application/json",
      "Idempotency-Key": idempotencyKey,
      "User-Agent": request.headers.get("user-agent") ?? "mobisttech-website",
    },
    body,
  });
  const responseBody = await upstream.text();
  return new Response(responseBody, {
    status: upstream.status,
    headers: {
      "Content-Type": upstream.headers.get("content-type") ?? "application/json",
      "Cache-Control": "private, no-store",
    },
  });
}
