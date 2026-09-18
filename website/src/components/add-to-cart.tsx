"use client";

import { useState } from "react";
import { readCart, writeCart } from "@/lib/customer-cart";

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
  return <div className="mt-5">
    <button type="button" disabled={!product.available} onClick={add} className="rounded-xl bg-slate-950 px-5 py-3 text-sm font-semibold text-white disabled:cursor-not-allowed disabled:bg-slate-300">
      {product.available ? "Add to cart" : "Out of stock"}
    </button>
    {message && <span className="ml-3 text-sm text-emerald-700">{message}</span>}
  </div>;
}
