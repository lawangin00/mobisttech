import { Head, Link } from '@inertiajs/react';

type RecordRow = { id: number; source: 'http' | 'identity'; actor_name: string | null; actor_email: string | null; action: string; method: string | null; path: string | null; status_code: number | null; created_at: string | null };
type Props = { records: RecordRow[]; current_page: number; last_page: number; total: number; filters: { q?: string; method?: string } };
export default function WebsiteAuditViewer({ records, current_page, last_page, total, filters }: Props) {
    const href = (page: number) => '/internal/admin/website-audit?' + new URLSearchParams({ ...filters, page: String(page) }).toString();
    return <><Head title="Website activity"/><main className="min-h-screen bg-slate-100 p-4 text-slate-950 sm:p-8"><div className="mx-auto max-w-6xl space-y-5">
        <header className="flex flex-wrap items-center justify-between gap-3"><div><h1 className="text-2xl font-semibold">Website activity</h1><p className="text-sm text-slate-600">Permissioned read-only Website and digital activity. Payloads and sensitive references are never displayed; recorded identity events have no HTTP method.</p></div><Link href="/internal/admin/platform" className="rounded border px-3 py-2 text-sm">Back to Platform</Link></header>
        <form method="GET" action="/internal/admin/website-audit" className="grid gap-3 rounded-xl border bg-white p-4 sm:grid-cols-3" data-testid="website-audit-filters">
            <label className="text-sm sm:col-span-2">Search actor, action or path<input name="q" type="search" maxLength={100} defaultValue={filters.q??''} className="mt-1 w-full rounded border p-2"/></label>
            <label className="text-sm">Recorded HTTP method<select name="method" defaultValue={filters.method??''} className="mt-1 w-full rounded border p-2"><option value="">All activity</option>{['GET','POST','PUT','PATCH','DELETE','SYSTEM'].map(method=><option key={method} value={method}>{method}</option>)}</select></label>
            <button type="submit" className="rounded bg-slate-950 px-4 py-2 text-sm text-white sm:col-span-3">Apply filters</button>
        </form>
        <section className="overflow-x-auto rounded-xl border bg-white p-4" data-testid="website-audit-records"><p data-testid="website-audit-count" className="mb-3 text-sm">{total} matching event(s)</p>
            <table className="w-full min-w-[720px] border-collapse text-left text-sm"><thead><tr className="border-b"><th className="p-2">Time</th><th className="p-2">Actor</th><th className="p-2">Action</th><th className="p-2">Method</th><th className="p-2">Path</th><th className="p-2">Status</th></tr></thead><tbody>{records.map(row=><tr key={row.source + row.id} data-testid="website-audit-row" className="border-b align-top"><td className="p-2 whitespace-nowrap">{row.created_at??'—'}</td><td className="p-2">{row.actor_name??'System'}<span className="block text-xs text-slate-500">{row.actor_email??''}</span></td><td className="p-2 break-all">{row.action}</td><td className="p-2">{row.method??'Not recorded'}</td><td className="p-2 break-all">{row.path??'Not recorded'}</td><td className="p-2">{row.status_code??'—'}</td></tr>)}</tbody></table>
            {records.length===0&&<p className="p-3 text-sm">No matching Website activity.</p>}
            <nav className="mt-4 flex items-center justify-between gap-3 text-sm"><span>Page {current_page} of {last_page}</span><span className="flex gap-3">{current_page>1&&<a href={href(current_page-1)} className="underline">Previous</a>}{current_page<last_page&&<a href={href(current_page+1)} className="underline">Next</a>}</span></nav>
        </section></div></main></>;
}
