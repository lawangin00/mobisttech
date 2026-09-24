import { Head, Link, router } from '@inertiajs/react';
import { FormEvent, useState } from 'react';

type Channel = {
    code: string;
    label: string;
    enabled: boolean;
    merchant_configured: boolean;
    available: boolean;
};

type Settings = {
    cod_enabled: boolean;
    published_version: number;
    published_invalid: boolean;
    draft_invalid: boolean;
    draft: { id: number; version: number; cod_enabled: boolean } | null;
    can_publish: boolean;
};

type PresentationPolicy = { channels: Record<string, { label: string; instructions: string }>; cod_min_amount: string | null; cod_max_amount: string | null };
type PresentationRevisions = { published: PresentationPolicy; published_version: number; published_invalid: boolean; draft_invalid: boolean; draft: { id: number; version: number; policy: PresentationPolicy } | null };
type Props = {
    identity: { name: string; job_title: string | null };
    channels: Channel[];
    settings: Settings;
    presentation: PresentationRevisions;
};

// Admin uses a separate CSRF cookie; do not rely on Inertia's generic
// XSRF-TOKEN lookup or relax server-side CSRF protection.
async function adminCsrfToken(): Promise<string> {
    const response = await fetch('/internal/admin/auth/csrf-cookie', {
        credentials: 'same-origin',
        headers: { Accept: 'application/json' },
    });
    const body = await response.json() as { data?: { csrf_token?: string } };
    if (!response.ok || !body.data?.csrf_token) {
        throw new Error('Secure Admin request token is unavailable.');
    }
    return body.data.csrf_token;
}

// Require an actual JSON success from the authorized policy endpoint before
// refreshing Inertia props. A 303 redirect alone does not prove a draft was saved.
async function postNonsecretPaymentPolicy(path: string, payload: Record<string, unknown>, expectedStatus: number): Promise<void> {
    const token = await adminCsrfToken();
    const response = await fetch(path, {
        method: 'POST',
        credentials: 'same-origin',
        headers: {
            Accept: 'application/json',
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': token,
        },
        body: JSON.stringify(payload),
    });
    const body = await response.json().catch(() => null) as { data?: unknown; message?: string } | null;
    if (response.status !== expectedStatus || !body?.data) {
        throw new Error(body?.message ?? `Payment settings request failed (${response.status}).`);
    }
}

function canonicalAmount(value: string | null): string | null {
    if (value === null || value === '') return null;
    if (!/^(?:0|[1-9][0-9]{0,8})(?:\.[0-9]{1,2})?$/.test(value)) {
        throw new Error('COD amounts must be valid PKR values with at most two decimal places.');
    }
    const [whole, fraction = ''] = value.split('.');
    return `${whole}.${fraction.padEnd(2, '0')}`;
}
export default function WebsitePaymentSettings({ identity, channels, settings, presentation }: Props) {
    const [codEnabled, setCodEnabled] = useState(settings.draft?.cod_enabled ?? settings.cod_enabled);
    const [busy, setBusy] = useState(false);
    const [presentationPolicy, setPresentationPolicy] = useState<PresentationPolicy>(presentation.draft?.policy ?? presentation.published);
    const [presentationBusy, setPresentationBusy] = useState(false);
    const [presentationError, setPresentationError] = useState('');
    function updateChannel(code: string, field: 'label' | 'instructions', value: string) {
        setPresentationPolicy(previous => ({ ...previous, channels: { ...previous.channels,
            [code]: { ...previous.channels[code], [field]: value },
        } }));
    }
    function reloadPresentation() {
        router.visit('/internal/admin/website/payment-settings', {
            method: 'get', preserveState: false, preserveScroll: true, replace: true,
            onError: () => setPresentationError('Payment presentation could not be refreshed.'),
            onFinish: () => setPresentationBusy(false),
        });
    }
    async function savePresentation(event: FormEvent<HTMLFormElement>) {
        event.preventDefault();
        if (presentationBusy) return;
        setPresentationBusy(true);
        setPresentationError('');
        try {
            await postNonsecretPaymentPolicy('/internal/admin/website/payment-settings/presentation/drafts', { ...presentationPolicy,
                cod_min_amount: canonicalAmount(presentationPolicy.cod_min_amount),
                cod_max_amount: canonicalAmount(presentationPolicy.cod_max_amount),
            }, 201);
            reloadPresentation();
        } catch (cause) {
            setPresentationError(cause instanceof Error ? cause.message : 'Payment presentation draft failed.');
            setPresentationBusy(false);
        }
    }
    async function publishPresentation() {
        if (presentationBusy || !settings.can_publish || !presentation.draft) return;
        setPresentationBusy(true);
        setPresentationError('');
        try {
            await postNonsecretPaymentPolicy(`/internal/admin/website/payment-settings/presentation/drafts/${presentation.draft.id}/publish`, {}, 200);
            reloadPresentation();
        } catch (cause) {
            setPresentationError(cause instanceof Error ? cause.message : 'Payment presentation publish failed.');
            setPresentationBusy(false);
        }
    }
    const [error, setError] = useState('');

    function reloadPolicy() {
        router.visit('/internal/admin/website/payment-settings', {
            method: 'get',
            preserveState: false,
            preserveScroll: true,
            replace: true,
            onError: () => setError('COD policy could not be refreshed.'),
            onFinish: () => setBusy(false),
        });
    }

    async function saveDraft(event: FormEvent<HTMLFormElement>) {
        event.preventDefault();
        if (busy) return;
        setBusy(true);
        setError('');
        try {
            await postNonsecretPaymentPolicy('/internal/admin/website/payment-settings/drafts', { cod_enabled: codEnabled }, 201);
            reloadPolicy();
        } catch (cause) {
            setError(cause instanceof Error ? cause.message : 'Secure Admin request failed.');
            setBusy(false);
        }
    }

    async function publishDraft() {
        if (busy || !settings.can_publish || !settings.draft) return;
        setBusy(true);
        setError('');
        try {
            await postNonsecretPaymentPolicy(`/internal/admin/website/payment-settings/drafts/${settings.draft.id}/publish`, {}, 200);
            reloadPolicy();
        } catch (cause) {
            setError(cause instanceof Error ? cause.message : 'Secure Admin request failed.');
            setBusy(false);
        }
    }

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
                    <h2 className="font-semibold">Cash on Delivery policy</h2>
                    <p className="mt-1 text-sm text-slate-600">Current checkout: {settings.cod_enabled ? 'COD enabled' : 'COD disabled'} · published version {settings.published_version}. Only the nonsecret COD availability flag can be changed here. Saving a draft does not change checkout.</p>
                    {settings.published_invalid && <p role="alert" className="mt-3 rounded border border-red-300 p-3 text-sm">Published COD policy is invalid. Checkout COD is disabled until a valid replacement draft is published.</p>}
                    {error && <p role="alert" className="mt-3 rounded border border-red-300 p-3 text-sm">{error}</p>}
                    <form onSubmit={saveDraft} className="mt-4 flex flex-wrap items-center gap-3">
                        <label className="flex items-center gap-2 text-sm">
                            <input type="checkbox" checked={codEnabled} onChange={event => setCodEnabled(event.target.checked)} disabled={busy} />
                            Offer Cash on Delivery at checkout
                        </label>
                        <button type="submit" disabled={busy} className="rounded bg-slate-950 px-4 py-2 text-sm font-semibold text-white disabled:opacity-50">Save policy draft</button>
                    </form>
                    {settings.draft_invalid && <p role="alert" className="mt-3 rounded border border-amber-400 p-3 text-sm">The latest COD draft is invalid and cannot be published. Save a new draft to replace it.</p>}
                    {settings.draft && <div className="mt-4 rounded-xl border bg-slate-50 p-3 text-sm">
                        <p>Latest draft v{settings.draft.version}: COD {settings.draft.cod_enabled ? 'enabled' : 'disabled'} (not yet live).</p>
                        {settings.can_publish ? <button type="button" onClick={() => void publishDraft()} disabled={busy} className="mt-2 rounded border border-slate-950 px-4 py-2 font-semibold disabled:opacity-50">Publish latest COD draft</button>
                            : <p className="mt-2 text-slate-600">Publishing requires the separate Website publish permission.</p>}
                    </div>}
                    <p className="mt-3 text-xs text-slate-600">Turning COD off may leave checkout without an available channel while external gateways are unconfigured. Existing orders are not cancelled by changing this setting.</p>
                </section>
                <section className="rounded-2xl border bg-white p-5">
                    <h2 className="font-semibold">Payment labels, instructions and COD limits</h2>
                    <p className="mt-1 text-sm text-slate-600">Published version {presentation.published_version}. Drafts require separate publication. Changes apply to new orders only; existing orders and provider activation are unchanged.</p>
                    {presentation.published_invalid && <p role="alert" className="mt-2 text-sm text-red-700">Published presentation is invalid; checkout uses safe default labels and limits.</p>}
                    {presentationError && <p role="alert" className="mt-2 text-sm text-red-700">{presentationError}</p>}
                    <form onSubmit={savePresentation} className="mt-4 space-y-4">
                        {channels.map(channel => <fieldset key={channel.code} disabled={presentationBusy} className="grid gap-2 rounded-xl border p-3 sm:grid-cols-2">
                            <legend className="px-1 text-sm font-semibold">{channel.code.toUpperCase()}</legend>
                            <label className="grid gap-1 text-sm">Customer-facing name
                                <input required maxLength={80} value={presentationPolicy.channels[channel.code].label} onChange={event => updateChannel(channel.code, 'label', event.target.value)} className="rounded border p-2" />
                            </label>
                            <label className="grid gap-1 text-sm">Customer instructions
                                <textarea maxLength={500} rows={2} value={presentationPolicy.channels[channel.code].instructions} onChange={event => updateChannel(channel.code, 'instructions', event.target.value)} className="rounded border p-2" />
                            </label>
                        </fieldset>)}
                        <div className="grid gap-3 sm:grid-cols-2">
                            {(['cod_min_amount', 'cod_max_amount'] as const).map(field => <label key={field} className="grid gap-1 text-sm">{field === 'cod_min_amount' ? 'COD minimum (PKR)' : 'COD maximum (PKR)'}
                                <input type="number" min="0" max="999999999.99" step="0.01" placeholder="No limit" value={presentationPolicy[field] ?? ''} disabled={presentationBusy} onChange={event => setPresentationPolicy(previous => ({ ...previous, [field]: event.target.value === '' ? null : event.target.value }))} className="rounded border p-2" />
                            </label>)}
                        </div>
                        <button disabled={presentationBusy} type="submit" className="rounded bg-slate-950 px-4 py-2 text-sm font-semibold text-white disabled:opacity-50">Save presentation draft</button>
                    </form>
                    {presentation.draft_invalid && <p role="alert" className="mt-3 text-sm text-red-700">Latest presentation draft is invalid. Save a new draft to replace it.</p>}
                    {presentation.draft && <div className="mt-4 rounded-xl border bg-slate-50 p-3 text-sm">
                        <p>Draft v{presentation.draft.version} is not yet live.</p>
                        {settings.can_publish ? <button disabled={presentationBusy} type="button" onClick={() => void publishPresentation()} className="mt-2 rounded border border-slate-950 px-4 py-2 font-semibold disabled:opacity-50">Publish presentation draft</button>
                            : <p className="mt-2">Publishing requires the separate Website publish permission.</p>}
                    </div>}
                    <p className="mt-3 text-xs text-slate-600">External channels stay default-OFF without approved merchant integration. No credentials are accepted here.</p>
                </section>
                <section className="rounded-2xl border bg-white p-5">
                    <h2 className="font-semibold">Channel status · masked</h2>
                    <p className="mt-1 text-sm text-slate-600">JazzCash, Easypaisa and hosted card remain default-OFF without approved merchant onboarding and registered authentic adapters. This screen cannot register merchants, change credentials or confirm an external payment.</p>
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
                    <p className="mt-4 text-xs text-slate-600">Merchant identifiers and credentials are intentionally not displayed. Genuine provider activation and settlement verification remain pending.</p>
                </section>
            </div>
        </main>
    </>;
}
