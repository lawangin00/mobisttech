import { Head, Link } from '@inertiajs/react';

type Channel = {
    code: string;
    label: string;
    enabled: boolean;
    merchant_configured: boolean;
    available: boolean;
};

type Props = {
    identity: { name: string; job_title: string | null };
    channels: Channel[];
};

export default function WebsitePaymentSettings({ identity, channels }: Props) {
    return <>
        <Head title="Website payment channels" />
        <main className="min-h-screen bg-slate-50 p-4 text-slate-950 sm:p-6">
            <div className="mx-auto max-w-5xl space-y-5">
                <header className="flex flex-wrap items-center justify-between gap-3 rounded-2xl border bg-white p-5">
                    <div>
                        <p className="text-xs font-semibold uppercase tracking-[0.2em] text-slate-500">mobiST Technologies</p>
                        <h1 className="text-2xl font-semibold">Website payment channels</h1>
                        <p className="text-sm text-slate-600">{identity.name} · {identity.job_title ?? 'Administrator'}</p>
                    </div>
                    <Link href="/internal/admin/platform" className="rounded border px-3 py-2 text-sm">Back to Platform Administration</Link>
                </header>
                <section className="rounded-2xl border bg-white p-5">
                    <h2 className="font-semibold">Channel status · read-only</h2>
                    <p className="mt-1 text-sm text-slate-600">This screen shows status only. It cannot register merchants, change credentials, activate a provider or confirm an external payment. An enabled setting is not evidence that a provider is available.</p>
                    <div className="mt-4 overflow-x-auto">
                        <table className="w-full min-w-[520px] text-left text-sm">
                            <thead><tr className="border-b"><th className="p-2">Channel</th><th className="p-2">Setting</th><th className="p-2">Merchant configured</th><th className="p-2">Checkout available</th></tr></thead>
                            <tbody>{channels.map(channel => <tr key={channel.code} className="border-b">
                                <th scope="row" className="p-2 font-medium">{channel.label}</th>
                                <td className="p-2">{channel.enabled ? 'Enabled in configuration' : 'Off'}</td>
                                <td className="p-2">{channel.merchant_configured ? 'Yes' : 'No'}</td>
                                <td className="p-2">{channel.available ? 'Available' : 'Unavailable'}</td>
                            </tr>)}</tbody>
                        </table>
                    </div>
                    <p className="mt-4 text-xs text-slate-600">Merchant identifiers and credentials are intentionally not displayed. Genuine payment-provider activation and settlement verification remain pending.</p>
                </section>
            </div>
        </main>
    </>;
}
