import { NextRequest, NextResponse } from "next/server";

export const dynamic = "force-dynamic";
const backend = process.env.LARAVEL_API_ORIGIN ?? "http://127.0.0.1:18080";
if (backend !== "http://127.0.0.1:18080") throw new Error("Managed page media origin must be isolated Laravel target.");

export async function GET(_request: NextRequest, { params }: { params: Promise<{ slug: string; media: string }> }) {
  const { slug, media } = await params;
  if (!/^[a-z0-9]+(?:-[a-z0-9]+)*$/.test(slug) || !/^[1-9][0-9]{0,14}$/.test(media)) return new NextResponse(null, { status: 404 });
  const response = await fetch(`${backend}/api/v1/content/pages/${encodeURIComponent(slug)}/media/${media}`, {
    cache: "no-store", redirect: "error", signal: AbortSignal.timeout(12000),
  });
  if (response.status === 404) return new NextResponse(null, { status: 404 });
  if (!response.ok) return new NextResponse(null, { status: 502 });
  const type = response.headers.get("content-type");
  if (!type || !["image/jpeg", "image/png", "image/webp"].includes(type)) return new NextResponse(null, { status: 502 });
  const bytes = await response.arrayBuffer();
  if (!bytes.byteLength || bytes.byteLength > 10 * 1024 * 1024) return new NextResponse(null, { status: 502 });
  return new NextResponse(bytes, { headers: { "Content-Type": type, "Cache-Control": "no-store", "X-Content-Type-Options": "nosniff" } });
}
