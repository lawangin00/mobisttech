import { readBackendHealth } from "@/lib/backend-health";

export const dynamic = "force-dynamic";

export default async function Home() {
  const connected = await readBackendHealth();
  return (
    <main className="mx-auto flex min-h-screen max-w-3xl flex-col justify-center px-6 py-12">
      <p className="mb-3 text-sm font-semibold uppercase tracking-widest text-slate-500">mobiST Tech</p>
      <h1 className="text-4xl font-semibold tracking-tight text-slate-950">Website foundation</h1>
      <p className="mt-5 text-lg leading-8 text-slate-600">Next.js, React, TypeScript and Tailwind are ready. Catalogue, customer accounts and checkout will be migrated through the shared Laravel API.</p>
      <p role="status" className="mt-8 rounded-xl border border-slate-200 bg-white p-4 text-sm text-slate-700">Shared backend: {connected ? "connected" : "unavailable"}</p>
      <p className="mt-4 text-sm text-slate-500">Application foundation only. No business data is connected.</p>
    </main>
  );
}
