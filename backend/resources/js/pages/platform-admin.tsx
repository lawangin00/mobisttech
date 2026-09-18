import { Head, Link } from '@inertiajs/react';
import { useEffect, useState } from 'react';

type ModeRevision = { id: number; version: number; state: string; mode: string | null; published_at: string | null; created_at: string };
type Policy = { policy_type: string; title: string; requirement: string; footer_destination: string | null; revision_id: number | null; version: number | null; state: string | null; effective_date: string | null };
type PolicyRevision = { id: number; policy_type: string; version: number; state: string; effective_date: string; approval_state: string; factual_review_state: string; published_at: string | null; created_at: string };
type Template = { template_id: string; template_key: string; version: number; document_type: string; channel: string; template_part: string; template_text: string };
type Software = { id: string; name: string; slug: string; status: string; archived_at: string | null; current_revision_id: number | null; current_revision_version: number | null; current_version: string | null; revisions: Array<{ id: number; version: number; state: string }>; releases: Array<{ id: number; public_id: string; version: string; state: string; release_date: string; summary: string }> };
type Data = {
    identity: { name: string; job_title: string | null }; permissions: string[]; outlet: { id: string; name: string } | null;
    website_mode: Record<string, unknown> & { mode?: string | null }; mode_revisions: ModeRevision[];
    policies: Policy[]; policy_history: PolicyRevision[]; templates: Template[]; software: Software[];
    team_members: Array<{ id: string; name: string; email: string; job_title: string | null; status: string; roles: Array<{ id: string; name: string }>; outlets: Array<{ id: string; name: string }> }>;
    roles: Array<{ id: string; name: string; system: boolean; protected: boolean; permissions: string[] }>;
    integrations: Array<{ provider: string; status: string; account?: string | null }>;
    payment_destinations: Array<{ destination_id?: string; public_id?: string; method: string; display_name: string; provider_label?: string | null; masked_identifier?: string | null; active?: boolean; version?: number }>;
    promotion: Record<string, unknown> | null; loyalty: Record<string, unknown> | null;
};

async function csrf() {
    const response = await fetch('/internal/admin/auth/csrf-cookie', { credentials: 'same-origin', headers: { Accept: 'application/json' } });
    const body = await response.json() as { data?: { csrf_token?: string } };
    if (!response.ok || !body.data?.csrf_token) throw new Error('Secure request token is unavailable.');
    return body.data.csrf_token;
}
async function api<T>(url: string, init?: RequestInit): Promise<T> {
    const method = init?.method ?? 'GET';
    const headers: Record<string, string> = { Accept: 'application/json' };
    if (method !== 'GET') {
        headers['Content-Type'] = 'application/json';
        headers['X-CSRF-TOKEN'] = await csrf();
        headers['Idempotency-Key'] = crypto.randomUUID();
    }
    const response = await fetch(url, { ...init, credentials: 'same-origin', headers });
    const body = await response.json().catch(() => ({})) as { data?: T; message?: string; errors?: Record<string, string[]> };
    if (!response.ok) throw new Error(body.errors ? Object.values(body.errors).flat()[0] : (body.message ?? 'Request failed.'));
    return body.data as T;
}

export default function PlatformAdmin({ identity }: { identity: { name: string; job_title: string | null } }) {
    const [data, setData] = useState<Data | null>(null);
    const [tab, setTab] = useState('website');
    const [busy, setBusy] = useState(false);
    const [message, setMessage] = useState('');
    const load = async () => setData(await api<Data>('/internal/admin/platform/data'));
    useEffect(() => { void load(); }, []);
    const run = async (task: () => Promise<void>, reload = true) => {
        setBusy(true); setMessage('');
        try { await task(); if (reload) await load(); }
        catch (error) { setMessage(error instanceof Error ? error.message : 'Request failed.'); }
        finally { setBusy(false); }
    };
    const allowed = (permission: string) => data?.permissions.includes(permission) ?? false;

    return <><Head title="Platform Administration" /><div className="min-h-screen bg-slate-50 text-slate-950">
        <header className="border-b bg-white"><div className="mx-auto flex max-w-[1500px] flex-wrap items-center justify-between gap-3 px-4 py-4 sm:px-6"><div><p className="text-xs font-semibold uppercase tracking-[0.2em] text-slate-500">mobiST Technologies</p><h1 className="text-2xl font-semibold">Platform Administration</h1><p className="text-sm text-slate-600">{identity.name} · {identity.job_title ?? 'Administrator'}</p></div><div className="flex gap-2"><Link href="/internal/admin/pos" className="rounded border px-3 py-2 text-sm">POS</Link><Link href="/internal/admin/settings/integrations" className="rounded border px-3 py-2 text-sm">Google integrations</Link></div></div></header>
        <main className="mx-auto max-w-[1500px] p-4 sm:p-6">
            {message && <p role="alert" className="mb-4 rounded-xl border border-red-200 bg-red-50 p-3 text-sm text-red-800">{message}</p>}
            <nav className="mb-5 flex max-w-full gap-2 overflow-auto pb-1">{['website','content','documents','team','software'].map((value) => <button key={value} onClick={() => setTab(value)} className={'whitespace-nowrap rounded-full border px-4 py-2 text-sm '+(tab===value?'bg-slate-950 text-white':'bg-white')}>{value === 'documents' ? 'Documents & payments' : value === 'team' ? 'Team & integrations' : value[0].toUpperCase()+value.slice(1)}</button>)}</nav>
            {!data ? <div className="rounded-2xl border bg-white p-5 text-sm">Loading administration data…</div> : <>
                {tab === 'website' && <WebsiteTab data={data} allowed={allowed} busy={busy} run={run} />}
                {tab === 'content' && <ContentTab data={data} allowed={allowed} busy={busy} run={run} />}
                {tab === 'documents' && <DocumentsTab data={data} allowed={allowed} busy={busy} run={run} />}
                {tab === 'team' && <TeamTab data={data} />}
                {tab === 'software' && <SoftwareTab data={data} allowed={allowed} busy={busy} run={run} />}
            </>}
        </main>
    </div></>;
}

function WebsiteTab({ data, allowed, busy, run }: { data: Data; allowed: (p:string)=>boolean; busy:boolean; run:(task:()=>Promise<void>,reload?:boolean)=>Promise<void> }) {
    const [mode, setMode] = useState('hybrid');
    const [preview, setPreview] = useState<Record<string, unknown> | null>(null);
    return <div className="grid gap-5 lg:grid-cols-2">
        <section className="rounded-2xl border bg-white p-5"><h2 className="font-semibold">Website operating mode</h2><p className="mt-1 text-sm text-slate-600">Current: <strong>{String(data.website_mode.mode ?? 'not published')}</strong>. Publication preserves historical data.</p>
            <div className="mt-3 flex flex-wrap gap-2"><select value={mode} onChange={(e)=>setMode(e.target.value)} className="rounded border p-2 text-sm"><option value="digital_only">Digital only</option><option value="hybrid">Hybrid</option><option value="commerce_only">Commerce only</option></select><button disabled={busy || !allowed('website.settings.manage')} onClick={()=>void run(async()=>{ await api('/internal/admin/platform/website-mode/draft',{method:'POST',body:JSON.stringify({mode})}); })} className="rounded border px-3 py-2 text-sm disabled:opacity-40">Save mode draft</button></div>
            <div className="mt-4 grid gap-2">{data.mode_revisions.map((row)=><div key={row.id} className="rounded border p-3 text-sm"><div className="flex flex-wrap items-center justify-between gap-2"><span>v{row.version} · <strong>{row.mode}</strong> · {row.state}</span><div className="flex flex-wrap gap-1"><button disabled={busy || !allowed('website.mode.preview')} onClick={()=>void run(async()=>setPreview(await api('/internal/admin/platform/website-mode/'+row.id+'/preview')),false)} className="rounded border px-2 py-1 text-xs">Preview impact</button>{row.state==='draft'&&<button disabled={busy || !allowed('website.mode.publish')} onClick={()=>void run(async()=>{await api('/internal/admin/platform/website-mode/'+row.id+'/publish',{method:'POST',body:'{}'});})} className="rounded border px-2 py-1 text-xs">Publish</button>}{['published','superseded'].includes(row.state)&&<button disabled={busy || !allowed('website.mode.publish')} onClick={()=>void run(async()=>{await api('/internal/admin/platform/website-mode/'+row.id+'/rollback',{method:'POST',body:'{}'});})} className="rounded border px-2 py-1 text-xs">Rollback</button>}</div></div></div>)}</div>
        </section>
        <section className="rounded-2xl border bg-white p-5"><h2 className="font-semibold">Mode preview / publish impact</h2>{preview?<pre className="mt-3 max-h-[520px] max-w-full overflow-auto rounded bg-slate-950 p-3 text-xs text-white">{JSON.stringify(preview,null,2)}</pre>:<p className="mt-2 text-sm text-slate-500">Select a revision and preview its capabilities, routes, SEO/sitemap and visibility impact before publishing.</p>}</section>
    </div>;
}

function ContentTab({ data, allowed, busy, run }: { data: Data; allowed:(p:string)=>boolean; busy:boolean; run:(task:()=>Promise<void>,reload?:boolean)=>Promise<void> }) {
    const [type,setType]=useState('privacy'); const [content,setContent]=useState(''); const [effective,setEffective]=useState(new Date().toISOString().slice(0,10));
    return <div className="grid gap-5 lg:grid-cols-2"><section className="rounded-2xl border bg-white p-5"><h2 className="font-semibold">Legal & policy content</h2><p className="mt-1 text-sm text-slate-600">Drafting and publication remain revisioned; publish/rollback requires the existing recent-auth boundary.</p><div className="mt-3 grid gap-2"><select value={type} onChange={(e)=>setType(e.target.value)} className="rounded border p-2 text-sm">{['privacy','terms','returns_refunds','shipping_delivery','warranty','cookie','digital_services_terms','payment_disclosures'].map(x=><option key={x} value={x}>{x.replaceAll('_',' ')}</option>)}</select><input type="date" value={effective} onChange={(e)=>setEffective(e.target.value)} className="rounded border p-2 text-sm"/><textarea value={content} onChange={(e)=>setContent(e.target.value)} rows={8} placeholder="Typed reviewed policy content" className="rounded border p-2 text-sm"/><button disabled={busy || !allowed('website.content.manage') || !content} onClick={()=>void run(async()=>{await api('/internal/admin/platform/policies/'+type+'/draft',{method:'POST',body:JSON.stringify({content,effective_date:effective,approval_state:'owner_approved',factual_review_state:'verified',unresolved_decisions:[]})});setContent('');})} className="rounded bg-slate-950 px-3 py-2 text-sm font-semibold text-white disabled:opacity-40">Save reviewed draft</button></div></section>
        <section className="rounded-2xl border bg-white p-5"><h2 className="font-semibold">Policy revisions</h2><div className="mt-3 grid max-h-[560px] gap-2 overflow-auto">{data.policy_history.map((row)=><div key={row.id} className="rounded border p-3 text-sm"><div>{row.policy_type} v{row.version} · <strong>{row.state}</strong> · {row.effective_date}</div><div className="mt-2 flex gap-2">{row.state==='draft'&&<button disabled={busy || !allowed('website.publish')} onClick={()=>void run(async()=>{await api('/internal/admin/platform/policies/'+row.id+'/publish',{method:'POST',body:'{}'});})} className="rounded border px-2 py-1 text-xs">Publish</button>}{['published','superseded'].includes(row.state)&&<button disabled={busy || !allowed('website.publish')} onClick={()=>void run(async()=>{await api('/internal/admin/platform/policies/'+row.id+'/rollback',{method:'POST',body:'{}'});})} className="rounded border px-2 py-1 text-xs">Rollback</button>}</div></div>)}</div></section></div>;
}

function DocumentsTab({ data, allowed, busy, run }: { data: Data; allowed:(p:string)=>boolean; busy:boolean; run:(task:()=>Promise<void>,reload?:boolean)=>Promise<void> }) {
    const [edits,setEdits]=useState<Record<string,string>>({});
    const [method,setMethod]=useState('bank_transfer'); const [name,setName]=useState(''); const [masked,setMasked]=useState('');
    return <div className="grid gap-5 lg:grid-cols-2"><section className="rounded-2xl border bg-white p-5"><h2 className="font-semibold">Invoice/Warranty communication templates</h2><div className="mt-3 grid gap-3">{data.templates.map((row)=><div key={row.template_key} className="rounded border p-3"><div className="text-xs font-semibold">{row.template_key} · v{row.version}</div><textarea value={edits[row.template_key] ?? row.template_text} onChange={(e)=>setEdits({...edits,[row.template_key]:e.target.value})} rows={row.template_part==='subject'?2:4} className="mt-2 w-full rounded border p-2 text-sm"/><button disabled={busy || !allowed('config.documents.manage')} onClick={()=>void run(async()=>{await api('/internal/admin/platform/templates/'+row.template_key,{method:'POST',body:JSON.stringify({text:edits[row.template_key] ?? row.template_text})});})} className="mt-2 rounded border px-2 py-1 text-xs disabled:opacity-40">Save new revision</button></div>)}</div></section>
        <section className="rounded-2xl border bg-white p-5"><h2 className="font-semibold">POS Payment Destinations</h2><p className="mt-1 text-sm text-slate-600">Only masked identifiers are shown. Provider credentials are never exposed here.</p>{data.outlet?<><div className="mt-3 grid gap-2 sm:grid-cols-3"><select value={method} onChange={(e)=>setMethod(e.target.value)} className="rounded border p-2 text-sm"><option value="bank_transfer">Bank transfer</option><option value="card">Card</option><option value="wallet">Wallet</option></select><input value={name} onChange={(e)=>setName(e.target.value)} placeholder="Display name" className="rounded border p-2 text-sm"/><input value={masked} onChange={(e)=>setMasked(e.target.value)} placeholder="Masked identifier" className="rounded border p-2 text-sm"/></div><button disabled={busy || !allowed('config.payments.manage') || !name} onClick={()=>void run(async()=>{await api('/internal/admin/platform/payment-destinations',{method:'POST',body:JSON.stringify({method,display_name:name,provider_label:null,masked_identifier:masked||null,active:true})});setName('');setMasked('');})} className="mt-2 rounded bg-slate-950 px-3 py-2 text-sm text-white disabled:opacity-40">Add destination</button></>:<p className="mt-3 rounded bg-amber-50 p-3 text-sm">Choose an active POS outlet first to manage its destinations.</p>}<div className="mt-4 grid gap-2">{data.payment_destinations.map((row,index)=><div key={row.destination_id ?? row.public_id ?? index} className="rounded border p-3 text-sm">{row.display_name} · {row.method} · {row.masked_identifier ?? 'no public identifier'} · {row.active===false?'inactive':'active'}</div>)}</div></section></div>;
}

function TeamTab({ data }: { data: Data }) {
    return <div className="grid gap-5 lg:grid-cols-2"><section className="rounded-2xl border bg-white p-5"><div className="flex items-center justify-between"><h2 className="font-semibold">Team Members</h2><span className="text-xs text-slate-500">Existing delegated authority</span></div><div className="mt-3 grid gap-2">{data.team_members.map((m)=><div key={m.id} className="rounded border p-3 text-sm"><strong>{m.name}</strong> · {m.status}<div className="text-xs text-slate-500">{m.email} · {m.roles.map(r=>r.name).join(', ') || 'no role'}</div></div>)}</div></section><section className="rounded-2xl border bg-white p-5"><h2 className="font-semibold">Custom Roles & integrations</h2><div className="mt-3 grid gap-2">{data.roles.map((r)=><div key={r.id} className="rounded border p-3 text-sm"><strong>{r.name}</strong>{r.protected?' · protected':''}<div className="mt-1 text-xs text-slate-500">{r.permissions.join(', ')}</div></div>)}</div><div className="mt-4 rounded bg-slate-50 p-3 text-sm"><strong>Google / integration status</strong>{data.integrations.map((i)=><div key={i.provider} className="mt-1">{i.provider}: {i.status}{i.account?' · '+i.account:''}</div>)}<Link href="/internal/admin/settings/integrations" className="mt-3 inline-block rounded border bg-white px-3 py-2 text-sm">Open integration management</Link></div><p className="mt-3 text-xs text-slate-500">Create/update Team Members and Custom Roles continue through the existing recent-auth protected APIs; protected Full Access and delegation ceilings remain backend-enforced.</p></section></div>;
}

function SoftwareTab({ data, allowed, busy, run }: { data: Data; allowed:(p:string)=>boolean; busy:boolean; run:(task:()=>Promise<void>,reload?:boolean)=>Promise<void> }) {
    const [name,setName]=useState(''); const [slug,setSlug]=useState(''); const [summary,setSummary]=useState(''); const [overview,setOverview]=useState('');
    const create=async()=>{await api('/internal/admin/platform/software',{method:'POST',body:JSON.stringify({name,slug,summary,overview,features:[{title:'Core capability',description:'Describe the verified product capability.'}],platforms:['Windows 10 x64','Windows 11 x64'],system_requirements:'<p>Supported platform requirements.</p>',limitations:[],support:{channel:'support'},cta:{type:'contact'},privacy:'<p>Review product-specific privacy content.</p>',terms:'<p>Review product-specific terms.</p>',faq:[{question:'What does this product provide?',answer:'<p>Review the product FAQ answer.</p>'}],seo_title:name,seo_description:summary,sitemap:true})});setName('');setSlug('');setSummary('');setOverview('');};
    return <div className="grid gap-5"><section className="rounded-2xl border bg-white p-5"><h2 className="font-semibold">Software administration</h2><p className="mt-1 text-sm text-slate-600">New Software uses the reusable backend template; it never creates copied hard-coded routes.</p><div className="mt-3 grid gap-2 sm:grid-cols-2"><input value={name} onChange={(e)=>setName(e.target.value)} placeholder="Product name" className="rounded border p-2 text-sm"/><input value={slug} onChange={(e)=>setSlug(e.target.value)} placeholder="product-slug" className="rounded border p-2 text-sm"/><input value={summary} onChange={(e)=>setSummary(e.target.value)} placeholder="Customer-facing summary" className="rounded border p-2 text-sm sm:col-span-2"/><textarea value={overview} onChange={(e)=>setOverview(e.target.value)} placeholder="Overview" rows={4} className="rounded border p-2 text-sm sm:col-span-2"/></div><button disabled={busy || !allowed('website.content.manage') || !name || !slug || !summary || !overview} onClick={()=>void run(create)} className="mt-2 rounded bg-slate-950 px-3 py-2 text-sm font-semibold text-white disabled:opacity-40">New Software draft</button></section>
        <section className="rounded-2xl border bg-white p-5"><div className="grid gap-3">{data.software.map((s)=><div key={s.id} className="rounded-xl border p-4"><div className="flex flex-wrap items-start justify-between gap-2"><div><strong>{s.name}</strong><div className="text-xs text-slate-500">/{s.slug} · {s.status} · current version {s.current_version ?? 'none'}</div></div><div className="flex flex-wrap gap-1">{s.revisions.filter(r=>r.state==='draft').map(r=><button key={'p'+r.id} disabled={busy || !allowed('website.publish')} onClick={()=>void run(async()=>{await api('/internal/admin/platform/software/revisions/'+r.id+'/publish',{method:'POST',body:'{}'});})} className="rounded border px-2 py-1 text-xs">Publish draft v{r.version}</button>)}{s.revisions.filter(r=>['published','superseded'].includes(r.state)).slice(0,2).map(r=><button key={'r'+r.id} disabled={busy || !allowed('website.publish')} onClick={()=>void run(async()=>{await api('/internal/admin/platform/software/revisions/'+r.id+'/rollback',{method:'POST',body:'{}'});})} className="rounded border px-2 py-1 text-xs">Rollback to v{r.version}</button>)}{!s.archived_at&&<button disabled={busy || !allowed('website.content.manage')} onClick={()=>void run(async()=>{await api('/internal/admin/platform/software/'+s.id+'/archive',{method:'POST',body:'{}'});})} className="rounded border px-2 py-1 text-xs">Archive</button>}</div></div><div className="mt-3 text-xs text-slate-600">Revisions: {s.revisions.map(r=>'v'+r.version+' '+r.state).join(' · ') || 'none'}<br/>Releases: {s.releases.map(r=>r.version+' '+r.state).join(' · ') || 'none'}</div></div>)}</div></section>
    </div>;
}
