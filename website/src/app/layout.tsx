import type { Metadata } from "next";
import "./globals.css";
export const metadata: Metadata = {
  title: "mobiST Tech | Website foundation",
  description: "Isolated application foundation. Business migration is pending.",
  robots: { index: false, follow: false },
};
export default function RootLayout({ children }: Readonly<{ children: React.ReactNode }>) {
  return <html lang="en"><body>{children}</body></html>;
}
