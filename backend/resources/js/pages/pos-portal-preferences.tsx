import {Head,Link} from '@inertiajs/react';
import {FormEvent,useState} from 'react';
type NavigationOverride={label?:string;visible?:boolean;order?:number};
type NavigationItem={key:string;default_label:string;customizable:boolean};
type Values=Record<string,unknown>&{navigation:Record<string,NavigationOverride>};
type Props={values:Values;options:Record<string,string[]>;navigation:NavigationItem[]};
const labels:Record<string,string>={invoice_page_length:'Invoices per page',inventory_page_length:'Inventory rows per page',claims_page_length:'Warranty jobs per page',invoice_search_category:'Default invoice search category',inventory_search_category:'Default inventory search category',warranty_search_category:'Default warranty search category',claims_search_category:'Default warranty jobs search category',auto_focus_search:'Focus search automatically',remember_search:'Allow per-operator opt-in search category memory (current tab only; never search text)'};
export default function PosPortalPreferences({values,options,navigation}:Props){
    const [form,setForm]=useState<Values>(values);
    const [busy,setBusy]=useState(false);
    const [message,setMessage]=useState('');
    const [saved,setSaved]=useState(false);
    async function submit(event:FormEvent<HTMLFormElement>){
        event.preventDefault();setBusy(true);setSaved(false);setMessage('');
        try {
            const csrf=await fetch('/internal/admin/auth/csrf-cookie',{credentials:'same-origin',headers:{Accept:'application/json'}});
            const cookie=await csrf.json() as {data?:{csrf_token?:string}};
            if(!csrf.ok||!cookie.data?.csrf_token)throw new Error('Secure request token unavailable.');
            const response=await fetch('/internal/admin/pos/portal-preferences',{method:'PUT',credentials:'same-origin',headers:{Accept:'application/json','Content-Type':'application/json','X-CSRF-TOKEN':cookie.data.csrf_token},body:JSON.stringify(form)});
            const body=await response.json().catch(()=>({})) as {data?:Values;error?:{message?:string;fields?:Record<string,string[]>}};
            if(!response.ok||!body.data)throw new Error(Object.values(body.error?.fields??{}).flat()[0]??body.error?.message??'Preferences could not be saved.');
            setForm(body.data);setSaved(true);setMessage('Portal preferences saved.');
        }catch(error){setMessage(error instanceof Error?error.message:'Preferences could not be saved.');}
        finally{setBusy(false);}
    }
    return <><Head title="POS portal preferences"/><main className="min-h-screen bg-slate-100 p-5 text-slate-950 sm:p-8"><div className="mx-auto max-w-3xl space-y-5">
        <div className="flex items-center justify-between gap-3"><h1 className="text-2xl font-semibold">POS portal preferences</h1><Link href="/internal/admin/pos" className="rounded border px-3 py-2 text-sm">Back to POS</Link></div>
        <p className="text-sm text-slate-600">Business-wide display defaults. Current list screens may have separate retrieval limits; these settings do not grant new data permissions.</p>
        <form onSubmit={submit} className="grid gap-4 rounded-xl border bg-white p-5 sm:grid-cols-2" data-testid="portal-preferences-form">
            {Object.entries(options).map(([key,choices])=><label key={key} className="grid gap-2 text-sm font-medium">{labels[key]??key}
                <select data-testid={'pref-'+key} value={String(form[key]??'')} onChange={event=>{setSaved(false);setForm(previous=>({...previous,[key]:event.target.value}));}} className="min-w-0 rounded border p-2">
                    {choices.map(option=><option key={option} value={option}>{option}</option>)}
                </select></label>)}
            {(['auto_focus_search','remember_search'] as const).map(key=><label key={key} className="flex items-center gap-2 text-sm">
                <input data-testid={'pref-'+key} type="checkbox" checked={form[key]===true} onChange={event=>{setSaved(false);setForm(previous=>({...previous,[key]:event.target.checked}));}}/>{labels[key]}
            </label>)}
            <section className="grid gap-3 border-t pt-4 sm:col-span-2" data-testid="navigation-presentation"><div><h2 className="font-semibold">POS navigation presentation</h2><p className="text-xs text-slate-500">Labels, visibility and order affect presentation only. Direct routes keep their backend permissions.</p></div>{navigation.map((item,index)=>{const current=form.navigation[item.key]??{};const label=current.label??item.default_label;const visible=current.visible??true;const order=current.order??100+index*10;return <div key={item.key} className="grid gap-2 rounded border p-3 sm:grid-cols-[1fr_auto_7rem]"><label className="text-xs">Label<input disabled={!item.customizable||busy} value={label} onChange={event=>{setSaved(false);setForm(previous=>({...previous,navigation:{...previous.navigation,[item.key]:{...current,label:event.target.value,visible,order}}}));}} className="mt-1 w-full rounded border p-2 text-sm"/></label><label className="flex items-center gap-2 text-xs"><input disabled={!item.customizable||busy} type="checkbox" checked={visible} onChange={event=>{setSaved(false);setForm(previous=>({...previous,navigation:{...previous.navigation,[item.key]:{...current,label,visible:event.target.checked,order}}}));}}/>Visible</label><label className="text-xs">Order<input disabled={!item.customizable||busy} type="number" min="100" max="999" value={order} onChange={event=>{setSaved(false);setForm(previous=>({...previous,navigation:{...previous.navigation,[item.key]:{...current,label,visible,order:Number(event.target.value)}}}));}} className="mt-1 w-full rounded border p-2 text-sm"/></label></div>;})}</section>
            <div className="sm:col-span-2"><button disabled={busy} type="submit" data-testid="portal-preferences-save" className="rounded bg-slate-950 px-4 py-2 text-sm text-white disabled:opacity-50">Save preferences</button></div>
            {message&&<p className="sm:col-span-2 text-sm" role={saved?'status':'alert'}>{message}</p>}
        </form>
    </div></main></>;
}
