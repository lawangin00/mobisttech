"use client";

import { FormEvent, useCallback, useEffect, useState } from "react";
import { clearCustomerCsrf, CustomerAccount, customerRequest } from "@/lib/customer-api";
import { clearGuestOwnerToken, readGuestOwnerToken } from "@/lib/customer-guest";
import { CustomerEngagementPanel } from "@/components/customer-engagement-panel";

type Order = { id: string; number: string; status: string; fulfillment_status: string; payment_status: string; total: string; currency: string; created_at: string };
type OrderPage = { items: Order[]; page: { has_more: boolean; next_cursor: string | null } };

export function CustomerAccountPanel() {
  const [account, setAccount] = useState<CustomerAccount | null>(null);
  const [orders, setOrders] = useState<Order[]>([]);
  const [wishlistCount, setWishlistCount] = useState(0);
  const [reviewCount, setReviewCount] = useState(0);
  const [register, setRegister] = useState(false);
  const [recovery, setRecovery] = useState(false);
  const [message, setMessage] = useState("");

  const refresh = useCallback(async () => {
    const current = await customerRequest<CustomerAccount>("account");
    setAccount(current);
    const guestToken = readGuestOwnerToken();
    if (guestToken) {
      await customerRequest("wishlist/claim", { method: "POST", body: JSON.stringify({ guest_token: guestToken }) })
        .then(() => clearGuestOwnerToken()).catch(() => undefined);
    }
    const [orderData, wishlist, reviews] = await Promise.all([
      customerRequest<OrderPage>("orders"),
      customerRequest<{ items: unknown[] }>("wishlist").catch(() => ({ items: [] })),
      customerRequest<{ items: unknown[] }>("reviews").catch(() => ({ items: [] })),
    ]);
    setOrders(orderData.items);
    setWishlistCount(wishlist.items.length);
    setReviewCount(reviews.items.length);
  }, []);

  useEffect(() => {
    const timer = window.setTimeout(() => { void refresh().catch(() => setAccount(null)); }, 0);
    return () => window.clearTimeout(timer);
  }, [refresh]);

  async function submit(event: FormEvent<HTMLFormElement>) {
    event.preventDefault();
    setMessage("");
    const form = new FormData(event.currentTarget);
    const body = register
      ? { name: form.get("name"), email: form.get("email"), mobile: form.get("mobile"), password: form.get("password"), password_confirmation: form.get("password_confirmation") }
      : { email: form.get("email"), password: form.get("password"), remember: form.get("remember") === "on" };
    try {
      await customerRequest<CustomerAccount>(register ? "auth/register" : "auth/login", { method: "POST", body: JSON.stringify(body) });
      await refresh();
    } catch (error) {
      setMessage(error instanceof Error ? error.message : "Authentication failed.");
    }
  }

  async function requestRecovery(event: FormEvent<HTMLFormElement>) {
    event.preventDefault();
    const form = new FormData(event.currentTarget);
    setMessage("");
    try {
      const result = await customerRequest<{ message: string }>("auth/forgot-password", {
        method: "POST", body: JSON.stringify({ email: form.get("email") }),
      });
      setMessage(result.message);
    } catch (error) {
      setMessage(error instanceof Error ? error.message : "Recovery delivery is unavailable.");
    }
  }

  async function changePassword(event: FormEvent<HTMLFormElement>) {
    event.preventDefault();
    const form = new FormData(event.currentTarget);
    setMessage("");
    try {
      const result = await customerRequest<{ message: string }>("auth/password", {
        method: "PATCH",
        body: JSON.stringify({
          current_password: form.get("current_password"),
          password: form.get("password"),
          password_confirmation: form.get("password_confirmation"),
        }),
      });
      clearCustomerCsrf();
      setAccount(null); setOrders([]); setWishlistCount(0); setReviewCount(0);
      setMessage(result.message);
    } catch (error) {
      setMessage(error instanceof Error ? error.message : "Unable to change password.");
    }
  }

  async function logout() {
    await customerRequest<{ message: string }>("auth/logout", { method: "POST", body: JSON.stringify({}) });
    clearCustomerCsrf();
    setAccount(null); setOrders([]); setWishlistCount(0); setReviewCount(0);
  }

  if (!account) return <div className="max-w-lg">
    {recovery ? <>
      <button className="mb-4 text-sm font-medium text-slate-700" onClick={() => { setRecovery(false); setMessage(""); }}>← Back to sign in</button>
      <form onSubmit={requestRecovery} className="space-y-3 rounded-2xl border bg-white p-5">
        <h2 className="text-xl font-bold">Reset password</h2>
        <p className="text-sm text-slate-600">Enter your customer-account email. A secure reset link is sent only when recovery delivery is configured.</p>
        <input name="email" type="email" required placeholder="Email" className="w-full rounded-xl border p-3" />
        <button className="rounded-xl bg-slate-950 px-5 py-3 font-semibold text-white">Send reset link</button>
        {message && <p className="text-sm text-slate-600">{message}</p>}
      </form>
    </> : <>
      <div className="mb-5 flex gap-2">
        <button className={"rounded-xl px-4 py-2 " + (!register ? "bg-slate-950 text-white" : "border")} onClick={() => { setRegister(false); setMessage(""); }}>Sign in</button>
        <button className={"rounded-xl px-4 py-2 " + (register ? "bg-slate-950 text-white" : "border")} onClick={() => { setRegister(true); setMessage(""); }}>Create account</button>
      </div>
      <form onSubmit={submit} className="space-y-3 rounded-2xl border bg-white p-5">
        {register && <><input name="name" required placeholder="Name" className="w-full rounded-xl border p-3" /><input name="mobile" required pattern="03[0-9]{9}" placeholder="03XXXXXXXXX" className="w-full rounded-xl border p-3" /></>}
        <input name="email" type="email" required placeholder="Email" className="w-full rounded-xl border p-3" />
        <input name="password" type="password" required minLength={8} placeholder="Password" className="w-full rounded-xl border p-3" />
        {register && <input name="password_confirmation" type="password" required minLength={8} placeholder="Confirm password" className="w-full rounded-xl border p-3" />}
        {!register && <label className="flex items-center gap-2 text-sm"><input type="checkbox" name="remember" /> Remember me on this device</label>}
        <button className="rounded-xl bg-slate-950 px-5 py-3 font-semibold text-white">{register ? "Create account" : "Sign in"}</button>
        {!register && <button type="button" className="block text-sm font-medium text-slate-700" onClick={() => { setRecovery(true); setMessage(""); }}>Forgot password?</button>}
        {message && <p className="text-sm text-slate-600">{message}</p>}
      </form>
    </>}
  </div>;

  return <div className="space-y-6">
    <section className="rounded-2xl border bg-white p-5">
      <div className="flex flex-wrap items-start justify-between gap-3"><div><h2 className="text-xl font-bold">{account.name}</h2><p className="text-sm text-slate-600">{account.email} · {account.mobile}</p></div><button onClick={logout} className="rounded-xl border px-4 py-2">Sign out</button></div>
      <p className="mt-3 text-sm text-slate-500">Customer session inactivity: {account.session_policy.inactivity_minutes ?? 120} minutes.</p>
    </section>
    <section><h2 className="text-xl font-bold">Orders</h2><div className="mt-3 space-y-3">{orders.length === 0 ? <p className="text-slate-600">No orders yet.</p> : orders.map((order) => <article key={order.id} className="rounded-2xl border bg-white p-4"><strong>{order.number}</strong><p className="text-sm text-slate-600">{order.status} · {order.payment_status} · {order.currency} {order.total}</p></article>)}</div></section>
    <section className="grid gap-3 sm:grid-cols-2"><div className="rounded-2xl border bg-white p-4"><strong>Saved items</strong><p className="mt-1 text-2xl font-bold">{wishlistCount}</p></div><div className="rounded-2xl border bg-white p-4"><strong>Your reviews</strong><p className="mt-1 text-2xl font-bold">{reviewCount}</p></div></section>
    <section>
      <h2 className="text-xl font-bold">Change password</h2>
      <form onSubmit={changePassword} className="mt-3 grid gap-3 rounded-2xl border bg-white p-4 sm:grid-cols-3">
        <input name="current_password" type="password" required placeholder="Current password" className="rounded-xl border p-3" />
        <input name="password" type="password" required minLength={8} placeholder="New password" className="rounded-xl border p-3" />
        <input name="password_confirmation" type="password" required minLength={8} placeholder="Confirm new password" className="rounded-xl border p-3" />
        <button className="rounded-xl bg-slate-950 px-4 py-2 text-white sm:col-span-3 sm:w-fit">Update password</button>
      </form>
      {message && <p className="mt-2 text-sm text-slate-600">{message}</p>}
    </section>
    <CustomerEngagementPanel />
  </div>;
}
