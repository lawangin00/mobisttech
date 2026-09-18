import type { Metadata } from "next";
import { SiteFooter, SiteHeader } from "@/components/site-shell";
import { readStorefrontContext } from "@/lib/storefront";
import "./globals.css";

export async function generateMetadata(): Promise<Metadata> {
  const { business, website } = await readStorefrontContext();
  const brand = business?.business_name ?? "mobiST Technologies";

  return {
    title: { default: brand, template: `%s | ${brand}` },
    description: "Mobile products and digital solutions from mobiST Technologies.",
    metadataBase: business ? new URL(business.public_website) : undefined,
    robots: website?.mode ? { index: true, follow: true } : { index: false, follow: false },
    openGraph: { type: "website", siteName: brand },
  };
}

export default async function RootLayout({
  children,
}: Readonly<{ children: React.ReactNode }>) {
  const { business, website, content } = await readStorefrontContext();

  return (
    <html lang="en">
      <body>
        <SiteHeader business={business} profile={website} content={content} />
        {children}
        <SiteFooter business={business} profile={website} content={content} />
      </body>
    </html>
  );
}
