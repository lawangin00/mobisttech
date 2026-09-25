"use client";

import { FormEvent, useMemo, useRef, useState } from "react";
import type { ConsultationAvailability, DigitalService } from "@/lib/website-api";

type Props = {
  services: DigitalService[];
  defaultService?: string;
  attributionSource?: string | null;
  attributionCampaign?: string | null;
  consultation: ConsultationAvailability;
};

function optional(value: FormDataEntryValue | null) {
  const text = typeof value === "string" ? value.trim() : "";
  return text === "" ? null : text;
}

export function DigitalEnquiryForm({ services, defaultService, attributionSource, attributionCampaign, consultation }: Props) {
  const [serviceSlug, setServiceSlug] = useState(defaultService ?? services[0]?.slug ?? "");
  const [consultationRequested, setConsultationRequested] = useState(false);
  const [message, setMessage] = useState("");
  const [busy, setBusy] = useState(false);
  const keyRef = useRef<string | null>(null);
  const service = useMemo(() => services.find((item) => item.slug === serviceSlug) ?? null, [services, serviceSlug]);

  async function submit(event: FormEvent<HTMLFormElement>) {
    event.preventDefault();
    if (!service || busy) return;
    const formElement = event.currentTarget;
    const form = new FormData(formElement);
    setBusy(true);
    setMessage("");
    try {
      if (!keyRef.current) keyRef.current = "website-enquiry-" + crypto.randomUUID();
      const addonIds = form.getAll("addon_public_ids").map(String);
      const payload = {
        service_slug: service.slug,
        customer_name: form.get("customer_name"),
        business_name: optional(form.get("business_name")),
        customer_mobile: form.get("customer_mobile"),
        customer_email: optional(form.get("customer_email")),
        requirements: form.get("requirements"),
        project_type: optional(form.get("project_type")),
        existing_url: optional(form.get("existing_url")),
        budget_range: optional(form.get("budget_range")),
        preferred_timeline: optional(form.get("preferred_timeline")),
        preferred_contact: form.get("preferred_contact") || "whatsapp",
        package_public_id: optional(form.get("package_public_id")),
        addon_public_ids: addonIds,
        source: attributionSource ?? "website",
        campaign: attributionCampaign ?? null,
        consultation_requested: consultationRequested,
        preferred_timezone: consultationRequested ? consultation.timezone : null,
        preferred_window_start: consultationRequested ? optional(form.get("preferred_window_start")) : null,
        preferred_window_end: consultationRequested ? optional(form.get("preferred_window_end")) : null,
      };
      const response = await fetch("/api/public/enquiries", {
        method: "POST",
        cache: "no-store",
        headers: { "Content-Type": "application/json", "Idempotency-Key": keyRef.current },
        body: JSON.stringify(payload),
      });
      const body = await response.json().catch(() => null) as { data?: { reference?: string }; message?: string } | null;
      if (!response.ok) throw new Error(body?.message ?? "Unable to submit enquiry.");
      setMessage("Enquiry submitted" + (body?.data?.reference ? " · " + body.data.reference : "") + ".");
      keyRef.current = null;
      formElement.reset();
      setConsultationRequested(false);
    } catch (error) {
      setMessage(error instanceof Error ? error.message : "Unable to submit enquiry.");
    } finally {
      setBusy(false);
    }
  }

  if (!service) return <p className="text-slate-600">No active Digital Service is available for enquiry.</p>;

  return <form onSubmit={submit} onChange={() => { keyRef.current = null; }} className="space-y-5 rounded-2xl border bg-white p-5">
    <div className="grid gap-3 sm:grid-cols-2">
      <label className="text-sm font-medium">Service
        <select name="service_slug" value={serviceSlug} onChange={(event) => setServiceSlug(event.target.value)} className="mt-1 w-full rounded-xl border p-3">
          {services.map((item) => <option key={item.slug} value={item.slug}>{item.name}</option>)}
        </select>
      </label>
      <label className="text-sm font-medium">Name
        <input name="customer_name" required className="mt-1 w-full rounded-xl border p-3" />
      </label>
      <label className="text-sm font-medium">Mobile
        <input name="customer_mobile" required className="mt-1 w-full rounded-xl border p-3" />
      </label>
      <label className="text-sm font-medium">Email <span className="font-normal text-slate-500">(optional)</span>
        <input name="customer_email" type="email" className="mt-1 w-full rounded-xl border p-3" />
      </label>
      <label className="text-sm font-medium">Business <span className="font-normal text-slate-500">(optional)</span>
        <input name="business_name" className="mt-1 w-full rounded-xl border p-3" />
      </label>
      <label className="text-sm font-medium">Existing URL <span className="font-normal text-slate-500">(optional)</span>
        <input name="existing_url" type="url" className="mt-1 w-full rounded-xl border p-3" />
      </label>
      <label className="text-sm font-medium">Project type <span className="font-normal text-slate-500">(optional)</span>
        <input name="project_type" className="mt-1 w-full rounded-xl border p-3" />
      </label>
      <label className="text-sm font-medium">Budget range <span className="font-normal text-slate-500">(optional)</span>
        <input name="budget_range" className="mt-1 w-full rounded-xl border p-3" />
      </label>
      <label className="text-sm font-medium">Preferred timeline <span className="font-normal text-slate-500">(optional)</span>
        <input name="preferred_timeline" className="mt-1 w-full rounded-xl border p-3" />
      </label>
      <label className="text-sm font-medium">Preferred contact
        <select name="preferred_contact" defaultValue="whatsapp" className="mt-1 w-full rounded-xl border p-3">
          <option value="whatsapp">WhatsApp</option><option value="phone">Phone</option><option value="email">Email</option>
        </select>
      </label>
    </div>

    {service.packages.length > 0 && <fieldset><legend className="font-semibold">Package {service.price_type !== "package" && <span className="font-normal text-slate-500">(optional)</span>}</legend>
      <select name="package_public_id" aria-label="Package" required={service.price_type === "package"} defaultValue="" className="mt-2 w-full rounded-xl border p-3">
        <option value="">No package selected</option>
        {service.packages.map((item) => <option key={item.public_id} value={item.public_id}>{item.name}{item.price ? " · PKR " + item.price : ""}</option>)}
      </select>
    </fieldset>}

    {service.addons.length > 0 && <fieldset><legend className="font-semibold">Add-ons <span className="font-normal text-slate-500">(optional)</span></legend>
      <div className="mt-2 grid gap-2 sm:grid-cols-2">{service.addons.map((item) => <label key={item.public_id} className="rounded-xl border p-3 text-sm">
        <input type="checkbox" name="addon_public_ids" value={item.public_id} className="mr-2" />{item.name}{item.price ? " · PKR " + item.price : ""}
      </label>)}</div>
    </fieldset>}

    <label className="block text-sm font-medium">What do you need?
      <textarea name="requirements" required rows={5} className="mt-1 w-full rounded-xl border p-3" />
    </label>

    {consultation.enabled && <section className="rounded-xl bg-slate-50 p-4">
      <label className="font-medium"><input type="checkbox" checked={consultationRequested} onChange={(event) => setConsultationRequested(event.target.checked)} className="mr-2" />Request a consultation</label>
      {consultationRequested && <div className="mt-3 grid gap-3 sm:grid-cols-2">
        <label className="text-sm">Window start<input name="preferred_window_start" type="datetime-local" required className="mt-1 w-full rounded-xl border p-3" /></label>
        <label className="text-sm">Window end<input name="preferred_window_end" type="datetime-local" required className="mt-1 w-full rounded-xl border p-3" /></label>
        <p className="text-xs text-slate-500 sm:col-span-2">Timezone: {consultation.timezone}. Available windows are validated by the server.</p>
      </div>}
    </section>}

    <button disabled={busy} className="rounded-xl bg-slate-950 px-5 py-3 font-semibold text-white disabled:opacity-50">{busy ? "Submitting…" : "Send enquiry"}</button>
    {message && <p className="text-sm" role="status">{message}</p>}
  </form>;
}
