import { notFound } from "next/navigation";
import { DigitalEnquiryForm } from "@/components/digital-enquiry-form";
import { readConsultationAvailability, readServices, readWebsiteProfile } from "@/lib/website-api";

export const dynamic = "force-dynamic";

const safeAttribution = (value: string | undefined): string | null =>
  value && /^[A-Za-z0-9][A-Za-z0-9_-]{0,59}$/.test(value) ? value : null;

export default async function EnquiryPage({ searchParams }: { searchParams: Promise<{ service?: string; source?: string; campaign?: string }> }) {
  const profile = await readWebsiteProfile();
  if (!profile?.capabilities.digital) notFound();
  const [{ service, source, campaign }, services, consultation] = await Promise.all([
    searchParams,
    readServices(),
    readConsultationAvailability().catch(() => ({ enabled: false, timezone: null, weekly_availability: [] })),
  ]);
  return <main className="mx-auto max-w-4xl px-4 py-10 sm:px-6">
    <h1 className="text-3xl font-bold">Project enquiry</h1>
    <p className="mt-2 mb-6 text-slate-600">Start with the essentials. Optional project details can help us respond more precisely.</p>
    <DigitalEnquiryForm services={services} defaultService={service}
      attributionSource={safeAttribution(source)} attributionCampaign={safeAttribution(campaign)}
      consultation={consultation} />
  </main>;
}
