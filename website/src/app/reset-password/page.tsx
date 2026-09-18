import { ResetPasswordForm } from "@/components/reset-password-form";

export const dynamic = "force-dynamic";

export default async function ResetPasswordPage({ searchParams }: { searchParams: Promise<{ token?: string; email?: string }> }) {
  const params = await searchParams;
  return <main className="mx-auto max-w-xl px-4 py-10 sm:px-6">
    <h1 className="text-3xl font-bold">Reset password</h1>
    <p className="mt-2 mb-6 text-slate-600">Use the single-use recovery link sent to your customer-account email.</p>
    <ResetPasswordForm token={params.token ?? ""} email={params.email ?? ""} />
  </main>;
}
