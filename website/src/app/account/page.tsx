import { CustomerAccountPanel } from "@/components/customer-account-panel";

export const dynamic = "force-dynamic";

export default function AccountPage() {
  return <main className="mx-auto max-w-5xl px-4 py-10 sm:px-6">
    <h1 className="text-3xl font-bold">Customer account</h1>
    <p className="mt-2 mb-6 text-slate-600">Sign in to view owned orders, saved items and reviews.</p>
    <CustomerAccountPanel />
  </main>;
}
