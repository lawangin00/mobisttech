"use client";

import Link from "next/link";
import { FormEvent, useCallback, useEffect, useState } from "react";
import { clearCustomerCsrf, CustomerAccount, customerRequest, ensureCustomerCsrf } from "@/lib/customer-api";
import { clearGuestOwnerToken, readGuestOwnerToken } from "@/lib/customer-guest";
import { CustomerEngagementPanel } from "@/components/customer-engagement-panel";

type Order = { id: string; number: string; status: string; fulfillment_status: string; payment_status: string; total: string; currency: string; created_at: string };
type OrderPage = { items: Order[]; page: { has_more: boolean; next_cursor: string | null } };
type ProjectSummary = {
  public_id: string; reference: string; title: string; status: string; version: number; updated_at: string;
  service: { slug: string | null; name: string | null };
};

export function CustomerAccountPanel() {
  const [account, setAccount] = useState<CustomerAccount | null>(null);
  const [orders, setOrders] = useState<Order[]>([]);
  const [projects, setProjects] = useState<ProjectSummary[]>([]);
  const [wishlistCount, setWishlistCount] = useState(0);
  const [reviewCount, setReviewCount] = useState(0);
  const [snapshotReady, setSnapshotReady] = useState(false);
  const [register, setRegister] = useState(false);
  const [recovery, setRecovery] = useState(false);
  const [message, setMessage] = useState("");

  const refresh = useCallback(async () => {
    const current = await customerRequest<CustomerAccount>("account");
    setAccount(current);
    setSnapshotReady(false);
    const guestToken = readGuestOwnerToken();
    if (guestToken) {
      await customerRequest("wishlist/claim", { method: "POST", body: JSON.stringify({ guest_token: guestToken }) })
        .then(() => clearGuestOwnerToken()).catch(() => undefined);
    }
    const [orderData, projectData, wishlist, reviews] = await Promise.all([
      customerRequest<OrderPage>("orders"),
      customerRequest<{ items: ProjectSummary[] }>("projects").catch(() => ({ items: [] })),
      customerRequest<{ items: unknown[] }>("wishlist").catch(() => ({ items: [] })),
      customerRequest<{ items: unknown[] }>("reviews").catch(() => ({ items: [] })),
    ]);
    setOrders(orderData.items);
    setProjects(projectData.items);
    setWishlistCount(wishlist.items.length);
    setReviewCount(reviews.items.length);
    setSnapshotReady(true);
  }, []);

  useEffect(() => {
    // Establish the session before account requests to prevent competing first-session cookies.
    const timer = window.setTimeout(() => {
      void ensureCustomerCsrf()
        .then(() => refresh())
        .catch(() => setAccount(null));
    }, 0);
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
      clearCustomerCsrf();
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
      setAccount(null); setOrders([]); setProjects([]); setWishlistCount(0); setReviewCount(0); setSnapshotReady(false);
      setMessage(result.message);
    } catch (error) {
      setMessage(error instanceof Error ? error.message : "Unable to change password.");
    }
  }

  async function updateProfile(event: FormEvent<HTMLFormElement>) {
    event.preventDefault();
    const form = new FormData(event.currentTarget);
    setMessage("");
    try {
      await customerRequest("account/profile", { method: "PATCH", body: JSON.stringify({ name: form.get("name"), mobile: form.get("mobile") }) });
      await refresh();
      setMessage("Profile updated.");
    } catch (error) { setMessage(error instanceof Error ? error.message : "Unable to update profile."); }
  }

  async function uploadPhoto(event: FormEvent<HTMLFormElement>) {
    event.preventDefault();
    const form = new FormData(event.currentTarget);
    setMessage("");
    try {
      await customerRequest("account/photo", { method: "POST", body: form });
      await refresh();
      setMessage("Profile photo updated.");
    } catch (error) { setMessage(error instanceof Error ? error.message : "Unable to update profile photo."); }
  }

  async function removePhoto() {
    setMessage("");
    try {
      await customerRequest("account/photo", { method: "DELETE", body: JSON.stringify({}) });
      await refresh();
      setMessage("Profile photo removed.");
    } catch (error) { setMessage(error instanceof Error ? error.message : "Unable to remove profile photo."); }
  }

  async function logout() {
    await customerRequest<{ message: string }>("auth/logout", { method: "POST", body: JSON.stringify({}) });
    clearCustomerCsrf();
    setAccount(null); setOrders([]); setProjects([]); setWishlistCount(0); setReviewCount(0); setSnapshotReady(false);
  }

  if (!account) return <div className="max-w-lg">
    {recovery ? <>
      <button className="mb-4 text-sm font-medium text-slate-700" onClick={() => { setRecovery(false); setMessage(""); }}>← Back to sign in</button>
      <form onSubmit={requestRecovery} className="space-y-3 rounded-2xl border bg-white p-5">
        <h2 className="text-xl font-bold">Reset password</h2>
        <p className="text-sm text-slate-600">Enter your customer-account email. A secure reset link is sent only when recovery delivery is configured.</p>
        <input name="email" aria-label="Recovery email" type="email" required placeholder="Email" className="w-full rounded-xl border p-3" />
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
        <input name="email" aria-label="Email" type="email" required placeholder="Email" className="w-full rounded-xl border p-3" />
        <input name="password" aria-label="Password" type="password" required minLength={8} placeholder="Password" className="w-full rounded-xl border p-3" />
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
      <div className="flex flex-wrap items-start justify-between gap-3"><div className="flex items-center gap-3">{account.has_photo && <img src="/api/customer/account/photo" alt="Customer profile" className="h-14 w-14 rounded-full object-cover" />}<div><h2 className="text-xl font-bold">{account.name}</h2><p className="text-sm text-slate-600">{account.email} · {account.mobile}</p></div></div><button onClick={logout} className="rounded-xl border px-4 py-2">Sign out</button></div>
      <p className="mt-3 text-sm text-slate-500">Customer session inactivity: {account.session_policy.inactivity_minutes ?? 120} minutes.</p>
    </section>
    <section><h2 className="text-xl font-bold">Profile</h2><form onSubmit={updateProfile} className="mt-3 grid gap-3 rounded-2xl border bg-white p-4 sm:grid-cols-2"><input name="name" required defaultValue={account.name} aria-label="Profile name" className="rounded-xl border p-3" /><input name="mobile" required pattern="03[0-9]{9}" defaultValue={account.mobile} aria-label="Profile mobile" className="rounded-xl border p-3" /><button className="rounded-xl bg-slate-950 px-4 py-2 text-white sm:col-span-2 sm:w-fit">Save profile</button></form><form onSubmit={uploadPhoto} className="mt-3 flex flex-wrap items-center gap-3 rounded-2xl border bg-white p-4"><input name="profile_photo" type="file" required accept="image/jpeg,image/png,image/webp" aria-label="Profile photo" className="max-w-full text-sm" /><button className="rounded-xl border px-4 py-2">Upload photo</button>{account.has_photo && <button type="button" onClick={() => void removePhoto()} className="rounded-xl border border-red-300 px-4 py-2 text-red-700">Remove photo</button>}</form></section>
    <section><h2 className="text-xl font-bold">Orders</h2><div className="mt-3 space-y-3">{!snapshotReady ? <p className="text-slate-600">Loading orders…</p> : orders.length === 0 ? <p className="text-slate-600">No orders yet.</p> : orders.map((order) => <article key={order.id} className="rounded-2xl border bg-white p-4"><Link href={"/account/orders/" + order.id} className="font-semibold underline-offset-2 hover:underline">{order.number}</Link><p className="text-sm text-slate-600">{order.status} · {order.payment_status} · {order.currency} {order.total}</p></article>)}</div></section>
    <section><h2 className="text-xl font-bold">Projects</h2><div className="mt-3 space-y-3">{!snapshotReady ? <p className="text-slate-600">Loading projects…</p> : projects.length === 0 ? <p className="text-slate-600">No client projects yet.</p> : projects.map((project) => <article key={project.public_id} className="rounded-2xl border bg-white p-4"><Link href={"/account/projects/" + project.public_id} className="font-semibold underline-offset-2 hover:underline">{project.title}</Link><p className="text-sm text-slate-600">{project.reference} · {project.status.replaceAll("_", " ")}</p>{project.service.name && <p className="mt-1 text-xs text-slate-500">{project.service.name}</p>}</article>)}</div></section>
    <section className="grid gap-3 sm:grid-cols-2"><div className="rounded-2xl border bg-white p-4"><strong>Saved items</strong><p className="mt-1 text-2xl font-bold">{snapshotReady ? wishlistCount : "…"}</p></div><div className="rounded-2xl border bg-white p-4"><strong>Your reviews</strong><p className="mt-1 text-2xl font-bold">{snapshotReady ? reviewCount : "…"}</p></div></section>
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
    {snapshotReady ? <CustomerEngagementPanel /> : <p className="text-sm text-slate-500">Loading account details…</p>}
  </div>;
}
