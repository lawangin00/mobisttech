import { useEffect, useState } from 'react';

type OutletProfile = { id:string;outlet_code:string;version:number;name:string;business_legal_name:string|null;business_phone:string|null;business_whatsapp:string|null;business_address:string|null;business_hours:string|null };
type Form = Pick<OutletProfile,'name'|'business_legal_name'|'business_phone'|'business_whatsapp'|'business_address'|'business_hours'>;
const fields: Array<{key:keyof Form;label:string}> = [
    {key:'name',label:'Outlet name'}, {key:'business_legal_name',label:'Legal name'},
    {key:'business_phone',label:'Phone'}, {key:'business_whatsapp',label:'WhatsApp'},
    {key:'business_address',label:'Address'}, {key:'business_hours',label:'Business hours'},
];

export default function OutletProfileWorkspace({outletId, managed=false, onSaved}:{outletId:string;managed?:boolean;onSaved?:()=>void}) {
    const url = managed ? '/internal/admin/outlet-management/'+encodeURIComponent(outletId)+'/profile' : '/internal/admin/pos/outlet-profile';
    const [password,setPassword] = useState('');
    const [profile,setProfile] = useState<OutletProfile|null>(null);
    const [form,setForm] = useState<Form|null>(null);
    const [busy,setBusy] = useState(false);
    const [message,setMessage] = useState('');
    async function load() {
        const response = await fetch(url,{headers:{Accept:'application/json'},credentials:'same-origin'});
        const payload = await response.json() as {data?:OutletProfile;message?:string};
        if(!response.ok||!payload.data) throw new Error(payload.message??'Outlet profile unavailable.');
        if(payload.data.id!==outletId) throw new Error('Selected outlet changed; reload the workspace.');
        setProfile(payload.data);
        setForm(Object.fromEntries(fields.map(field=>[field.key,payload.data?.[field.key]??''])) as Form);
    }
    useEffect(()=>{let active=true;void load().catch(error=>{if(active)setMessage(error instanceof Error?error.message:'Unable to load outlet profile.');});return()=>{active=false;};},[outletId]);
    async function save() {
        if(!profile||!form) return;
        setBusy(true);setMessage('');
        try {
            const csrfResponse=await fetch('/internal/admin/auth/csrf-cookie',{credentials:'same-origin',headers:{Accept:'application/json'}});
            const csrfBody=await csrfResponse.json() as {data?:{csrf_token?:string}};
            if(!csrfResponse.ok||!csrfBody.data?.csrf_token) throw new Error('Secure request token is unavailable.');
            if(managed) {
                if(!password) throw new Error('Confirm your current password before saving.');
                const confirmation=await fetch('/internal/admin/auth/confirm-password',{method:'POST',credentials:'same-origin',headers:{Accept:'application/json','Content-Type':'application/json','X-CSRF-TOKEN':csrfBody.data.csrf_token},body:JSON.stringify({password})});
                if(!confirmation.ok) throw new Error('Current password confirmation failed.');
            }
            const response=await fetch(url,{
                method:'PATCH',credentials:'same-origin',headers:{Accept:'application/json','Content-Type':'application/json','X-CSRF-TOKEN':csrfBody.data.csrf_token},
                body:JSON.stringify({...form,version:profile.version}),
            });
            const body=await response.json() as {data?:OutletProfile;message?:string;errors?:Record<string,string[]>};
            if(!response.ok||!body.data) throw new Error(body.errors?Object.values(body.errors).flat()[0]:(body.message??'Save failed.'));
            await load();setPassword('');onSaved?.();setMessage('Outlet profile saved.');
        } catch(error) {setMessage(error instanceof Error?error.message:'Save failed.');}
        finally {setBusy(false);}
    }
    return <div data-testid="outlet-profile-editor" className="mt-6 rounded-xl border bg-slate-50 p-4">
        <p className="text-sm text-slate-600">{managed ? 'Full Access may edit this unassigned outlet after password confirmation; membership and history are unchanged.' : 'Only your selected and assigned outlet can be edited.'} Its original three-character code and historical records cannot be changed here.</p>
        {profile&&form?<><p className="mt-3 text-xs font-semibold">Outlet code: <span data-testid="outlet-profile-code">{profile.outlet_code}</span></p>
            <div className="mt-3 grid gap-3 sm:grid-cols-2">{fields.map(field=><label key={field.key} className="text-xs font-medium">{field.label}
                <input data-testid={'outlet-profile-'+field.key} value={form[field.key]??''} onChange={event=>setForm({...form,[field.key]:event.target.value})}
                    className="mt-1 block w-full rounded border bg-white p-2 text-sm" maxLength={field.key==='name'?160:field.key==='business_legal_name'?255:field.key==='business_phone'||field.key==='business_whatsapp'?40:1000}/>
            </label>)}</div>
            {managed&&<label className="mt-3 block text-xs">Confirm current Admin password<input data-testid="outlet-managed-password" type="password" autoComplete="current-password" value={password} onChange={event=>setPassword(event.target.value)} className="mt-1 block w-full rounded border bg-white p-2 text-sm" /></label>}
            <button data-testid="outlet-profile-save" disabled={busy||!form.name.trim()||(managed&&!password)} onClick={()=>void save()} className="mt-4 rounded bg-slate-950 px-4 py-2 text-sm text-white disabled:opacity-50">Save outlet profile</button></>:<p className="mt-2 text-sm">Loading the selected outlet...</p>}
        {message&&<p data-testid="outlet-profile-message" role="status" className="mt-3 text-sm">{message}</p>}
    </div>;
}
