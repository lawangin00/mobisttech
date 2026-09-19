import { Head, Link } from '@inertiajs/react';
import { useEffect, useState } from 'react';

type Outlet = {id:string;outlet_code:string;name:string;business_address:string|null;status:string;version:number};
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
        <p className="text-sm text-slate-600">Protected Full Access only. Outlet codes are immutable; archived codes are never reused. Historical or active business records block automatic archive.</p>
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
                {o.status==='open'&&<button disabled={busy||!password} onClick={()=>void act(async()=>{
                    await call('/internal/admin/outlet-management/'+o.id+'/archive','POST',{version:o.version});
                })} className="rounded border px-3 py-2 disabled:opacity-40">Archive empty outlet</button>}
            </div>)}</div></section>
        {message&&<p role="status" className="rounded border p-3 text-sm">{message}</p>}
    </main></>;
}
