"use client";

import { useState } from "react";
import { customerRequest } from "@/lib/customer-api";
import { readCart, writeCart } from "@/lib/customer-cart";
import { ensureGuestOwnerToken } from "@/lib/customer-guest";

export function AddToCart({ product }: { product: { id: string; slug: string; name: string; price: string; available: boolean } }) {
  const [message, setMessage] = useState("");
  function add() {
    const lines = readCart();
    const existing = lines.find((line) => line.product_id === product.id);
    const next = existing
      ? lines.map((line) => line.product_id === product.id ? { ...line, quantity: Math.min(99, line.quantity + 1) } : line)
      : [...lines, { product_id: product.id, slug: product.slug, name: product.name, price: product.price, quantity: 1 }];
    writeCart(next);
    setMessage("Added to cart.");
  }
  async function saveForLater() {
    setMessage("");
    try {
      await customerRequest(`guest-wishlist/${product.id}`, {
        method: "POST",
        body: JSON.stringify({ guest_token: ensureGuestOwnerToken() }),
      });
      setMessage("Saved for later.");
    } catch (error) {
      setMessage(error instanceof Error ? error.message : "Unable to save item.");
    }
  }
  async function subscribe() {
    setMessage("");
    try {
      await customerRequest(`product-subscriptions/${product.id}`, {
        method: "POST",
        body: JSON.stringify({ events: ["back_in_stock", "price_drop"], consent: true }),
      });
      setMessage("Product alerts enabled.");
    } catch (error) {
      setMessage(error instanceof Error ? error.message : "Sign in with a verified email to enable alerts.");
    }
  }
  return <div className="mt-5">
    <div className="flex flex-wrap gap-2">
      <button type="button" disabled={!product.available} onClick={add} className="rounded-xl bg-slate-950 px-5 py-3 text-sm font-semibold text-white disabled:cursor-not-allowed disabled:bg-slate-300">
        {product.available ? "Add to cart" : "Out of stock"}
      </button>
      <button type="button" onClick={saveForLater} className="rounded-xl border px-5 py-3 text-sm font-semibold">Save for later</button>
      <button type="button" onClick={subscribe} className="rounded-xl border px-5 py-3 text-sm font-semibold">Product alerts</button>
    </div>
    {message && <p className="mt-3 text-sm text-slate-600">{message}</p>}
  </div>;
}
