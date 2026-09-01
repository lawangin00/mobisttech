import { Head, router } from '@inertiajs/react';
import { useState } from 'react';

type Integration = { key: 'gmail' | 'google_drive'; provider: string; status: string; account: string | null; remote: string | null; backup_destination: string | null; last_success_at: string | null; last_error_summary: string | null; backup_schedule: string; retention_policy: string; last_backup_status: string | null; last_backup_size: number | null; last_backup_at: string | null };

export default function Integrations({ integrations }: { integrations: Integration[] }) {
    const [busy, setBusy] = useState<string | null>(null);
    const [message, setMessage] = useState<string | null>(null);

    async function csrf(): Promise<string> {
        await fetch('/internal/admin/auth/csrf-cookie', { credentials: 'same-origin', headers: { Accept: 'application/json' } });
        const value = document.cookie.split('; ').find((item) => item.startsWith('XSRF-TOKEN-admin='))?.split('=')[1];
        if (!value) throw new Error('Secure request token is unavailable.');
        return decodeURIComponent(value);
    }

    async function action(item: Integration, operation: 'connect' | 'test' | 'disconnect') {
        setBusy(`${item.key}:${operation}`); setMessage(null);
        try {
            const token = await csrf();
            const url = operation === 'disconnect' ? `/internal/admin/integrations/${item.key}` : `/internal/admin/integrations/${item.key}/${operation}`;
            const response = await fetch(url, { method: operation === 'disconnect' ? 'DELETE' : 'POST', credentials: 'same-origin',
                headers: { Accept: 'application/json', 'Content-Type': 'application/json', 'X-XSRF-TOKEN': token }, body: '{}' });
            const body = await response.json();
            if (!response.ok) throw new Error(body?.error?.message ?? 'The integration request failed.');
            const authorizationUrl = body?.data?.authorization_url;
            if (typeof authorizationUrl === 'string') { window.location.assign(authorizationUrl); return; }
            setMessage(`${item.provider} updated successfully.`); router.reload({ only: ['integrations'] });
        } catch (error) { setMessage(error instanceof Error ? error.message : 'The integration request failed.'); }
        finally { setBusy(null); }
    }

    async function backupNow(item: Integration) {
        setBusy(`${item.key}:backup`); setMessage(null);
        try {
            const token = await csrf();
            const response = await fetch('/internal/admin/integrations/google_drive/backup-now', { method: 'POST', credentials: 'same-origin',
                headers: { Accept: 'application/json', 'Content-Type': 'application/json', 'X-XSRF-TOKEN': token }, body: '{}' });
            if (!response.ok) throw new Error('Backup could not be queued.');
            setMessage('Backup queued through the Laravel backend.'); router.reload({ only: ['integrations'] });
        } catch (error) { setMessage(error instanceof Error ? error.message : 'Backup could not be queued.'); }
        finally { setBusy(null); }
    }

    async function backupSettings(item: Integration, schedule: 'disabled' | 'daily' | 'weekly') {
        setBusy(`${item.key}:settings`); setMessage(null);
        try {
            const token = await csrf();
            const response = await fetch('/internal/admin/integrations/google_drive/backup-settings', { method: 'PUT', credentials: 'same-origin',
                headers: { Accept: 'application/json', 'Content-Type': 'application/json', 'X-XSRF-TOKEN': token }, body: JSON.stringify({ schedule, retention_days: 30 }) });
            if (!response.ok) throw new Error('Backup settings could not be saved.');
            setMessage(`Backup schedule set to ${schedule}; retention is 30 days.`); router.reload({ only: ['integrations'] });
        } catch (error) { setMessage(error instanceof Error ? error.message : 'Backup settings could not be saved.'); }
        finally { setBusy(null); }
    }

    return <><Head title="Admin integrations" /><main className="mx-auto min-h-screen max-w-4xl px-6 py-12">
        <p className="text-sm font-semibold uppercase tracking-widest text-slate-500">Admin · Settings · Integrations</p>
        <h1 className="mt-3 text-4xl font-semibold text-slate-950">Google integrations</h1>
        <p className="mt-4 text-slate-600">Connect approved services through backend-controlled OAuth. Credentials are never shown here.</p>
        {message && <p role="status" className="mt-6 rounded-lg border border-slate-200 bg-white p-3 text-sm">{message}</p>}
        <div className="mt-8 grid gap-5 md:grid-cols-2">{integrations.map((item) => <section key={item.key} className="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
            <h2 className="text-xl font-semibold">{item.provider}</h2><p className="mt-2">Status: <strong>{item.status === 'connected' ? 'Connected' : item.status === 'error' ? 'Error' : 'Not Connected'}</strong></p>
            {item.account && <p className="mt-1 text-sm text-slate-600">Account: {item.account}</p>}{item.remote && <p className="mt-1 text-sm text-slate-600">Remote: {item.remote}</p>}
            {item.backup_destination && <p className="mt-1 text-sm text-slate-600">Destination: {item.backup_destination}</p>}{item.last_success_at && <p className="mt-1 text-sm text-slate-600">Last success: {item.last_success_at}</p>}
            {item.key === 'google_drive' && <><p className="mt-1 text-sm text-slate-600">Schedule: {item.backup_schedule}</p><p className="mt-1 text-sm text-slate-600">Retention: {item.retention_policy}</p>{item.last_backup_status && <p className="mt-1 text-sm text-slate-600">Last backup: {item.last_backup_status} · {item.last_backup_size ?? 0} bytes</p>}<div className="mt-3 flex gap-2"><button onClick={() => backupSettings(item, 'daily')} className="text-xs underline">Daily</button><button onClick={() => backupSettings(item, 'weekly')} className="text-xs underline">Weekly</button><button onClick={() => backupSettings(item, 'disabled')} className="text-xs underline">Disable schedule</button></div></>}
            {item.last_error_summary && <p className="mt-2 text-sm text-red-700">{item.last_error_summary}</p>}
            <div className="mt-5 flex flex-wrap gap-2"><button disabled={busy !== null} onClick={() => action(item, 'connect')} className="rounded-lg bg-slate-950 px-4 py-2 text-sm text-white">{item.status === 'connected' ? 'Reconnect' : `Connect ${item.provider}`}</button>
                {item.status === 'connected' && <><button disabled={busy !== null} onClick={() => action(item, 'test')} className="rounded-lg border border-slate-300 px-4 py-2 text-sm">Test</button>{item.key === 'google_drive' && <button disabled={busy !== null} onClick={() => backupNow(item)} className="rounded-lg border border-slate-300 px-4 py-2 text-sm">Backup Now</button>}<button disabled={busy !== null} onClick={() => action(item, 'disconnect')} className="rounded-lg border border-red-300 px-4 py-2 text-sm text-red-700">Disconnect</button></>}
            </div></section>)}</div>
    </main></>;
}
