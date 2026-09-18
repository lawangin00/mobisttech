import { CustomerOrderDetail } from "@/components/customer-order-detail";

export const dynamic = "force-dynamic";

export default async function CustomerOrderPage({ params }: { params: Promise<{ order: string }> }) {
  const { order } = await params;
  return <main className="mx-auto max-w-4xl px-4 py-10 sm:px-6">
    <h1 className="text-3xl font-bold">Order status</h1>
    <p className="mt-2 mb-6 text-slate-600">Payment and fulfillment status are read from your owned order history.</p>
    <CustomerOrderDetail orderId={order} />
  </main>;
}
