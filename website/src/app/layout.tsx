import type { CSSProperties } from "react";
import type { Metadata } from "next";
import { SiteFooter, SiteHeader } from "@/components/site-shell";
import { readStorefrontContext } from "@/lib/storefront";
import "./globals.css";

const themeProperties: Record<string, string> = {
  primary: "--mobist-color-brand", primary_hover: "--mobist-color-brand-hover",
  secondary: "--mobist-color-brand-deep", accent: "--mobist-color-brand-bright",
  background: "--mobist-color-surface-soft", surface: "--mobist-color-surface",
  text: "--mobist-color-ink", muted_text: "--mobist-color-muted", border: "--mobist-color-line",
};
function publishedThemeStyle(theme: Record<string, string> | undefined): CSSProperties {
  const style: Record<string, string> = {};
  for (const [key, variable] of Object.entries(themeProperties)) {
    const value = theme?.[key];
    if (typeof value === "string" && /^#[0-9a-fA-F]{6}$/.test(value)) style[variable] = value.toLowerCase();
  }
  return style as CSSProperties;
}


export async function generateMetadata(): Promise<Metadata> {
  const { business, website } = await readStorefrontContext();
  const brand = business?.business_name ?? "mobiST Technologies";
  const seo = website?.content?.seo;
  const title = seo?.title || brand;
  const description = seo?.description || "Mobile products and digital solutions from mobiST Technologies.";

  return {
    title: { default: title, template: `%s | ${brand}` },
    description,
    metadataBase: business ? new URL(business.public_website) : undefined,
    robots: website?.mode ? { index: true, follow: true } : { index: false, follow: false },
    openGraph: { type: "website", siteName: brand, title: seo?.social_title || title, description: seo?.social_description || description,
      ...(website?.content?.branding?.social_image ? { images: [`/branding/social_image/${website.content.branding.social_image}`] } : {}) },
    icons: {
      icon: [{ url: website?.content?.branding?.favicon ? `/branding/favicon/${website.content.branding.favicon}` : "/favicon.ico" },
        { url: website?.content?.branding?.square_icon ? `/branding/square_icon/${website.content.branding.square_icon}` : "/icon-192.png", type: "image/png", sizes: "192x192" }],
      apple: [{ url: website?.content?.branding?.square_icon ? `/branding/square_icon/${website.content.branding.square_icon}` : "/apple-touch-icon.png", sizes: "180x180", type: "image/png" }],
    },
  };
}

export default async function RootLayout({
  children,
}: Readonly<{ children: React.ReactNode }>) {
  const { business, website, content } = await readStorefrontContext();

  return (
    <html lang="en" style={publishedThemeStyle(content?.theme)}>
      <body>
        <SiteHeader business={business} profile={website} content={content} />
        {children}
        <SiteFooter business={business} profile={website} content={content} />
      </body>
    </html>
  );
}
