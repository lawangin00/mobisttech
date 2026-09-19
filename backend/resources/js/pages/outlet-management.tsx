import { Head, Link } from '@inertiajs/react';
import { useEffect, useState } from 'react';
import OutletProfileWorkspace from '../components/outlet-profile-workspace';

type Outlet = {id:string;outlet_code:string;name:string;business_address:string|null;status:string;version:number};
type ArchiveHistory = {outlet_code:string;name:string;archived_at:string;cash_session_count:number;cash_entry_count:number;cash_sessions:Array<{id:string;business_date:string;status:string;expected_cash:string;actual_cash:string;closed_at:string}>;sales_claim_history:{invoice_count:number;sale_line_count:number;claim_count:number;claim_event_count:number;invoices:Array<{id:string;number:string|null;currency:string;final_bill:string;created_at:string|null}>;claims:Array<{id:string;number:string|null;status:string;invoice_id:string}>};stock_history:{product_count:number;unit_count:number;movement_count:number;acquisition_count:number;stocktake_count:number;transfer_count:number;transfers:Array<{id:string;number:string;status:string;source_id:string;destination_id:string;dispatched_at:string|null;completed_at:string|null}>;products:Array<{id:string;code:string|null;name:string;quantity:number;product_archived:boolean}>;movements:Array<{product_id:string;type:string;quantity_change:number;stock_after:number;created_at:string|null}>}};
async function call<T>(url:string, method='GET', data?:Record<string,unknown>):Promise<T> {
    const csrf = await fetch('/internal/admin/auth/csrf-cookie',{credentials:'same-origin',headers:{Accept:'application/json'}});
    if(!csrf.ok) throw new Error('Secure request token unavailable.');
    const token = (await csrf.json() as {data:{csrf_token:string}}).data.csrf_token;
    const response = await fetch(url,{method,credentials:'same-origin',headers:{Accept:'application/json',
        'Content-Type':'application/json','X-CSRF-TOKEN':token},body:method==='GET'?undefined:JSON.stringify(data??{})});
    const result = await response.json().catch(()=>({})) as {data:T;message?:string;errors?:Record<string,string[]>};
    if(!response.ok) throw new Error(result.errors?Object.values(result.errors).flat()[0]:(result.message??'Request failed.'));
    return result.data;
}
export default function OutletManagement() {
    const [outlets,setOutlets]=useState<Outlet[]>([]);
    const [name,setName]=useState('');
    const [address,setAddress]=useState('');
    const [password,setPassword]=useState('');
    const [busy,setBusy]=useState(false);
    const [message,setMessage]=useState('');
    const [editing,setEditing]=useState<string|null>(null);
    const [history,setHistory]=useState<Record<string,ArchiveHistory>>({});
    const load=async()=>setOutlets(await call<Outlet[]>('/internal/admin/outlet-management/data'));
    useEffect(()=>{void load().catch(e=>setMessage(e instanceof Error?e.message:'Unable to load outlets.'));},[]);
    async function act(task:()=>Promise<void>) {
        if(!password){setMessage('Confirm your current password.');return;}
        setBusy(true);setMessage('');
        try {
            await call('/internal/admin/auth/confirm-password','POST',{password});
            await task();await load();setPassword('');setMessage('Outlet change saved.');
        } catch(e) {setMessage(e instanceof Error?e.message:'Outlet change failed.');}
        finally {setBusy(false);}
    }
    return <><Head title="Outlet management"/><main className="mx-auto max-w-4xl space-y-5 p-6 text-slate-900">
        <header className="flex items-center justify-between gap-3"><h1 className="text-2xl font-semibold">Outlet management</h1>
            <Link href="/internal/admin/pos" className="rounded border p-2 text-sm">Back to POS</Link></header>
        <p className="text-sm text-slate-600">Protected Full Access only. Outlet codes are immutable and never reused. Only empty or independently verified closed-cash-history outlets may be archived; unresolved obligations block archival. Archived history is read-only.</p>
        <label className="block text-sm">Confirm current Admin password
            <input data-testid="outlet-owner-password" type="password" autoComplete="current-password" value={password} onChange={e=>setPassword(e.target.value)} className="mt-1 block w-full rounded border p-2" /></label>
        <section className="rounded-xl border p-4"><h2 className="font-semibold">Create outlet</h2>
            <input data-testid="outlet-new-name" value={name} onChange={e=>setName(e.target.value)} placeholder="Outlet name" className="mt-3 block w-full rounded border p-2"/>
            <input data-testid="outlet-new-address" value={address} onChange={e=>setAddress(e.target.value)} placeholder="Outlet address" className="mt-2 block w-full rounded border p-2"/>
            <button data-testid="outlet-create" disabled={busy||!name.trim()||!address.trim()||!password} onClick={()=>void act(async()=>{
                await call('/internal/admin/outlet-management','POST',{name,business_address:address});setName('');setAddress('');
            })} className="mt-3 rounded bg-slate-950 px-3 py-2 text-white disabled:opacity-40">Create outlet</button></section>
        <section className="rounded-xl border p-4"><h2 className="font-semibold">Outlets</h2>
            <div className="mt-3 space-y-2">{outlets.map(o=><div key={o.id} className="flex flex-wrap items-center justify-between gap-2 rounded border p-3 text-sm">
                <span><strong>{o.outlet_code} · {o.name}</strong> · {o.status}<span className="block text-slate-600">{o.business_address??'No address'}</span></span>
                {o.status==='open'&&<button data-testid={'outlet-manage-profile-'+o.outlet_code} disabled={busy} onClick={()=>setEditing(editing===o.id?null:o.id)} className="rounded border px-3 py-2">{editing===o.id?'Close profile':'Edit profile'}</button>}
                {o.status==='open'&&<button disabled={busy||!password} onClick={()=>void act(async()=>{
                    await call('/internal/admin/outlet-management/'+o.id+'/archive','POST',{version:o.version});
                })} className="rounded border px-3 py-2 disabled:opacity-40">Archive eligible outlet</button>}
                {o.status==='archived'&&<button data-testid={'outlet-history-'+o.outlet_code} disabled={busy} onClick={()=>void call<ArchiveHistory>('/internal/admin/outlet-management/'+o.id+'/history').then(data=>setHistory(previous=>({...previous,[o.id]:data}))).catch(e=>setMessage(e instanceof Error?e.message:'Archived history unavailable.'))} className="rounded border px-3 py-2 disabled:opacity-40">View read-only history</button>}
                {history[o.id]&&o.status==='archived'&&<div data-testid={'outlet-archive-summary-'+o.outlet_code} className="w-full rounded border bg-slate-50 p-3 text-xs"><strong>Archived {history[o.id].name}</strong><p>Closed cash sessions: {history[o.id].cash_session_count}; cash entries: {history[o.id].cash_entry_count}</p><div className="mt-2 space-y-1">{history[o.id].cash_sessions.map(session=><p key={session.id}>{session.business_date}: closed; expected {session.expected_cash}; actual {session.actual_cash}</p>)}</div><div data-testid={'outlet-archived-sales-claims-'+o.outlet_code} className="mt-3 space-y-1 rounded border p-2"><strong>Historical sales and warranty claims â€” read-only</strong><p>Invoices: {history[o.id].sales_claim_history.invoice_count}; sale lines: {history[o.id].sales_claim_history.sale_line_count}; claims: {history[o.id].sales_claim_history.claim_count}; claim events: {history[o.id].sales_claim_history.claim_event_count}</p>{history[o.id].sales_claim_history.invoices.map(invoice=><p key={invoice.id}>Invoice {invoice.number??invoice.id}: {invoice.currency} {invoice.final_bill}</p>)}{history[o.id].sales_claim_history.claims.map(claim=><p key={claim.id}>Claim {claim.number??claim.id}: {claim.status}; invoice {claim.invoice_id}</p>)}</div><div data-testid={'outlet-archived-stock-'+o.outlet_code} className="mt-3 space-y-1 rounded border p-2"><strong>Historical stock â€” read-only</strong><p>Products: {history[o.id].stock_history.product_count}; units: {history[o.id].stock_history.unit_count}; movements: {history[o.id].stock_history.movement_count}; acquisitions: {history[o.id].stock_history.acquisition_count}; stocktakes: {history[o.id].stock_history.stocktake_count}; transfers: {history[o.id].stock_history.transfer_count}</p>{history[o.id].stock_history.products.map(product=><p key={product.id}>{product.code??product.id}: {product.name}; recorded quantity {product.quantity}{product.product_archived?' (retired definition)':''}</p>)}<p>Historical transfers (latest 50):</p>{history[o.id].stock_history.transfers.map(transfer=><p key={transfer.id}>{transfer.number}: {transfer.status}; {transfer.source_id} â†’ {transfer.destination_id}</p>)}<p>Latest movements (up to 50):</p>{history[o.id].stock_history.movements.map((movement,i)=><p key={movement.product_id+'-'+i}>{movement.type}: {movement.quantity_change}; after {movement.stock_after}</p>)}</div><p>Historical records are read-only; operational access stays disabled.</p></div>}
                {editing===o.id&&o.status==='open'&&<div className="w-full"><OutletProfileWorkspace key={o.id} outletId={o.id} managed onSaved={()=>void load()} /></div>}
            </div>)}</div></section>
        {message&&<p role="status" className="rounded border p-3 text-sm">{message}</p>}
    </main></>;
}
