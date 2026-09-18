import type { MetadataRoute } from "next";
import { readBusinessProfile } from "@/lib/business-profile";
import { readWebsiteProfile } from "@/lib/website-api";

export const dynamic = "force-dynamic";

export default async function robots(): Promise<MetadataRoute.Robots> {
  const [business, profile] = await Promise.all([readBusinessProfile(), readWebsiteProfile()]);
  const indexable = Boolean(profile?.mode);
  return {
    rules: {
      userAgent: "*",
      allow: indexable ? "/" : undefined,
      disallow: indexable ? ["/api/", "/cart", "/checkout"] : ["/"],
    },
    sitemap: business && indexable ? `${business.public_website.replace(/\/$/, "")}/sitemap.xml` : undefined,
  };
}
