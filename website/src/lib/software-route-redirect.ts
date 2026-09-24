import "server-only";
import { permanentRedirect } from "next/navigation";
import { readSoftwareRedirect, WebsiteApiError } from "@/lib/website-api";

export async function redirectRetiredSoftwareRoute(slug: string, suffix = ""): Promise<void> {
  const current = `/software/${slug}${suffix}`;
  try {
    const resolved = await readSoftwareRedirect(current);
    if (typeof resolved.to_path === "string" && /^\/software\/[a-z0-9-]+(?:\/(?:privacy|terms|faq|releases(?:\/[a-z0-9.\-]+)?))?$/.test(resolved.to_path)
      && resolved.to_path !== current) permanentRedirect(resolved.to_path);
  } catch (error) {
    if (error instanceof WebsiteApiError && error.status === 404) return;
    throw error;
  }
}
