import { NextRequest, NextResponse } from "next/server";

export const dynamic = "force-dynamic";
const backend = process.env.LARAVEL_API_ORIGIN ?? "http://127.0.0.1:18080";
if (backend !== "http://127.0.0.1:18080") throw new Error("Website branding media origin must be isolated Laravel target.");
const roles = new Set(["main_logo", "wordmark", "header_logo", "footer_logo", "square_icon", "favicon", "social_image"]);

export async function GET(_request: NextRequest, { params }: { params: Promise<{ role: string; media: string }> }) {
  const { role, media } = await params;
  if (!roles.has(role) || !/^[1-9][0-9]{0,14}$/.test(media)) return new NextResponse(null, { status: 404 });
  const response = await fetch(`${backend}/api/v1/brand/${role}/media/${media}`, {
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
