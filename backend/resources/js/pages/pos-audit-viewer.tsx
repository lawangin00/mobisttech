import {Head, Link} from '@inertiajs/react';

type RecordRow={id:number;actor_type:string;actor_name:string|null;actor_email:string|null;action:string;method:string;path:string;status_code:number|null;created_at:string|null;outlet_name:string|null;outlet_code:string|null};
type Outlet={id:string;outlet_code:string;name:string};
type Filters={q?:string;actor_type?:string;method?:string;outlet?:string};
type Props={records:RecordRow[];current_page:number;last_page:number;total:number;filters:Filters;outlets:Outlet[]};
export default function PosAuditViewer({records,current_page,last_page,total,filters,outlets}:Props){
    const href=(page:number)=>'/internal/admin/pos/audit?'+new URLSearchParams({...filters,page:String(page)}).toString();
    return <><Head title="POS audit"/><main className="min-h-screen bg-slate-100 p-5 text-slate-950 sm:p-8"><div className="mx-auto max-w-6xl space-y-5">
        <header className="flex flex-wrap items-center justify-between gap-3"><div><p className="text-xs uppercase tracking-widest text-slate-500">mobiST POS</p><h1 className="text-2xl font-semibold">POS audit</h1><p className="text-sm text-slate-600">Protected read-only activity history. Raw request payloads and device details are never displayed.</p></div><Link href="/internal/admin/pos" className="rounded border px-3 py-2 text-sm">Back to POS</Link></header>
        <form method="GET" action="/internal/admin/pos/audit" className="grid gap-3 rounded-xl border bg-white p-4 sm:grid-cols-5" data-testid="audit-filters">
            <label className="text-sm sm:col-span-2">Search actor, action or path<input name="q" type="search" maxLength={100} defaultValue={filters.q??''} data-testid="audit-search" className="mt-1 w-full rounded border p-2"/></label>
            <label className="text-sm">Actor type<input name="actor_type" maxLength={40} defaultValue={filters.actor_type??''} className="mt-1 w-full rounded border p-2"/></label>
            <label className="text-sm">Method<select name="method" defaultValue={filters.method??''} className="mt-1 w-full rounded border p-2"><option value="">All methods</option>{['GET','POST','PUT','PATCH','DELETE','SYSTEM'].map(method=><option key={method} value={method}>{method}</option>)}</select></label>
            <label className="text-sm">Outlet<select name="outlet" defaultValue={filters.outlet??''} data-testid="audit-outlet" className="mt-1 w-full rounded border p-2"><option value="">All outlets</option>{outlets.map(outlet=><option key={outlet.id} value={outlet.id}>{outlet.outlet_code} · {outlet.name}</option>)}</select></label>
            <button className="rounded bg-slate-950 px-4 py-2 text-sm font-medium text-white sm:col-span-5" type="submit" data-testid="audit-filter-submit">Apply filters</button>
        </form>
        <section className="overflow-x-auto rounded-xl border bg-white p-4"><p className="mb-3 text-sm text-slate-600" data-testid="audit-count">{total} matching event(s)</p>
            <table className="w-full min-w-[840px] border-collapse text-left text-sm"><thead><tr className="border-b text-slate-600"><th className="p-2">Time</th><th className="p-2">Actor</th><th className="p-2">Outlet</th><th className="p-2">Action</th><th className="p-2">Method</th><th className="p-2">Path</th><th className="p-2">Status</th></tr></thead>
                <tbody>{records.map(row=><tr key={row.id} data-testid="audit-row" className="border-b align-top"><td className="p-2 whitespace-nowrap">{row.created_at??'—'}</td><td className="p-2">{row.actor_name??row.actor_type}<span className="block text-xs text-slate-500">{row.actor_email??row.actor_type}</span></td><td className="p-2">{row.outlet_code??'—'} {row.outlet_name??''}</td><td className="p-2 break-all">{row.action}</td><td className="p-2">{row.method}</td><td className="p-2 break-all">{row.path}</td><td className="p-2">{row.status_code??'—'}</td></tr>)}</tbody>
            </table>{records.length===0&&<p className="p-4 text-sm" data-testid="audit-empty">No matching POS audit events.</p>}
            <nav aria-label="Audit pages" className="mt-4 flex items-center justify-between gap-3 text-sm"><span data-testid="audit-page">Page {current_page} of {last_page}</span><span className="flex gap-3">{current_page>1&&<a href={href(current_page-1)} className="underline">Previous</a>}{current_page<last_page&&<a href={href(current_page+1)} className="underline">Next</a>}</span></nav>
        </section></div></main></>;
}
