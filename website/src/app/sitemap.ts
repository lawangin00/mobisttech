import type { MetadataRoute } from "next";
import { readBusinessProfile } from "@/lib/business-profile";
import { readCatalogue, readCategories, readContentIndex, readServices, readWebsiteProfile } from "@/lib/website-api";

export const dynamic = "force-dynamic";

export default async function sitemap(): Promise<MetadataRoute.Sitemap> {
  const [business, profile, content] = await Promise.all([
    readBusinessProfile(),
    readWebsiteProfile(),
    readContentIndex().catch(() => null),
  ]);
  if (!business || !profile?.mode) return [];
  const base = business.public_website.replace(/\/$/, "");
  const rows: MetadataRoute.Sitemap = [{ url: base, changeFrequency: "daily", priority: 1 }];
  const seen = new Set(rows.map((row) => row.url));
  const add = (path: string, changeFrequency: MetadataRoute.Sitemap[number]["changeFrequency"] = "weekly", priority = 0.5) => {
    const url = base + (path.startsWith("/") ? path : "/" + path);
    if (!seen.has(url)) { seen.add(url); rows.push({ url, changeFrequency, priority }); }
  };

  for (const page of content?.pages ?? []) add("/" + page.slug, "weekly", 0.6);
  for (const policy of content?.policies ?? []) add("/" + policy.slug, "monthly", 0.5);
  for (const software of content?.software ?? []) {
    for (const route of Object.values(software.routes)) add(route, route.endsWith("/releases") ? "weekly" : "monthly", 0.7);
  }

  if (profile.capabilities.digital) {
    add("/services", "weekly", 0.8);
    add("/enquiry", "monthly", 0.4);
    const services = await readServices().catch(() => []);
    for (const service of services) add("/services/" + service.slug, "weekly", 0.7);
  }

  if (profile.capabilities.commerce) {
    add("/products", "hourly", 0.9);
    add("/categories", "daily", 0.8);
    for (const category of await readCategories()) add("/categories/" + encodeURIComponent(category.code), "daily", 0.7);
    let after: string | undefined;
    for (let page = 0; page < 10; page += 1) {
      const result = await readCatalogue({ limit: 24, after }).catch(() => null);
      if (!result) break;
      for (const product of result.items) add("/products/" + product.slug, "hourly", 0.8);
      if (!result.page.has_more || !result.page.next_cursor) break;
      after = result.page.next_cursor;
    }
  }

  return rows;
}
