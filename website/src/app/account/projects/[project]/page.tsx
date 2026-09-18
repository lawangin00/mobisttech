import { CustomerProjectPortal } from "@/components/customer-project-portal";

export const dynamic = "force-dynamic";

export default async function ProjectPage({ params }: { params: Promise<{ project: string }> }) {
  const { project } = await params;
  return <CustomerProjectPortal projectId={project} />;
}
