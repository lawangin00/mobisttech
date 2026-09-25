import Link from "next/link";
import { notFound } from "next/navigation";
import { readServices, readWebsiteProfile } from "@/lib/website-api";

export const dynamic = "force-dynamic";

function priceLabel(type: string, price: string | null) {
  if (type === "quote") return "Custom quotation";
  if (type === "package") return "Packages available";
  if (!price) return "Contact us";
  return type === "starting_from" ? "Starting from PKR " + price : "PKR " + price;
}

export default async function ServicesPage() {
  const profile = await readWebsiteProfile();
  if (!profile?.capabilities.digital) notFound();
  const services = await readServices();
  return <main className="mx-auto max-w-6xl px-4 py-10 sm:px-6">
    <div className="flex flex-wrap items-end justify-between gap-4">
      <div><h1 className="text-3xl font-bold">Digital Services</h1><p className="mt-2 text-slate-600">Published services, packages and custom project options.</p></div>
      <Link href="/enquiry" className="rounded-xl bg-slate-950 px-4 py-2 text-white">Start an enquiry</Link>
    </div>
    <div className="mt-8 grid gap-5 md:grid-cols-2">{services.map((service) => <article key={service.slug} className="rounded-2xl border bg-white p-5">
      <p className="text-xs font-semibold uppercase tracking-wide text-slate-500">{service.category ?? "Digital service"}</p>
      <h2 className="mt-1 text-xl font-bold"><Link href={"/services/" + service.slug} className="hover:underline">{service.name}</Link></h2>
      <p className="mt-2 text-slate-600">{service.short_description}</p>
      <p className="mt-4 font-semibold">{priceLabel(service.price_type, service.price)}</p>
      <div className="mt-4 flex gap-3"><Link href={"/services/" + service.slug} className="rounded-xl border px-4 py-2">Details</Link><Link href={"/enquiry?service=" + encodeURIComponent(service.slug) + "&source=services_index&campaign=service_" + encodeURIComponent(service.slug)} className="rounded-xl bg-slate-950 px-4 py-2 text-white">Enquire</Link></div>
    </article>)}</div>
  </main>;
}
