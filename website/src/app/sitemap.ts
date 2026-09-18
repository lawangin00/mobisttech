import type { MetadataRoute } from "next";
import { readBusinessProfile } from "@/lib/business-profile";
import { readCatalogue, readWebsiteProfile } from "@/lib/website-api";

export const dynamic = "force-dynamic";

export default async function sitemap(): Promise<MetadataRoute.Sitemap> {
  const [business, profile] = await Promise.all([readBusinessProfile(), readWebsiteProfile()]);
  if (!business || !profile?.mode) return [];
  const base = business.public_website.replace(/\/$/, "");
  const rows: MetadataRoute.Sitemap = [
    { url: base, changeFrequency: "daily", priority: 1 },
  ];

  if (profile.capabilities.commerce) {
    rows.push(
      { url: `${base}/products`, changeFrequency: "hourly", priority: 0.9 },
      { url: `${base}/categories`, changeFrequency: "daily", priority: 0.8 },
      { url: `${base}/compare`, changeFrequency: "weekly", priority: 0.4 },
    );
    let after: string | undefined;
    for (let page = 0; page < 10; page += 1) {
      const result = await readCatalogue({ limit: 24, after }).catch(() => null);
      if (!result) break;
      for (const product of result.items) {
        rows.push({
          url: `${base}/products/${product.slug}`,
          changeFrequency: "hourly",
          priority: 0.8,
        });
      }
      if (!result.page.has_more || !result.page.next_cursor) break;
      after = result.page.next_cursor;
    }
  }

  return rows;
}
