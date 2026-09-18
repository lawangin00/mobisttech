"use client";

import { FormEvent, useCallback, useEffect, useState } from "react";
import { customerRequest } from "@/lib/customer-api";

type Preferences = { email_enabled: boolean; max_per_hour: number; version: number };
type Subscription = { product_id: string; product_name: string; event_type: string; active: boolean };
type Loyalty = { enabled: boolean; balance_points: number; min_redeem_points: number | null; max_redeem_points: number | null; redemption_value: string | null; expiry_days: number | null };
type Eligibility = { order_id: string; order_number: string; product_id: string; product_name: string; review_id: number | null; review_status: string | null };

export function CustomerEngagementPanel() {
  const [preferences, setPreferences] = useState<Preferences | null>(null);
  const [subscriptions, setSubscriptions] = useState<Subscription[]>([]);
  const [loyalty, setLoyalty] = useState<Loyalty | null>(null);
  const [eligible, setEligible] = useState<Eligibility[]>([]);
  const [message, setMessage] = useState("");

  const load = useCallback(async () => {
    await Promise.all([
      customerRequest<Preferences>("notification-preferences").then(setPreferences),
      customerRequest<{ items: Subscription[] }>("product-subscriptions")
        .then((value) => setSubscriptions(value.items)).catch(() => setSubscriptions([])),
      customerRequest<Loyalty>("loyalty").then(setLoyalty),
      customerRequest<{ items: Eligibility[] }>("reviews/eligible").then((value) => setEligible(value.items)),
    ]);
  }, []);

  useEffect(() => {
    const timer = window.setTimeout(() => { void load().catch(() => undefined); }, 0);
    return () => window.clearTimeout(timer);
  }, [load]);

  async function toggleEmail() {
    if (!preferences) return;
    const next = await customerRequest<Preferences>("notification-preferences", {
      method: "PUT",
      body: JSON.stringify({ version: preferences.version, email_enabled: !preferences.email_enabled, max_per_hour: preferences.max_per_hour }),
    });
    setPreferences(next);
  }

  async function unsubscribe(item: Subscription) {
    await customerRequest(`product-subscriptions/${item.product_id}`, {
      method: "DELETE",
      body: JSON.stringify({ events: [item.event_type] }),
    });
    await load();
  }

  async function submitReview(event: FormEvent<HTMLFormElement>) {
    event.preventDefault();
    const formElement = event.currentTarget;
    const form = new FormData(formElement);
    const selected = eligible.find((item) => `${item.order_id}|${item.product_id}` === form.get("selection"));
    if (!selected) return;
    setMessage("");
    try {
      await customerRequest("reviews", {
        method: "POST",
        body: JSON.stringify({
          order_id: selected.order_id,
          product_id: selected.product_id,
          rating: Number(form.get("rating")),
          title: form.get("title") || null,
          body: form.get("body"),
        }),
      });
      setMessage("Review submitted for moderation.");
      formElement.reset();
      void load().catch(() => undefined);
    } catch (error) {
      setMessage(error instanceof Error ? error.message : "Unable to submit review.");
    }
  }

  return <div className="space-y-6">
    <section className="grid gap-3 sm:grid-cols-2">
      <div className="rounded-2xl border bg-white p-4"><strong>Loyalty</strong><p className="mt-1 text-2xl font-bold">{loyalty?.balance_points ?? 0} points</p><p className="text-xs text-slate-500">{loyalty?.enabled ? "Available for eligible orders." : "New redemption is currently unavailable."}</p></div>
      <div className="rounded-2xl border bg-white p-4"><strong>Email product alerts</strong><p className="mt-1 text-sm text-slate-600">{preferences?.email_enabled ? "Enabled" : "Disabled"}</p><button onClick={toggleEmail} className="mt-2 rounded-lg border px-3 py-1 text-sm">Toggle email alerts</button></div>
    </section>
    {subscriptions.length > 0 && <section><h2 className="text-xl font-bold">Product alerts</h2><div className="mt-3 space-y-2">{subscriptions.map((item) => <div key={item.product_id + item.event_type} className="flex flex-wrap items-center justify-between gap-2 rounded-xl border bg-white p-3"><span>{item.product_name} · {item.event_type.replaceAll("_", " ")}</span><button onClick={() => unsubscribe(item)} className="text-sm text-red-700">Unsubscribe</button></div>)}</div></section>}
    {eligible.length > 0 && <section><h2 className="text-xl font-bold">Review a purchase</h2><form onSubmit={submitReview} className="mt-3 space-y-3 rounded-2xl border bg-white p-4">
      <select name="selection" required className="w-full rounded-xl border p-3">{eligible.map((item) => <option key={item.order_id + item.product_id} value={item.order_id + "|" + item.product_id}>{item.order_number} · {item.product_name}{item.review_status ? " · " + item.review_status : ""}</option>)}</select>
      <select name="rating" required className="w-full rounded-xl border p-3">{[5,4,3,2,1].map((rating) => <option key={rating} value={rating}>{rating}/5</option>)}</select>
      <input name="title" maxLength={160} placeholder="Review title (optional)" className="w-full rounded-xl border p-3" />
      <textarea name="body" required minLength={2} maxLength={5000} placeholder="Your review" className="min-h-28 w-full rounded-xl border p-3" />
      <button className="rounded-xl bg-slate-950 px-4 py-2 text-white">Submit review</button>
      {message && <p className="text-sm text-slate-600">{message}</p>}
    </form></section>}
  </div>;
}
