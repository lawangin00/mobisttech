"use client";

import Link from "next/link";
import { FormEvent, useEffect, useRef, useState } from "react";
import { useRouter } from "next/navigation";
import { CustomerAccount, customerRequest } from "@/lib/customer-api";
import { CartLine, clearCart, readCart } from "@/lib/customer-cart";

type Channel = { code: "cod" | "jazzcash" | "easypaisa" | "card"; label: string; instructions?: string; cod_min_amount?: string | null; cod_max_amount?: string | null; available: boolean; kind: string };
type CreatedOrder = { order_id: string; order_number: string; payment_id: string; payment_status: string; amount: string; currency: string };
type PaymentInit = { reference: string; redirect_url?: string; expires_at?: string };

export function CustomerCheckoutForm() {
  const router = useRouter();
  const [account, setAccount] = useState<CustomerAccount | null | undefined>(undefined);
  const [lines, setLines] = useState<CartLine[]>([]);
  const [channels, setChannels] = useState<Channel[]>([]);
  const [gateway, setGateway] = useState<Channel["code"]>("cod");
  const [message, setMessage] = useState("");
  const [createdOrderId, setCreatedOrderId] = useState<string | null>(null);
  const [submitting, setSubmitting] = useState(false);
  const submittingRef = useRef(false);
  const keyRef = useRef<string | null>(null);

  useEffect(() => {
    const timer = window.setTimeout(() => {
      setLines(readCart());
      Promise.all([
        customerRequest<CustomerAccount>("account"),
        customerRequest<{ items: Channel[] }>("checkout/channels"),
      ]).then(([current, available]) => {
        setAccount(current);
        setChannels(available.items);
        const first = available.items.find((item) => item.available);
        if (first) setGateway(first.code);
      }).catch(() => setAccount(null));
    }, 0);
    return () => window.clearTimeout(timer);
  }, []);

  async function submit(event: FormEvent<HTMLFormElement>) {
    event.preventDefault();
    if (!account || lines.length === 0 || submittingRef.current || createdOrderId) return;
    submittingRef.current = true;
    const formElement = event.currentTarget;
    const form = new FormData(formElement);
    setSubmitting(true);
    setMessage("");
    try {
      if (!keyRef.current) keyRef.current = "website-checkout-" + crypto.randomUUID();
      const coupons = String(form.get("coupon") ?? "").split(",").map((value) => value.trim()).filter(Boolean);
      const loyalty = Number(form.get("loyalty_points") ?? 0);
      const created = await customerRequest<CreatedOrder>("orders", {
        method: "POST",
        idempotencyKey: keyRef.current,
        body: JSON.stringify({
          customer_name: form.get("customer_name"),
          customer_mobile: form.get("customer_mobile"),
          customer_email: form.get("customer_email"),
          city: form.get("city"),
          delivery_address: form.get("delivery_address"),
          notes: form.get("notes"),
          gateway,
          coupon_codes: coupons,
          loyalty_points: Number.isFinite(loyalty) ? loyalty : 0,
          lines: lines.map(({ product_id, quantity }) => ({ product_id, quantity })),
        }),
      });
      setCreatedOrderId(created.order_id);
      clearCart();
      keyRef.current = null;
      if (gateway !== "cod") {
        const initiated = await customerRequest<PaymentInit>(`payments/${created.payment_id}/initiate`, { method: "POST", body: JSON.stringify({}) });
        if (initiated.redirect_url) {
          const target = new URL(initiated.redirect_url);
          if (target.protocol !== "https:") throw new Error("Payment provider returned an unsafe redirect.");
          window.location.assign(target.toString());
          return;
        }
      }
      router.push("/account/orders/" + created.order_id);
    } catch (error) {
      setMessage(error instanceof Error ? error.message : "Unable to place order.");
    } finally {
      submittingRef.current = false;
      setSubmitting(false);
    }
  }

  if (account === undefined) return <div className="min-h-[1050px] rounded-2xl bg-slate-50 p-5 text-slate-600">Loading checkout…</div>;
  if (account === null) return <div className="rounded-2xl border bg-white p-5"><p>Sign in before checkout.</p><Link href="/account" className="mt-3 inline-block rounded-xl bg-slate-950 px-4 py-2 text-white">Customer account</Link></div>;
  if (createdOrderId) return <div className="rounded-2xl border bg-white p-5">
    <p>Your order has been created. You can continue any pending payment from your order details.</p>
    {message && <p role="alert" className="mt-3 text-sm text-red-700">{message}</p>}
    <Link href={"/account/orders/" + createdOrderId} prefetch={false} className="mt-3 inline-block rounded-xl bg-slate-950 px-4 py-2 text-white">View order and continue payment</Link>
  </div>;
  if (lines.length === 0) return <div className="rounded-2xl border bg-white p-5"><p>Your cart is empty.</p><Link href="/products" className="mt-3 inline-block underline">Browse products</Link></div>;

  return <form onSubmit={submit} onChange={() => { keyRef.current = null; }} className="min-h-[1050px] space-y-6">
    <section className="grid gap-3 rounded-2xl border bg-white p-5 sm:grid-cols-2">
      <input name="customer_name" required defaultValue={account.name} aria-label="Customer name" className="rounded-xl border p-3" />
      <input name="customer_mobile" required defaultValue={account.mobile} aria-label="Customer mobile" className="rounded-xl border p-3" />
      <input name="customer_email" type="email" defaultValue={account.email} aria-label="Customer email" className="rounded-xl border p-3" />
      <input name="city" required placeholder="City" aria-label="City" className="rounded-xl border p-3" />
      <textarea name="delivery_address" required placeholder="Delivery address" aria-label="Delivery address" className="rounded-xl border p-3 sm:col-span-2" />
      <textarea name="notes" placeholder="Order notes (optional)" aria-label="Order notes" className="rounded-xl border p-3 sm:col-span-2" />
    </section>

    <section className="rounded-2xl border bg-white p-5">
      <h2 className="text-lg font-bold">Payment</h2>
      <div className="mt-3 grid gap-3 sm:grid-cols-2">
        {channels.map((channel) => <label key={channel.code} className={"rounded-xl border p-4 " + (!channel.available ? "opacity-50" : "")}>
          <span className="flex items-center gap-2"><input type="radio" name="gateway" value={channel.code} checked={gateway === channel.code} disabled={!channel.available} onChange={() => setGateway(channel.code)} /> <strong>{channel.label}</strong></span>
          <span className="mt-1 block text-xs text-slate-500">{channel.available ? (channel.instructions || (channel.code === "cod" ? "Pay when the order is collected/delivered." : "Continue on the configured hosted provider.")) : "Not configured."}</span>
          {channel.code === "cod" && channel.available && (channel.cod_min_amount != null || channel.cod_max_amount != null) && <span className="mt-1 block text-xs text-slate-500">COD order amount: {channel.cod_min_amount ?? "No minimum"} – {channel.cod_max_amount ?? "No maximum"} PKR. Final eligibility is checked when placing the order.</span>}
        </label>)}
      </div>
      <p className="mt-3 text-xs text-slate-500">Exactly four Website payment channels are supported. Bank transfer and split tender are not offered here.</p>
    </section>

    <section className="grid gap-3 rounded-2xl border bg-white p-5 sm:grid-cols-2">
      <input name="coupon" placeholder="Coupon code(s), comma separated" aria-label="Coupon codes" className="rounded-xl border p-3" />
      <input name="loyalty_points" type="number" min="0" step="1" defaultValue="0" aria-label="Loyalty points" className="rounded-xl border p-3" />
      <p className="text-xs text-slate-500 sm:col-span-2">Use coupons or loyalty points, not both. The server recalculates price, discount, rewards and stock at submission.</p>
    </section>

    <section className="rounded-2xl bg-slate-50 p-5">
      <strong>{lines.reduce((sum, line) => sum + line.quantity, 0)} item(s)</strong>
      <p className="text-sm text-slate-600">Final payable amount is server-authoritative.</p>
    </section>
    <button disabled={submitting || !channels.some((item) => item.available && item.code === gateway)} className="rounded-xl bg-slate-950 px-5 py-3 font-semibold text-white disabled:opacity-50">{submitting ? "Placing order…" : "Place order"}</button>
    {message && <p className="text-sm text-red-700">{message}</p>}
  </form>;
}
