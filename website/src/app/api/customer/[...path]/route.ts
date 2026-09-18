import { NextRequest, NextResponse } from "next/server";

const ORIGIN = process.env.LARAVEL_API_ORIGIN ?? "http://127.0.0.1:18080";
if (ORIGIN !== "http://127.0.0.1:18080") {
  throw new Error("Customer API proxy must target the isolated Laravel origin.");
}

const ALLOWED = [
  /^auth\/(?:csrf-cookie|register|login|logout|forgot-password|reset-password|password|activity|confirm-password)$/,
  /^account$/,
  /^cart\/quote$/,
  /^checkout\/channels$/,
  /^payments\/[0-9a-f-]+\/initiate$/,
  /^project-milestones\/pay$/,
  /^project-payment-channels$/,
  /^projects$/,
  /^projects\/[0-9a-f-]+$/,
  /^projects\/[0-9a-f-]+\/files\/reference$/,
  /^projects\/[0-9a-f-]+\/files\/[0-9a-f-]+$/,
  /^orders(?:\/[0-9a-f-]+(?:\/(?:cancel|payments\/retry))?)?$/,
  /^wishlist(?:\/[0-9a-f-]+)?$/,
  /^wishlist\/claim$/,
  /^guest-wishlist(?:\/[0-9a-f-]+)?$/,
  /^notification-preferences$/,
  /^product-subscriptions(?:\/[0-9a-f-]+)?$/,
  /^reviews(?:\/eligible)?$/,
  /^loyalty$/,
] as const;

function allowed(path: string) {
  return ALLOWED.some((pattern) => pattern.test(path));
}

async function forward(request: NextRequest, context: { params: Promise<{ path?: string[] }> }) {
  const params = await context.params;
  const path = (params.path ?? []).join("/");
  if (!allowed(path)) return NextResponse.json({ message: "Not found." }, { status: 404 });

  const target = new URL(`/api/v1/${path}`, ORIGIN);
  target.search = request.nextUrl.search;
  const headers = new Headers({ Accept: "application/json" });
  const contentType = request.headers.get("content-type");
  const csrf = request.headers.get("x-csrf-token");
  const idempotency = request.headers.get("idempotency-key");
  const cookie = request.headers.get("cookie");
  if (contentType) headers.set("Content-Type", contentType);
  if (csrf) headers.set("X-CSRF-TOKEN", csrf);
  if (idempotency) headers.set("Idempotency-Key", idempotency);
  if (cookie) headers.set("Cookie", cookie);

  const body = ["GET", "HEAD"].includes(request.method) ? undefined : await request.arrayBuffer();
  const upstream = await fetch(target, {
    method: request.method,
    headers,
    body,
    redirect: "manual",
    cache: "no-store",
    signal: AbortSignal.timeout(8000),
  });

  const responseHeaders = new Headers();
  const upstreamType = upstream.headers.get("content-type");
  if (upstreamType) responseHeaders.set("Content-Type", upstreamType);
  const disposition = upstream.headers.get("content-disposition");
  if (disposition) responseHeaders.set("Content-Disposition", disposition);
  const contentTypeOptions = upstream.headers.get("x-content-type-options");
  if (contentTypeOptions) responseHeaders.set("X-Content-Type-Options", contentTypeOptions);
  const cookieHeaders = (upstream.headers as Headers & { getSetCookie?: () => string[] }).getSetCookie?.()
    ?? (upstream.headers.get("set-cookie") ? [upstream.headers.get("set-cookie") as string] : []);
  for (const cookieHeader of cookieHeaders) responseHeaders.append("Set-Cookie", cookieHeader);
  responseHeaders.set("Cache-Control", "private, no-store");

  return new NextResponse(await upstream.arrayBuffer(), {
    status: upstream.status,
    headers: responseHeaders,
  });
}

export const GET = forward;
export const POST = forward;
export const PUT = forward;
export const PATCH = forward;
export const DELETE = forward;
