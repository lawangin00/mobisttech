"use client";

import Link from "next/link";
import { FormEvent, useState } from "react";
import { clearCustomerCsrf, customerRequest } from "@/lib/customer-api";

export function ResetPasswordForm({ token, email }: { token: string; email: string }) {
  const [message, setMessage] = useState("");
  const [done, setDone] = useState(false);

  async function submit(event: FormEvent<HTMLFormElement>) {
    event.preventDefault();
    const data = new FormData(event.currentTarget);
    setMessage("");
    try {
      const result = await customerRequest<{ message: string }>("auth/reset-password", {
        method: "POST",
        body: JSON.stringify({
          email,
          token,
          password: data.get("password"),
          password_confirmation: data.get("password_confirmation"),
        }),
      });
      clearCustomerCsrf();
      setDone(true);
      setMessage(result.message);
    } catch (error) {
      setMessage(error instanceof Error ? error.message : "This recovery link is invalid or expired.");
    }
  }

  if (!token || !email) return <p className="rounded-2xl border bg-white p-5 text-red-700">This recovery link is incomplete.</p>;
  if (done) return <div className="rounded-2xl border bg-white p-5"><p>{message}</p><Link href="/account" className="mt-4 inline-block rounded-xl bg-slate-950 px-4 py-2 text-white">Sign in</Link></div>;

  return <form onSubmit={submit} className="space-y-3 rounded-2xl border bg-white p-5">
    <p className="text-sm text-slate-600">Reset password for <strong>{email}</strong>.</p>
    <input name="password" type="password" required minLength={8} placeholder="New password" className="w-full rounded-xl border p-3" />
    <input name="password_confirmation" type="password" required minLength={8} placeholder="Confirm new password" className="w-full rounded-xl border p-3" />
    <button className="rounded-xl bg-slate-950 px-5 py-3 font-semibold text-white">Reset password</button>
    {message && <p className="text-sm text-red-700">{message}</p>}
  </form>;
}
