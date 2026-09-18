import Link from "next/link";
import { ProductCard } from "@/components/product-card";
import { readBusinessProfile } from "@/lib/business-profile";
import { readCatalogue, readWebsiteProfile } from "@/lib/website-api";

export const dynamic = "force-dynamic";

export default async function Home() {
  const [profile, business] = await Promise.all([readWebsiteProfile(), readBusinessProfile()]);
  const commerce = profile?.capabilities.commerce ?? false;
  const digital = profile?.capabilities.digital ?? false;
  const featured = commerce ? await readCatalogue({ limit: 6 }).catch(() => null) : null;
  const heading =
    profile?.mode === "digital_only"
      ? "Digital solutions built around your business."
      : profile?.mode === "commerce_only"
        ? "Mobile technology, clearly available."
        : "Mobile products and digital solutions in one place.";

  return (
    <main>
      <section className="border-b border-slate-200 bg-gradient-to-b from-white to-slate-50">
        <div className="mx-auto max-w-7xl px-4 py-20 sm:px-6 sm:py-28">
          <p className="text-sm font-semibold uppercase tracking-[0.2em] text-slate-500">
            {business?.business_name ?? "mobiST Technologies"}
          </p>
          <h1 className="mt-4 max-w-4xl text-4xl font-bold tracking-tight text-slate-950 sm:text-6xl">
            {heading}
          </h1>
          <p className="mt-6 max-w-2xl text-lg leading-8 text-slate-600">
            {digital && commerce
              ? "Shop current mobile inventory or start a digital project through the same trusted platform."
              : digital
                ? "Plan, scope and deliver digital work with a clear project journey."
                : commerce
                  ? "Browse live catalogue availability backed by the same inventory authority used in-store."
                  : "Website publishing is being configured."}
          </p>
          <div className="mt-8 flex flex-wrap gap-3">
            {commerce && (
              <Link
                href="/products"
                className="rounded-full bg-slate-950 px-5 py-3 font-semibold text-white"
              >
                Browse products
              </Link>
            )}
            {digital && business?.business_email && (
              <a
                href={`mailto:${business.business_email}?subject=Digital%20project%20enquiry`}
                className="rounded-full border border-slate-300 bg-white px-5 py-3 font-semibold"
              >
                Discuss a digital project
              </a>
            )}
          </div>
        </div>
      </section>

      {commerce && featured && (
        <section className="mx-auto max-w-7xl px-4 py-14 sm:px-6">
          <div className="flex items-end justify-between gap-4">
            <div>
              <p className="text-sm font-semibold uppercase tracking-wide text-slate-500">
                Live catalogue
              </p>
              <h2 className="mt-1 text-3xl font-bold">Available products</h2>
            </div>
            <Link href="/products" className="text-sm font-semibold">
              View all →
            </Link>
          </div>
          <div className="mt-7 grid gap-5 sm:grid-cols-2 lg:grid-cols-3">
            {featured.items.map((product, index) => (
              <ProductCard key={product.id} product={product} priority={index < 2} />
            ))}
          </div>
        </section>
      )}
    </main>
  );
}
