import type { Metadata } from "next";
import { readBusinessProfile } from "@/lib/business-profile";
import "./globals.css";
export async function generateMetadata(): Promise<Metadata> {
  const profile = await readBusinessProfile();
  return {
    title: profile ? `${profile.business_name} | Website foundation` : "Website foundation",
    description: "Isolated application foundation. Business migration is pending.",
    metadataBase: profile ? new URL(profile.public_website) : undefined,
    robots: { index: false, follow: false },
  };
}
export default function RootLayout({ children }: Readonly<{ children: React.ReactNode }>) {
  return <html lang="en"><body>{children}</body></html>;
}
