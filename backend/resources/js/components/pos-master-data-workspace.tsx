import { useEffect, useState } from 'react';

type Option = { id:number;list_key:string;code:string;label:string;is_active:boolean;archived_at:string|null;sort_order:number;metadata:Record<string,string|number>|null;usages_count:number };
type Catalogue = {lists:Record<string,string>;options:Option[]};
const endpoint='/internal/admin/pos/master-data';
async function csrf():Promise<string>{
    const response=await fetch('/internal/admin/auth/csrf-cookie',{credentials:'same-origin',headers:{Accept:'application/json'}});
    const body=await response.json() as {data?:{csrf_token?:string}};
    if(!response.ok||!body.data?.csrf_token)throw new Error('Secure request token unavailable.');
    return body.data.csrf_token;
}
export default function PosMasterDataWorkspace(){
    const [catalogue,setCatalogue]=useState<Catalogue|null>(null);
    const [list,setList]=useState('product_brand');
    const [label,setLabel]=useState(''); const [value,setValue]=useState('');
    const [hex,setHex]=useState('#111111'); const [parent,setParent]=useState('accessory');
    const [party,setParty]=useState('business'); const [slots,setSlots]=useState('1');
    const [busy,setBusy]=useState(false); const [message,setMessage]=useState('');
    const load=async()=>{
        const response=await fetch(endpoint,{credentials:'same-origin',headers:{Accept:'application/json'},cache:'no-store'});
        const body=await response.json() as {data?:Catalogue;message?:string};
        if(!response.ok||!body.data)throw new Error(body.message??'Master data unavailable.');
        setCatalogue(body.data);
    };
    useEffect(()=>{void load().catch(e=>setMessage(e instanceof Error?e.message:'Load failed.'));},[]);
    const change=async(action:string,id?:number)=>{
        if(action==='delete'&&!window.confirm('Permanently delete this unused option?'))return;
        setBusy(true);setMessage('');
        try{
            const input:Record<string,unknown>={list,action,...(id?{id}:{})};
            if(action==='create'){input.label=label.trim();if(list==='unit_color')input.hex=hex;
                if(list==='device_ram_gb'||list==='device_storage_gb')input.value=Number(value);
                if(list==='product_subcategory')input.parent_category_code=parent;
                if(list==='acquisition_source_type')input.party_kind=party;
                if(list==='device_sim_configuration')input.imei_slots=Number(slots);}
            const response=await fetch(endpoint,{method:'POST',credentials:'same-origin',headers:{Accept:'application/json','Content-Type':'application/json','X-CSRF-TOKEN':await csrf()},body:JSON.stringify(input)});
            const body=await response.json().catch(()=>({})) as {message?:string;errors?:Record<string,string[]>};
            if(!response.ok)throw new Error(body.errors?Object.values(body.errors).flat()[0]:(body.message??'Change rejected.'));
            await load();setLabel('');setMessage('Option '+action+' successful.');
        }catch(e){setMessage(e instanceof Error?e.message:'Change failed.');}finally{setBusy(false);}
    };
    const options=catalogue?.options.filter(option=>option.list_key===list)??[];
    const numeric=list==='device_ram_gb'||list==='device_storage_gb';
    return <section data-testid="master-data-workspace" className="mt-5 grid gap-4 rounded-xl border bg-white p-4">
        <h3 className="font-semibold">Protected product master data</h3>
        <p className="text-xs text-slate-600">Codes and historical product labels remain immutable. In-use options cannot be deleted.</p>
        <label className="grid gap-1 text-sm">Option list<select data-testid="master-list" value={list} onChange={e=>{setList(e.target.value);setMessage('');}} className="rounded border p-2">{Object.entries(catalogue?.lists??{}).map(([key,name])=><option key={key} value={key}>{name}</option>)}</select></label>
        {list!=='product_category'&&<form className="grid gap-2 rounded border p-3 sm:grid-cols-2" onSubmit={event=>{event.preventDefault();void change('create');}}>
            <input data-testid="master-label" value={label} onChange={e=>setLabel(e.target.value)} maxLength={160} required={!numeric} placeholder={numeric?'Capacity (GB) is entered below':'New plain-text label'} className="rounded border p-2 text-sm"/>
            {numeric&&<input value={value} onChange={e=>setValue(e.target.value)} required type="number" min="1" max={list==='device_ram_gb'?'2048':'8192'} placeholder="Capacity GB" className="rounded border p-2 text-sm"/>}
            {list==='unit_color'&&<input value={hex} onChange={e=>setHex(e.target.value)} aria-label="Color hex" pattern="#[0-9a-fA-F]{6}" className="rounded border p-2 text-sm"/>}
            {list==='product_subcategory'&&<select aria-label="Parent category" value={parent} onChange={e=>setParent(e.target.value)} className="rounded border p-2 text-sm">{['mobile_phone','tablet','accessory'].map(x=><option key={x} value={x}>{x.replaceAll('_',' ')}</option>)}</select>}
            {list==='acquisition_source_type'&&<select aria-label="Source party kind" value={party} onChange={e=>setParty(e.target.value)} className="rounded border p-2 text-sm"><option value="business">Business</option><option value="individual">Individual</option></select>}
            {list==='device_sim_configuration'&&<select aria-label="IMEI slots" value={slots} onChange={e=>setSlots(e.target.value)} className="rounded border p-2 text-sm"><option value="1">1 IMEI slot</option><option value="2">2 IMEI slots</option></select>}
            <button data-testid="master-create" disabled={busy||(!label.trim()&&!numeric)||(numeric&&!value)} className="rounded bg-slate-950 p-2 text-sm text-white">Create option</button>
        </form>}
        {message&&<p role="alert" className="rounded bg-slate-100 p-2 text-sm">{message}</p>}
        <div className="grid gap-2">{options.map(option=><div key={option.id} className="flex flex-wrap items-center justify-between gap-2 rounded border p-3 text-sm"><div><strong>{option.label}</strong><p className="text-xs text-slate-500">{option.code} · {option.usages_count} recorded uses · {option.archived_at?'Archived':option.is_active?'Active':'Inactive'}</p></div>
            <div className="flex flex-wrap gap-1">{!option.archived_at&&<button disabled={busy} className="rounded border px-2 py-1" onClick={()=>void change(option.is_active?'deactivate':'activate',option.id)}>{option.is_active?'Deactivate':'Activate'}</button>}
                {option.archived_at?<button disabled={busy} className="rounded border px-2 py-1" onClick={()=>void change('restore',option.id)}>Restore inactive</button>:list!=='product_category'&&<button disabled={busy} className="rounded border px-2 py-1" onClick={()=>void change('archive',option.id)}>Archive</button>}
                {!option.usages_count&&list!=='product_category'&&<button disabled={busy} className="rounded border px-2 py-1" onClick={()=>void change('delete',option.id)}>Delete unused</button>}
            </div></div>)}</div>
    </section>;
}
