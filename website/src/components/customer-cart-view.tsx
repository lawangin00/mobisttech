"use client";

import Link from "next/link";
import { useEffect, useState } from "react";
import { customerRequest } from "@/lib/customer-api";
import { CartLine, clearCart, readCart, writeCart } from "@/lib/customer-cart";

type Quote = { subtotal: string; currency: string; items: Array<{ product_id: string; name: string; quantity: number; unit_price: string; line_total: string }> };

export function CustomerCartView() {
  const [lines, setLines] = useState<CartLine[]>([]);
  const [quote, setQuote] = useState<Quote | null>(null);
  const [message, setMessage] = useState("");
  useEffect(() => {
    const timer = window.setTimeout(() => setLines(readCart()), 0);
    return () => window.clearTimeout(timer);
  }, []);

  function persist(next: CartLine[]) { setLines(next); writeCart(next); setQuote(null); }
  async function validate() {
    setMessage("");
    try {
      const data = await customerRequest<Quote>("cart/quote", {
        method: "POST",
        body: JSON.stringify({ lines: lines.map(({ product_id, quantity }) => ({ product_id, quantity })) }),
      });
      setQuote(data);
    } catch (error) {
      setMessage(error instanceof Error ? error.message : "Unable to validate cart.");
    }
  }
  return <div>
    {lines.length === 0 ? <p className="text-slate-600">Your cart is empty.</p> : <div className="space-y-3">
      {lines.map((line) => <article key={line.product_id} className="flex flex-wrap items-center justify-between gap-3 rounded-2xl border bg-white p-4">
        <div><Link href={"/products/" + line.slug} className="font-semibold">{line.name}</Link><p className="text-sm text-slate-500">PKR {line.price}</p></div>
        <div className="flex items-center gap-2">
          <button className="rounded border px-3 py-1" onClick={() => persist(lines.map((x) => x.product_id === line.product_id ? { ...x, quantity: Math.max(1, x.quantity - 1) } : x))}>−</button>
          <span>{line.quantity}</span>
          <button className="rounded border px-3 py-1" onClick={() => persist(lines.map((x) => x.product_id === line.product_id ? { ...x, quantity: Math.min(99, x.quantity + 1) } : x))}>+</button>
          <button className="ml-2 text-sm text-red-700" onClick={() => persist(lines.filter((x) => x.product_id !== line.product_id))}>Remove</button>
        </div>
      </article>)}
      <div className="flex flex-wrap gap-3">
        <button className="rounded-xl bg-slate-950 px-4 py-2 text-white" onClick={validate}>Validate cart</button>
        <button className="rounded-xl border px-4 py-2" onClick={() => { clearCart(); setLines([]); setQuote(null); }}>Clear</button>
        <Link href="/checkout" className="rounded-xl bg-slate-950 px-4 py-2 text-white">Checkout</Link>
        <Link href="/account" className="rounded-xl border px-4 py-2">Account</Link>
      </div>
    </div>}
    {message && <p className="mt-4 text-sm text-red-700">{message}</p>}
    {quote && <div className="mt-5 rounded-2xl bg-slate-50 p-4"><strong>Current quote</strong><p className="mt-1">Subtotal: {quote.currency} {quote.subtotal}</p><p className="text-xs text-slate-500">Server prices and availability are authoritative and will be checked again at checkout.</p></div>}
  </div>;
}
