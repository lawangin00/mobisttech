"use client";

import Link from "next/link";
import { useCallback, useEffect, useState } from "react";
import { customerRequest } from "@/lib/customer-api";

type Payment = { public_id: string; gateway: string; status: string; amount: string; currency: string; initiated_at?: string; paid_at?: string };
type Item = { title: string; quantity: number; unit_price: string; line_total: string };
type Order = { id: string; number: string; type: string; status: string; fulfillment_status: string; payment_status: string; subtotal: string; total: string; currency: string; items: Item[]; payments: Payment[]; signed_access_url: string };
type Channel = { code: "cod" | "jazzcash" | "easypaisa" | "card"; label: string; available: boolean };
type Retry = { payment_id: string };

export function CustomerOrderDetail({ orderId }: { orderId: string }) {
  const [order, setOrder] = useState<Order | null>(null);
  const [channels, setChannels] = useState<Channel[]>([]);
  const [message, setMessage] = useState("");
  const [busy, setBusy] = useState(false);

  const load = useCallback(async () => {
    const [owned, channelData] = await Promise.all([
      customerRequest<Order>("orders/" + orderId),
      customerRequest<{ items: Channel[] }>("checkout/channels").catch(() => ({ items: [] })),
    ]);
    setOrder(owned);
    setChannels(channelData.items);
  }, [orderId]);

  useEffect(() => {
    const timer = window.setTimeout(() => { void load().catch((error) => setMessage(error instanceof Error ? error.message : "Unable to load order.")); }, 0);
    return () => window.clearTimeout(timer);
  }, [load]);

  async function initiate(paymentId: string) {
    const result = await customerRequest<{ redirect_url?: string }>(`payments/${paymentId}/initiate`, { method: "POST", body: JSON.stringify({}) });
    if (!result.redirect_url) return;
    const target = new URL(result.redirect_url);
    if (target.protocol !== "https:") throw new Error("Payment provider returned an unsafe redirect.");
    window.location.assign(target.toString());
  }

  async function cancel() {
    if (!order) return;
    setBusy(true); setMessage("");
    try {
      await customerRequest(`orders/${order.id}/cancel`, { method: "POST", idempotencyKey: "website-cancel-" + crypto.randomUUID(), body: JSON.stringify({}) });
      await load();
    } catch (error) {
      setMessage(error instanceof Error ? error.message : "Unable to cancel order.");
    } finally { setBusy(false); }
  }

  async function retry(gateway: "jazzcash" | "easypaisa" | "card") {
    if (!order) return;
    setBusy(true); setMessage("");
    try {
      const retried = await customerRequest<Retry>(`orders/${order.id}/payments/retry`, {
        method: "POST", idempotencyKey: "website-retry-" + crypto.randomUUID(), body: JSON.stringify({ gateway }),
      });
      await initiate(retried.payment_id);
      await load();
    } catch (error) {
      setMessage(error instanceof Error ? error.message : "Unable to retry payment.");
    } finally { setBusy(false); }
  }

  if (!order) return <div className="min-h-[620px] rounded-2xl bg-slate-50 p-5 text-slate-600">{message || "Loading order…"}</div>;
  const latest = order.payments.at(-1);
  const retryChannels = channels.filter((item) => item.available && item.code !== "cod");
  const canCancel = !["cancelled", "completed"].includes(order.status) && !["paid", "paid_reconciliation"].includes(order.payment_status);

  return <div className="min-h-[620px] space-y-6">
    <section className="rounded-2xl border bg-white p-5">
      <div className="flex flex-wrap justify-between gap-3"><div><h2 className="text-xl font-bold">{order.number}</h2><p className="text-sm text-slate-600">{order.status} · {order.fulfillment_status} · {order.payment_status}</p></div><strong>{order.currency} {order.total}</strong></div>
    </section>
    <section className="rounded-2xl border bg-white p-5"><h3 className="font-bold">Items</h3><div className="mt-3 space-y-2">{order.items.map((item, index) => <div key={index} className="flex justify-between gap-4 text-sm"><span>{item.title} × {item.quantity}</span><span>{order.currency} {item.line_total}</span></div>)}</div></section>
    <section className="rounded-2xl border bg-white p-5"><h3 className="font-bold">Payments</h3><div className="mt-3 space-y-2">{order.payments.map((payment) => <div key={payment.public_id} className="text-sm"><strong>{payment.gateway}</strong> · {payment.status} · {payment.currency} {payment.amount}</div>)}</div></section>
    <div className="flex flex-wrap gap-3">
      {latest && latest.gateway !== "cod" && latest.status === "pending" && <button disabled={busy} onClick={() => void initiate(latest.public_id).catch((error) => setMessage(error instanceof Error ? error.message : "Unable to continue payment."))} className="rounded-xl bg-slate-950 px-4 py-2 text-white">Continue payment</button>}
      {order.payment_status === "failed" && retryChannels.map((channel) => <button key={channel.code} disabled={busy} onClick={() => void retry(channel.code as "jazzcash" | "easypaisa" | "card")} className="rounded-xl border px-4 py-2">Retry with {channel.label}</button>)}
      {canCancel && <button disabled={busy} onClick={() => void cancel()} className="rounded-xl border border-red-300 px-4 py-2 text-red-700">Cancel order</button>}
      <a href={order.signed_access_url} target="_blank" rel="noreferrer" className="rounded-xl border px-4 py-2">Open 30-minute read-only link</a>
      <Link href="/account" className="rounded-xl border px-4 py-2">Back to account</Link>
    </div>
    {message && <p className="text-sm text-red-700">{message}</p>}
  </div>;
}
