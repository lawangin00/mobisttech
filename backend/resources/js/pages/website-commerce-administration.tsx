import { Head, Link } from '@inertiajs/react';
import { FormEvent, useCallback, useEffect, useState } from 'react';

type Order = {
    id:string; order_number:string; customer_name:string; customer_email:string|null; total:string; currency:string;
    status:string; payment_status:string; fulfillment_status:string; admin_notes:string|null; invoice_number:string|null;
    version:number; created_at:string|null; updated_at:string|null;
};
type Review = {
    id:number; rating:number; title:string|null; body:string; status:string; admin_reply:string|null;
    approved_at:string|null; created_at:string|null; product_slug:string; product_name:string; order_number:string; customer_name:string;
};

async function csrf(){
    const response=await fetch('/internal/admin/auth/csrf-cookie',{credentials:'same-origin',headers:{Accept:'application/json'}});
    const body=await response.json() as {data?:{csrf_token?:string}};
    if(!response.ok||!body.data?.csrf_token)throw new Error('Secure request token is unavailable.');
    return body.data.csrf_token;
}
async function api<T>(url:string,init?:RequestInit):Promise<T>{
    const headers:Record<string,string>={Accept:'application/json'};
    if((init?.method??'GET')!=='GET'){headers['Content-Type']='application/json';headers['X-CSRF-TOKEN']=await csrf();}
    const response=await fetch(url,{...init,credentials:'same-origin',headers});
    const body=await response.json().catch(()=>({})) as {data?:T;message?:string;errors?:Record<string,string[]>};
    if(!response.ok)throw new Error(body.errors?Object.values(body.errors).flat()[0]:(body.message??'Request failed.'));
    return body.data as T;
}

const nextStatus:Record<string,string|undefined>={pending:'processing',processing:'ready',ready:'dispatched',dispatched:'completed'};

export default function WebsiteCommerceAdministration({identity}:{identity:{name:string;job_title:string|null}}){
    const [orders,setOrders]=useState<Order[]>([]); const [reviews,setReviews]=useState<Review[]>([]);
    const [query,setQuery]=useState(''); const [status,setStatus]=useState(''); const [appliedQuery,setAppliedQuery]=useState(''); const [appliedStatus,setAppliedStatus]=useState(''); const [reviewStatus,setReviewStatus]=useState('pending');
    const [busy,setBusy]=useState(false); const [message,setMessage]=useState('');
    const load=useCallback(async()=>{
        const params=new URLSearchParams();if(appliedQuery)params.set('query',appliedQuery);if(appliedStatus)params.set('status',appliedStatus);
        const [orderRows,reviewRows]=await Promise.all([
            api<Order[]>('/internal/admin/website-commerce/orders?'+params.toString()),
            api<Review[]>('/internal/admin/website-commerce/reviews?status='+reviewStatus),
        ]);setOrders(orderRows);setReviews(reviewRows);
    },[appliedQuery,appliedStatus,reviewStatus]);
    useEffect(()=>{void load().catch(error=>setMessage(error instanceof Error?error.message:'Unable to load Website commerce.'));},[load]);
    const run=async(task:()=>Promise<void>,reloadAfter=true)=>{setBusy(true);setMessage('');try{await task();if(reloadAfter)await load();}catch(error){setMessage(error instanceof Error?error.message:'Request failed.');}finally{setBusy(false);}};
    const search=(event:FormEvent)=>{event.preventDefault();setAppliedQuery(query.trim());setAppliedStatus(status);};
    const advance=(order:Order)=>run(async()=>{const target=nextStatus[order.fulfillment_status];if(!target)return;await api('/internal/admin/website-commerce/orders/'+order.id,{method:'PATCH',body:JSON.stringify({version:order.version,fulfillment_status:target,admin_notes:order.admin_notes})});setMessage('Order advanced to '+target+'.');});
    const exportCsv=()=>run(async()=>{const params=new URLSearchParams();if(appliedQuery)params.set('query',appliedQuery);if(appliedStatus)params.set('status',appliedStatus);const data=await api<{filename:string;csv:string}>('/internal/admin/website-commerce/orders.csv?'+params.toString());const url=URL.createObjectURL(new Blob([data.csv],{type:'text/csv;charset=utf-8'}));const link=document.createElement('a');link.href=url;link.download=data.filename;link.click();URL.revokeObjectURL(url);},false);
    const moderate=(review:Review,decision:'approved'|'rejected')=>run(async()=>{await api('/internal/admin/website-commerce/reviews/'+review.id,{method:'PATCH',body:JSON.stringify({decision,admin_reply:review.admin_reply})});setMessage('Review '+decision+'.');});
    return <><Head title="Website commerce administration"/><main className="min-h-screen bg-slate-50 p-4 text-slate-950 sm:p-6"><div className="mx-auto max-w-[1500px] space-y-5">
        <header className="flex flex-wrap items-center justify-between gap-3 rounded-2xl border bg-white p-5"><div><p className="text-xs font-semibold uppercase tracking-[0.2em] text-slate-500">mobiST Technologies</p><h1 className="text-2xl font-semibold">Website commerce administration</h1><p className="text-sm text-slate-600">{identity.name} · {identity.job_title??'Administrator'}</p></div><Link href="/internal/admin/platform" className="rounded border px-3 py-2 text-sm">Back to Platform Administration</Link></header>
        {message&&<p role="status" className="rounded-xl border bg-white p-3 text-sm">{message}</p>}
        <section className="rounded-2xl border bg-white p-5"><div className="flex flex-wrap items-center justify-between gap-3"><div><h2 className="font-semibold">Website orders</h2><p className="text-sm text-slate-600">Financial and payment history stays immutable; only the next fulfillment state is available.</p></div><button disabled={busy} onClick={()=>void exportCsv()} className="rounded border px-3 py-2 text-sm disabled:opacity-40">Export CSV</button></div>
            <form onSubmit={search} className="mt-4 grid gap-2 sm:grid-cols-[1fr_12rem_auto]"><input value={query} onChange={e=>setQuery(e.target.value)} placeholder="Order, customer or email" className="rounded border p-2 text-sm"/><select value={status} onChange={e=>setStatus(e.target.value)} className="rounded border p-2 text-sm"><option value="">All fulfillment states</option>{['pending','processing','ready','dispatched','completed','cancelled'].map(value=><option key={value} value={value}>{value}</option>)}</select><button disabled={busy} className="rounded bg-slate-950 px-4 py-2 text-sm text-white disabled:opacity-40">Search</button></form>
            <div className="mt-4 overflow-x-auto"><table className="min-w-[900px] w-full text-left text-sm"><thead><tr className="border-b"><th className="p-2">Order</th><th className="p-2">Customer</th><th className="p-2">Total</th><th className="p-2">Payment</th><th className="p-2">Fulfillment</th><th className="p-2">Action</th></tr></thead><tbody>{orders.map(order=><tr key={order.id} className="border-b align-top"><td className="p-2"><strong>{order.order_number}</strong><div className="text-xs text-slate-500">v{order.version} · {order.status}</div></td><td className="p-2">{order.customer_name}<div className="text-xs text-slate-500">{order.customer_email??'No email'}</div></td><td className="p-2">{order.currency} {order.total}</td><td className="p-2">{order.payment_status}</td><td className="p-2">{order.fulfillment_status}</td><td className="p-2">{nextStatus[order.fulfillment_status]?<button disabled={busy} onClick={()=>void advance(order)} className="rounded border px-2 py-1 text-xs disabled:opacity-40">Mark {nextStatus[order.fulfillment_status]}</button>:<span className="text-xs text-slate-500">No forward action</span>}</td></tr>)}{orders.length===0&&<tr><td colSpan={6} className="p-4 text-center text-slate-500">No matching Website orders.</td></tr>}</tbody></table></div>
        </section>
        <section className="rounded-2xl border bg-white p-5"><div className="flex flex-wrap items-center justify-between gap-3"><div><h2 className="font-semibold">Product review moderation</h2><p className="text-sm text-slate-600">Only purchase-qualified Customer reviews enter this queue.</p></div><select value={reviewStatus} onChange={e=>setReviewStatus(e.target.value)} className="rounded border p-2 text-sm"><option value="pending">Pending</option><option value="approved">Approved</option><option value="rejected">Rejected</option></select></div>
            <div className="mt-4 grid gap-3">{reviews.map(review=><article key={review.id} className="rounded-xl border p-4"><div className="flex flex-wrap justify-between gap-2"><strong>{review.product_name} · {review.rating}/5</strong><span className="text-xs text-slate-500">{review.order_number} · {review.customer_name} · {review.status}</span></div>{review.title&&<h3 className="mt-2 font-medium">{review.title}</h3>}<p className="mt-1 text-sm">{review.body}</p><textarea value={review.admin_reply??''} disabled={review.status!=='pending'} onChange={e=>setReviews(rows=>rows.map(row=>row.id===review.id?{...row,admin_reply:e.target.value}:row))} placeholder="Optional Admin reply" className="mt-3 min-h-20 w-full rounded border p-2 text-sm disabled:bg-slate-100"/>{review.status==='pending'&&<div className="mt-2 flex gap-2"><button disabled={busy} onClick={()=>void moderate(review,'approved')} className="rounded bg-slate-950 px-3 py-2 text-xs text-white disabled:opacity-40">Approve</button><button disabled={busy} onClick={()=>void moderate(review,'rejected')} className="rounded border px-3 py-2 text-xs disabled:opacity-40">Reject</button></div>}</article>)}{reviews.length===0&&<p className="text-sm text-slate-500">No reviews in this queue.</p>}</div>
        </section>
    </div></main></>;
}
