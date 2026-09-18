import { notFound } from "next/navigation";
import { CustomerCheckoutForm } from "@/components/customer-checkout-form";
import { readWebsiteProfile } from "@/lib/website-api";

export const dynamic = "force-dynamic";

export default async function CheckoutPage() {
  const profile = await readWebsiteProfile();
  if (!profile?.capabilities.commerce) notFound();
  return <main className="mx-auto max-w-4xl px-4 py-10 sm:px-6">
    <h1 className="text-3xl font-bold">Checkout</h1>
    <p className="mt-2 mb-6 text-slate-600">Price, discounts, loyalty and stock are verified by the server when you place the order.</p>
    <CustomerCheckoutForm />
  </main>;
}
