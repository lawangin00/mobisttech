import type { Metadata } from "next";
import Link from "next/link";
import { notFound } from "next/navigation";
import { readCategories, readWebsiteProfile } from "@/lib/website-api";

export const dynamic = "force-dynamic";
export const metadata: Metadata = {
  title: "Categories",
  description: "Browse mobiST product categories.",
  alternates: { canonical: "/categories" },
};

export default async function Categories() {
  const profile = await readWebsiteProfile();
  if (!profile?.capabilities.commerce) notFound();
  const categories = await readCategories();

  return (
    <main className="mx-auto max-w-7xl px-4 py-10 sm:px-6">
      <h1 className="text-4xl font-bold">Categories</h1>
      <p className="mt-3 text-slate-600">
        Published catalogue groups, including currently out-of-stock listings.
      </p>
      <div className="mt-8 grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
        {categories.map((category) => (
          <Link
            key={category.code}
            href={`/categories/${encodeURIComponent(category.code)}`} prefetch={false}
            className="rounded-2xl border border-slate-200 bg-white p-6 hover:border-slate-400"
          >
            <strong className="text-xl">{category.label}</strong>
            <p className="mt-1 text-sm text-slate-500">
              {category.products} published product{category.products === 1 ? "" : "s"}
            </p>
          </Link>
        ))}
      </div>
    </main>
  );
}
