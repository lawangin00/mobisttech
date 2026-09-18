import { Head, Link } from '@inertiajs/react';
import { useEffect, useMemo, useState } from 'react';

type Level = {
    label:string; description:string; permission:string; can_execute:boolean;
    confirmation_required:string; available_domains:string[]; factory_scope_locked:boolean;
};
type Backup = {
    record_id:number; status:string|null; filename:string|null; size_bytes:number|null; checksum:string|null;
    completed_at:string|null; manifest_verified_at:string|null;
    restore_rehearsal:{status:string|null;failure_code:string|null;checked_at:string|null};
};
type Operation = {
    public_id:string; level:string; domains:string[]; record_count:number; file_count:number; status:string;
    failure_code:string|null; started_at:string|null; completed_at:string|null; result:Record<string,unknown>|null;
    created_at:string; backup:Backup|null;
};
type AuditRow = {id:number;actor_name:string|null;actor_email:string|null;action:string;payload:Record<string,unknown>;status_code:number;created_at:string};
type Data = {
    identity:{name:string;job_title:string|null}; levels:Record<string,Level>; domain_labels:Record<string,string>;
    recent_authentication:boolean; preview_ttl_minutes:number; execution_enabled_here:boolean; production_hold:boolean;
    minimum_bootstrap:{access_tables:string[];recovery_evidence_tables:string[];note:string};
    operations:Operation[]; audit:AuditRow[];
};
type Preview = {
    public_id:string; level:string; domains:string[]; domain_counts:Record<string,{tables:string[];record_count:number;file_count:number}>;
    record_count:number; file_count:number; barriers:Array<Record<string,unknown>>;
    preservation:Array<{group:string;action:string;tables:string[]}>;
    preview_sha256:string; confirmation_required:string; execution_enabled_here:boolean;
};

async function csrf(){
    const response=await fetch('/internal/admin/auth/csrf-cookie',{credentials:'same-origin',headers:{Accept:'application/json'}});
    const body=await response.json() as {data?:{csrf_token?:string}};
    if(!response.ok||!body.data?.csrf_token) throw new Error('Secure request token is unavailable.');
    return body.data.csrf_token;
}
async function api<T>(url:string,init?:RequestInit):Promise<T>{
    const method=init?.method??'GET'; const headers:Record<string,string>={Accept:'application/json'};
    if(method!=='GET'){headers['Content-Type']='application/json';headers['X-CSRF-TOKEN']=await csrf();}
    const response=await fetch(url,{...init,credentials:'same-origin',headers});
    const body=await response.json().catch(()=>({})) as {data?:T;message?:string;errors?:Record<string,string[]>};
    if(!response.ok) throw new Error(body.errors?Object.values(body.errors).flat()[0]:(body.message??'Request failed.'));
    return body.data as T;
}
function bytes(value:number|null){if(value===null)return '—';return value<1024?value+' B':value<1048576?Math.round(value/1024)+' KB':(value/1048576).toFixed(1)+' MB';}
function title(value:string){return value.replaceAll('_',' ').replace(/w/g,c=>c.toUpperCase());}

export default function ResetAdministration({identity}:{identity:{name:string;job_title:string|null}}){
    const [data,setData]=useState<Data|null>(null);
    const [level,setLevel]=useState('transactional');
    const [domains,setDomains]=useState<string[]>([]);
    const [preview,setPreview]=useState<Preview|null>(null);
    const [confirmation,setConfirmation]=useState('');
    const [password,setPassword]=useState('');
    const [busy,setBusy]=useState(false);
    const [message,setMessage]=useState('');
    const load=async()=>setData(await api<Data>('/internal/admin/reset-administration/data'));
    useEffect(()=>{void load();},[]);
    const current=data?.levels[level];
    const chooseLevel=(key:string,row:Level)=>{setLevel(key);setDomains(row.factory_scope_locked?[...row.available_domains]:[]);setPreview(null);setConfirmation('');};
    const toggle=(domain:string)=>setDomains(old=>old.includes(domain)?old.filter(x=>x!==domain):[...old,domain].sort());
    const run=async(task:()=>Promise<void>,reload=true)=>{setBusy(true);setMessage('');try{await task();if(reload)await load();}catch(error){setMessage(error instanceof Error?error.message:'Request failed.');}finally{setBusy(false);}};
    const previewReset=()=>run(async()=>{const value=await api<Preview>('/internal/admin/reset-administration/preview',{method:'POST',body:JSON.stringify({level,domains})});setPreview(value);setConfirmation('');},false);
    const execute=()=>preview&&run(async()=>{await api('/internal/admin/reset-administration/operations/'+preview.public_id+'/execute',{method:'POST',body:JSON.stringify({confirmation})});setPreview(null);setConfirmation('');},true);
    const canExecute=Boolean(preview&&current?.can_execute&&data?.recent_authentication&&preview.barriers.length===0&&preview.execution_enabled_here&&confirmation.trim()===preview.confirmation_required);
    const selectedCount=useMemo(()=>domains.length,[domains]);

    return <><Head title="Data Reset Administration"/><div className="min-h-screen bg-slate-50 text-slate-950">
        <header className="border-b bg-white"><div className="mx-auto flex max-w-[1500px] flex-wrap items-center justify-between gap-3 px-4 py-4 sm:px-6"><div><p className="text-xs font-semibold uppercase tracking-[0.2em] text-red-600">Destructive Administration</p><h1 className="text-2xl font-semibold">Data Reset Administration</h1><p className="text-sm text-slate-600">{identity.name} · {identity.job_title??'Administrator'}</p></div><div className="flex flex-wrap gap-2"><Link href="/internal/admin/platform" className="rounded border px-3 py-2 text-sm">Platform Administration</Link><Link href="/internal/admin/pos" className="rounded border px-3 py-2 text-sm">POS</Link></div></div></header>
        <main className="mx-auto max-w-[1500px] p-4 sm:p-6">{message&&<p role="alert" className="mb-4 rounded-xl border border-red-200 bg-red-50 p-3 text-sm text-red-800">{message}</p>}
        {!data?<div className="rounded-2xl border bg-white p-5 text-sm">Loading reset controls…</div>:<>
            <section className={'mb-5 rounded-2xl border p-4 '+(data.production_hold?'border-amber-300 bg-amber-50':'bg-white')}><h2 className="font-semibold">Production reset HOLD</h2><p className="mt-1 text-sm">{data.production_hold?'Production destructive reset is not authorized by reset configuration. Preview/history remain available; execution is accepted only in approved disposable acceptance environments.':'Production execution policy requires separate explicit authorization.'}</p><p className="mt-2 text-xs">Current environment execution: <strong>{data.execution_enabled_here?'enabled for disposable acceptance':'disabled'}</strong> · Preview TTL: {data.preview_ttl_minutes} minutes</p></section>

            <div className="grid gap-5 xl:grid-cols-[1.15fr_0.85fr]">
                <section className="rounded-2xl border bg-white p-5"><h2 className="font-semibold">1. Choose reset level</h2><div className="mt-3 grid gap-3 lg:grid-cols-3">{Object.entries(data.levels).map(([key,row])=><button key={key} onClick={()=>chooseLevel(key,row)} className={'rounded-xl border p-4 text-left '+(level===key?'border-slate-950 bg-slate-50':'')}><div className="font-semibold">{row.label}</div><p className="mt-1 text-xs text-slate-600">{row.description}</p><p className="mt-2 text-xs">Execute grant: {row.can_execute?'yes':'no'}</p></button>)}</div>
                <h2 className="mt-5 font-semibold">2. Select domains</h2><p className="mt-1 text-sm text-slate-600">{current?.factory_scope_locked?'Factory reset requires the complete approved factory scope; domain selection is locked.':'Choose only the approved domains required for this reset.'}</p><div className="mt-3 grid gap-2 sm:grid-cols-2 lg:grid-cols-3">{current?.available_domains.map(domain=><label key={domain} className="flex items-center gap-2 rounded border p-3 text-sm"><input type="checkbox" checked={domains.includes(domain)} disabled={current.factory_scope_locked} onChange={()=>toggle(domain)}/><span>{data.domain_labels[domain]??title(domain)}</span></label>)}</div><button disabled={busy||!data.recent_authentication||selectedCount===0} onClick={()=>void previewReset()} className="mt-4 rounded bg-slate-950 px-4 py-2 text-sm text-white disabled:opacity-40">Create dry-run preview</button></section>

                <section className="rounded-2xl border bg-white p-5"><h2 className="font-semibold">Recent re-authentication</h2><p className="mt-1 text-sm">Status: <strong>{data.recent_authentication?'confirmed':'required before preview/execution'}</strong></p><div className="mt-3 flex gap-2"><input type="password" value={password} onChange={e=>setPassword(e.target.value)} placeholder="Current password" className="min-w-0 flex-1 rounded border p-2 text-sm"/><button disabled={busy||!password} onClick={()=>void run(async()=>{await api('/internal/admin/auth/confirm-password',{method:'POST',body:JSON.stringify({password})});setPassword('');})} className="rounded border px-3 py-2 text-sm">Confirm password</button></div>
                <h2 className="mt-6 font-semibold">Minimum bootstrap preserved</h2><p className="mt-1 text-xs text-slate-600">{data.minimum_bootstrap.note}</p><div className="mt-3 text-xs"><strong>Access:</strong> {data.minimum_bootstrap.access_tables.join(', ')}</div><details className="mt-3"><summary className="cursor-pointer text-xs font-semibold">Protected recovery/audit evidence</summary><p className="mt-2 text-xs text-slate-600">{data.minimum_bootstrap.recovery_evidence_tables.join(', ')}</p></details></section>
            </div>

            {preview&&<section className="mt-5 rounded-2xl border-2 border-red-300 bg-white p-5"><div className="flex flex-wrap items-center justify-between gap-3"><div><h2 className="font-semibold">3. Dry-run preview · {title(preview.level)}</h2><p className="text-sm">{preview.record_count} records · {preview.file_count} private files · {preview.domains.length} domains</p></div><button onClick={()=>{setPreview(null);setConfirmation('');setMessage('Preview cancelled locally. No reset execution request was sent.');}} className="rounded border px-3 py-2 text-sm">Cancel preview</button></div>
            <div className="mt-4 grid gap-4 lg:grid-cols-2"><div><h3 className="text-sm font-semibold">Affected domain counts</h3><div className="mt-2 grid gap-2">{Object.entries(preview.domain_counts).map(([domain,row])=><div key={domain} className="rounded border p-3 text-sm"><strong>{data.domain_labels[domain]??domain}</strong><div>{row.record_count} records · {row.file_count} files</div><div className="mt-1 text-xs text-slate-500">{row.tables.join(', ')}</div></div>)}</div></div><div><h3 className="text-sm font-semibold">Preservation matrix</h3><div className="mt-2 max-h-96 overflow-auto rounded border">{preview.preservation.map((row,index)=><div key={index} className="border-b p-3 text-xs last:border-0"><strong>{row.group}</strong> · {row.action}<div className="mt-1 text-slate-500">{row.tables.join(', ')}</div></div>)}</div></div></div>
            {preview.barriers.length>0&&<div className="mt-4 rounded border border-red-300 bg-red-50 p-3"><strong className="text-sm">Execution blocked by {preview.barriers.length} unresolved barrier(s)</strong><pre className="mt-2 max-h-64 overflow-auto text-xs">{JSON.stringify(preview.barriers,null,2)}</pre></div>}
            <div className="mt-5 rounded-xl border border-red-300 p-4"><h3 className="font-semibold">4. Typed destructive confirmation</h3><p className="mt-1 text-sm">Type exactly: <code className="rounded bg-slate-100 px-1">{preview.confirmation_required}</code></p><input value={confirmation} onChange={e=>setConfirmation(e.target.value)} className="mt-2 w-full rounded border p-2 text-sm" placeholder={preview.confirmation_required}/><button disabled={busy||!canExecute} onClick={()=>void execute()} className="mt-3 rounded bg-red-700 px-4 py-2 text-sm text-white disabled:opacity-40">{data.execution_enabled_here?'Execute verified reset':'Execution HOLD in this environment'}</button></div></section>}

            <section className="mt-5 rounded-2xl border bg-white p-5"><h2 className="font-semibold">Reset outcomes & verified-backup evidence</h2><div className="mt-3 grid gap-3">{data.operations.length===0&&<p className="text-sm text-slate-500">No reset operations recorded.</p>}{data.operations.map(op=><div key={op.public_id} className="rounded border p-4 text-sm"><div className="flex flex-wrap items-center justify-between gap-2"><div><strong>{title(op.level)}</strong> · {op.status} · {op.record_count} records / {op.file_count} files</div><span className="text-xs text-slate-500">{op.created_at}</span></div><div className="mt-1 text-xs">Domains: {op.domains.join(', ')}</div>{op.failure_code&&<div className="mt-2 text-xs text-red-700">Failure: {op.failure_code}</div>}{op.backup&&<div className="mt-3 rounded bg-slate-50 p-3 text-xs"><strong>Backup #{op.backup.record_id}</strong> · {op.backup.status} · {bytes(op.backup.size_bytes)}<div>Manifest verified: {op.backup.manifest_verified_at??'no'} · Restore rehearsal: {op.backup.restore_rehearsal.status??'not recorded'} {op.backup.restore_rehearsal.checked_at??''}</div><div className="truncate">Checksum: {op.backup.checksum??'—'}</div></div>}{op.result&&<details className="mt-2"><summary className="cursor-pointer text-xs">Outcome</summary><pre className="mt-2 overflow-auto rounded bg-slate-950 p-2 text-xs text-white">{JSON.stringify(op.result,null,2)}</pre></details>}{op.status==='cleanup_pending'&&<button disabled={busy||!data.execution_enabled_here||!data.recent_authentication||!data.levels[op.level]?.can_execute} onClick={()=>{setLevel(op.level);setDomains(op.domains);setPreview({public_id:op.public_id,level:op.level,domains:op.domains,domain_counts:{},record_count:op.record_count,file_count:op.file_count,barriers:[],preservation:[],preview_sha256:'recovery',confirmation_required:data.levels[op.level].confirmation_required,execution_enabled_here:data.execution_enabled_here});setConfirmation('');window.scrollTo({top:0,behavior:'smooth'});}} className="mt-3 rounded border px-3 py-2 text-xs">Resume cleanup recovery</button>}</div>)}</div></section>

            <section className="mt-5 rounded-2xl border bg-white p-5"><h2 className="font-semibold">Surviving reset audit</h2><div className="mt-3 max-h-[520px] overflow-auto rounded border">{data.audit.length===0&&<p className="p-3 text-sm text-slate-500">No reset audit events.</p>}{data.audit.map(row=><div key={row.id} className="border-b p-3 text-xs last:border-0"><strong>{row.action}</strong> · {row.actor_name??row.actor_email??'admin'} · HTTP {row.status_code}<div className="mt-1 text-slate-500">{row.created_at}</div><pre className="mt-2 overflow-auto whitespace-pre-wrap">{JSON.stringify(row.payload,null,2)}</pre></div>)}</div></section>
        </>}</main></div></>;
}
