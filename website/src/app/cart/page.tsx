import { notFound } from "next/navigation";
import { CustomerCartView } from "@/components/customer-cart-view";
import { readWebsiteProfile } from "@/lib/website-api";

export const dynamic = "force-dynamic";

export default async function CartPage() {
  const profile = await readWebsiteProfile();
  if (!profile?.capabilities.commerce) notFound();
  return <main className="mx-auto max-w-5xl px-4 py-10 sm:px-6">
    <h1 className="text-3xl font-bold">Cart</h1>
    <p className="mt-2 mb-6 text-slate-600">Your cart is kept on this device and revalidated against live server price and stock before checkout.</p>
    <CustomerCartView />
  </main>;
}
