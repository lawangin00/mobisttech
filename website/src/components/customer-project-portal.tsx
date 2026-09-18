"use client";

import Link from "next/link";
import { FormEvent, useCallback, useEffect, useMemo, useState } from "react";
import { customerRequest } from "@/lib/customer-api";

type Channel = { code: "jazzcash" | "easypaisa" | "card"; label: string; available: boolean };
type Milestone = {
  id: string; sequence: number; kind: string; label: string; amount: string; currency: string;
  due_at: string | null; paid_at: string | null; payment_status: string; payable: boolean;
};
type Proposal = {
  public_id: string; revision: number; state: string; title: string; amount: string; currency: string;
  valid_until: string; scope: string; deliverables: string[]; schedule: unknown; snapshot_sha256: string;
  quote: { public_id: string; reference: string; status: string; expires_at: string | null; paid_at: string | null } | null;
  milestones: Milestone[];
};
type ProjectFile = {
  id: string; type: "reference" | "delivery"; name: string; mime_type: string; byte_size: number;
  sha256: string; retention_until: string | null; created_at: string;
};
type Project = {
  public_id: string; reference: string; title: string; status: string; version: number;
  service: { slug: string | null; name: string | null };
  proposals: Proposal[]; files: ProjectFile[];
  history: Array<{ type: string; snapshot: Record<string, unknown>; occurred_at: string }>;
};

function pretty(value: string) {
  return value.replaceAll("_", " ");
}

function formatDate(value: string | null) {
  if (!value) return "—";
  const date = new Date(value);
  return Number.isNaN(date.getTime()) ? value : date.toLocaleString();
}

async function fileToBase64(file: File) {
  const url = await new Promise<string>((resolve, reject) => {
    const reader = new FileReader();
    reader.onload = () => resolve(String(reader.result ?? ""));
    reader.onerror = () => reject(reader.error ?? new Error("Unable to read file."));
    reader.readAsDataURL(file);
  });
  const comma = url.indexOf(",");
  if (comma < 0) throw new Error("Unable to encode file.");
  return url.slice(comma + 1);
}

export function CustomerProjectPortal({ projectId }: { projectId: string }) {
  const [project, setProject] = useState<Project | null>(null);
  const [channels, setChannels] = useState<Channel[]>([]);
  const [message, setMessage] = useState("");
  const [busy, setBusy] = useState("");

  const refresh = useCallback(async () => {
    const [next, paymentChannels] = await Promise.all([
      customerRequest<Project>("projects/" + projectId),
      customerRequest<{ items: Channel[] }>("project-payment-channels").catch(() => ({ items: [] })),
    ]);
    setProject(next);
    setChannels(paymentChannels.items);
  }, [projectId]);

  useEffect(() => {
    const timer = window.setTimeout(() => {
      void refresh().catch((error) => setMessage(error instanceof Error ? error.message : "Unable to load project."));
    }, 0);
    return () => window.clearTimeout(timer);
  }, [refresh]);

  const availableChannels = useMemo(() => channels.filter((channel) => channel.available), [channels]);

  async function pay(milestone: Milestone, gateway: Channel["code"]) {
    if (busy) return;
    setBusy(milestone.id);
    setMessage("");
    try {
      const created = await customerRequest<{ payment_id: string }>("project-milestones/pay", {
        method: "POST",
        idempotencyKey: "project-milestone-" + milestone.id + "-" + crypto.randomUUID(),
        body: JSON.stringify({ milestone_id: milestone.id, gateway }),
      });
      const initiated = await customerRequest<{ redirect_url?: string }>("payments/" + created.payment_id + "/initiate", { method: "POST", body: JSON.stringify({}) });
      if (!initiated.redirect_url) throw new Error("Payment provider did not return a continuation URL.");
      const url = new URL(initiated.redirect_url);
      if (url.protocol !== "https:") throw new Error("Payment continuation must use HTTPS.");
      window.location.assign(url.toString());
    } catch (error) {
      setMessage(error instanceof Error ? error.message : "Unable to start milestone payment.");
      await refresh().catch(() => undefined);
    } finally {
      setBusy("");
    }
  }

  async function uploadReference(event: FormEvent<HTMLFormElement>) {
    event.preventDefault();
    if (busy) return;
    const formElement = event.currentTarget;
    const form = new FormData(formElement);
    const file = form.get("reference");
    if (!(file instanceof File) || file.size === 0) return;
    if (file.size > 10 * 1024 * 1024) {
      setMessage("Reference files must be 10 MB or smaller.");
      return;
    }
    setBusy("upload");
    setMessage("");
    try {
      await customerRequest("projects/" + projectId + "/files/reference", {
        method: "POST",
        body: JSON.stringify({ name: file.name, base64: await fileToBase64(file) }),
      });
      setMessage("Reference file uploaded.");
      formElement.reset();
      await refresh();
    } catch (error) {
      setMessage(error instanceof Error ? error.message : "Unable to upload reference.");
    } finally {
      setBusy("");
    }
  }

  if (!project) return <main className="mx-auto max-w-5xl px-4 py-10 sm:px-6"><p>{message || "Loading project…"}</p></main>;

  return <main className="mx-auto max-w-5xl px-4 py-10 sm:px-6">
    <Link href="/account" className="text-sm font-medium text-slate-600 hover:text-slate-950">← Account</Link>
    <div className="mt-4 flex flex-wrap items-start justify-between gap-4">
      <div><p className="text-sm text-slate-500">{project.reference}</p><h1 className="text-3xl font-bold">{project.title}</h1><p className="mt-2 text-slate-600">{project.service.name ?? "Client project"} · {pretty(project.status)}</p></div>
      <span className="rounded-full border px-3 py-1 text-sm capitalize">{pretty(project.status)}</span>
    </div>

    {message && <p role="status" className="mt-5 rounded-xl bg-slate-100 p-3 text-sm">{message}</p>}

    <section className="mt-8">
      <h2 className="text-xl font-bold">Proposals & milestones</h2>
      <div className="mt-3 space-y-5">{project.proposals.length === 0 ? <p className="text-slate-600">No approved or historical proposal is available yet.</p> : project.proposals.map((proposal) => <article key={proposal.public_id} className="rounded-2xl border bg-white p-5">
        <div className="flex flex-wrap justify-between gap-3"><div><h3 className="font-bold">{proposal.title}</h3><p className="text-sm text-slate-500">Revision {proposal.revision} · {pretty(proposal.state)} · valid until {formatDate(proposal.valid_until)}</p></div><strong>{proposal.currency} {proposal.amount}</strong></div>
        <p className="mt-4 whitespace-pre-wrap text-slate-700">{proposal.scope}</p>
        {proposal.deliverables.length > 0 && <ul className="mt-3 list-disc pl-5 text-sm text-slate-700">{proposal.deliverables.map((item) => <li key={item}>{item}</li>)}</ul>}
        {proposal.quote && <p className="mt-3 text-xs text-slate-500">Quote {proposal.quote.reference} · {pretty(proposal.quote.status)} · expires {formatDate(proposal.quote.expires_at)}</p>}
        <div className="mt-5 space-y-3">{proposal.milestones.map((milestone) => <div key={milestone.id} className="rounded-xl bg-slate-50 p-4">
          <div className="flex flex-wrap items-center justify-between gap-3"><div><strong>{milestone.label}</strong><p className="text-sm text-slate-600">{milestone.currency} {milestone.amount} · due {formatDate(milestone.due_at)} · {milestone.paid_at ? "paid " + formatDate(milestone.paid_at) : pretty(milestone.payment_status)}</p></div>
          {milestone.payable && <div className="flex flex-wrap gap-2">{availableChannels.length === 0 ? <span className="text-xs text-slate-500">No external payment provider is configured.</span> : availableChannels.map((channel) => <button key={channel.code} disabled={busy === milestone.id} onClick={() => void pay(milestone, channel.code)} className="rounded-lg bg-slate-950 px-3 py-2 text-xs font-semibold text-white disabled:opacity-50">Pay with {channel.label}</button>)}</div>}</div>
        </div>)}</div>
      </article>)}</div>
    </section>

    <section className="mt-8">
      <h2 className="text-xl font-bold">Private files</h2>
      <div className="mt-3 grid gap-3 md:grid-cols-2">{project.files.length === 0 ? <p className="text-slate-600">No project files yet.</p> : project.files.map((file) => <article key={file.id} className="rounded-2xl border bg-white p-4"><strong>{file.name}</strong><p className="mt-1 text-xs text-slate-500">{pretty(file.type)} · {Math.ceil(file.byte_size / 1024)} KB · {formatDate(file.created_at)}</p><a href={"/api/customer/projects/" + project.public_id + "/files/" + file.id} className="mt-3 inline-block text-sm font-semibold underline">Download securely</a></article>)}</div>
      {project.status !== "closed" && <form onSubmit={uploadReference} className="mt-4 rounded-2xl border bg-white p-4"><h3 className="font-bold">Add a reference file</h3><p className="mt-1 text-xs text-slate-500">PDF, JPEG, PNG or plain text · maximum 10 MB.</p><div className="mt-3 flex flex-wrap gap-3"><input name="reference" type="file" required accept=".pdf,.jpg,.jpeg,.png,.txt,application/pdf,image/jpeg,image/png,text/plain" className="max-w-full text-sm" /><button disabled={busy === "upload"} className="rounded-xl bg-slate-950 px-4 py-2 text-sm font-semibold text-white disabled:opacity-50">{busy === "upload" ? "Uploading…" : "Upload reference"}</button></div></form>}
    </section>

    <section className="mt-8">
      <h2 className="text-xl font-bold">Project history</h2>
      <ol className="mt-3 space-y-3">{project.history.length === 0 ? <li className="text-slate-600">No public project events yet.</li> : project.history.map((event, index) => <li key={event.type + event.occurred_at + index} className="rounded-xl border bg-white p-4"><strong className="capitalize">{pretty(event.type)}</strong><p className="mt-1 text-xs text-slate-500">{formatDate(event.occurred_at)}</p></li>)}</ol>
    </section>
  </main>;
}
