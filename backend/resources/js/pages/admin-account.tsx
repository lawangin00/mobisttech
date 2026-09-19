import {Head, Link} from '@inertiajs/react';
import {FormEvent, useState} from 'react';
import {adminAuthRequest} from '../components/admin-auth-request';
type Identity = {name:string;email:string;job_title:string|null;roles:string[];has_photo:boolean};
export default function AdminAccount({identity}:{identity:Identity}) {
    const [current,setCurrent]=useState('');
    const [next,setNext]=useState('');
    const [confirm,setConfirm]=useState('');
    const [busy,setBusy]=useState(false);
    const [photo,setPhoto]=useState(identity.has_photo);
    const [photoRevision,setPhotoRevision]=useState(0);
    const [file,setFile]=useState<File|null>(null);
    const [message,setMessage]=useState('');
    async function mutatePhoto(method:'POST'|'DELETE') {
        if(method==='POST'&&!file){setMessage('Choose a JPG, PNG or WebP image.');return;}
        setBusy(true);setMessage('');
        try {
            const cookie=await fetch('/internal/admin/auth/csrf-cookie',{credentials:'same-origin',headers:{Accept:'application/json'}});
            const token=(await cookie.json() as {data?:{csrf_token?:string}}).data?.csrf_token;
            if(!cookie.ok||!token)throw new Error('Secure request token unavailable.');
            const data=new FormData();if(method==='POST'&&file)data.append('profile_photo',file);
            const response=await fetch('/internal/admin/manage-account/photo',{method,credentials:'same-origin',
                headers:{Accept:'application/json','X-CSRF-TOKEN':token},...(method==='POST'?{body:data}:{})});
            if(!response.ok){const payload=await response.json().catch(()=>({})) as {error?:{fields?:Record<string,string[]>;message?:string}};
                throw new Error(Object.values(payload.error?.fields??{}).flat()[0]??payload.error?.message??'Photo update failed.');}
            setPhoto(method==='POST');setPhotoRevision(value=>value+1);setFile(null);
            setMessage(method==='POST'?'Profile photo updated.':'Profile photo removed.');
        }catch(error){setMessage(error instanceof Error?error.message:'Photo update failed.');}
        finally{setBusy(false);}
    }
    async function change(event:FormEvent<HTMLFormElement>){
        event.preventDefault(); setBusy(true); setMessage('');
        try {
            if(next!==confirm) throw new Error('New passwords do not match.');
            await adminAuthRequest('/internal/admin/auth/password','PATCH',{current_password:current,password:next,password_confirmation:confirm});
            setCurrent(''); setNext(''); setConfirm('');
            window.location.assign('/internal/admin/pos/login');
        } catch(error){setMessage(error instanceof Error?error.message:'Unable to update the account.');}
        finally{setBusy(false);}
    }
    return <><Head title="Team Member account"/><main className="min-h-screen bg-slate-100 p-5 text-slate-950 sm:p-10"><div className="mx-auto max-w-xl space-y-5">
        <div className="flex flex-wrap items-center justify-between gap-3"><img src="/brand/mobist-wordmark.svg" alt="mobiST Technologies" className="h-9"/><Link href="/internal/admin/pos" className="rounded border px-3 py-2 text-sm">Back to POS</Link></div>
        <section className="rounded-2xl border bg-white p-5"><h1 className="text-2xl font-semibold">My Team Member account</h1><p className="mt-3" data-testid="account-name">{identity.name}</p>
            <p className="text-sm text-slate-600" data-testid="account-email">{identity.email}</p><p className="text-sm text-slate-600">{identity.job_title??'Team Member'}</p><p className="mt-2 text-xs text-slate-500">{identity.roles.join(', ')||'Direct permissions'}</p>
        </section>
        <section className="rounded-2xl border bg-white p-5" data-testid="account-photo-workspace">
            <h2 className="text-lg font-semibold">Personal profile photo</h2>
            <p className="mt-2 text-sm text-slate-600">Only you can view or change your photo. JPG, PNG or WebP; maximum 5 MB.</p>
            {photo&&<img data-testid="account-photo-image" src={'/internal/admin/manage-account/photo?v='+photoRevision} alt="My profile photo" className="mt-3 h-24 w-24 rounded-full object-cover"/>}
            <label className="mt-3 block text-sm">Choose photo
                <input data-testid="account-photo-file" type="file" accept="image/jpeg,image/png,image/webp" onChange={event=>setFile(event.target.files?.[0]??null)} className="mt-1 block w-full text-sm"/>
            </label>
            <div className="mt-3 flex gap-2"><button data-testid="account-photo-upload" disabled={busy||!file} onClick={()=>void mutatePhoto('POST')} className="rounded bg-slate-950 px-3 py-2 text-sm text-white disabled:opacity-50">Upload photo</button>
                <button data-testid="account-photo-remove" disabled={busy||!photo} onClick={()=>void mutatePhoto('DELETE')} className="rounded border px-3 py-2 text-sm disabled:opacity-50">Remove photo</button></div>
        </section>
        <section className="rounded-2xl border bg-white p-5"><h2 className="text-lg font-semibold">Change password</h2><p className="mt-2 text-sm text-slate-600">A successful change revokes active sessions and requires a new sign-in.</p>
            <form onSubmit={change} className="mt-4 space-y-3">
                <label className="block text-sm">Current password<input data-testid="account-current" type="password" autoComplete="current-password" required value={current} onChange={e=>setCurrent(e.target.value)} className="mt-1 w-full rounded border p-2"/></label>
                <label className="block text-sm">New password<input data-testid="account-new" type="password" autoComplete="new-password" minLength={8} maxLength={128} required value={next} onChange={e=>setNext(e.target.value)} className="mt-1 w-full rounded border p-2"/></label>
                <label className="block text-sm">Confirm new password<input data-testid="account-confirm" type="password" autoComplete="new-password" required value={confirm} onChange={e=>setConfirm(e.target.value)} className="mt-1 w-full rounded border p-2"/></label>
                <button data-testid="account-change" disabled={busy} type="submit" className="rounded bg-slate-950 px-4 py-2 text-sm text-white disabled:opacity-50">Update password</button>
            </form>{message&&<p role="alert" className="mt-3 text-sm">{message}</p>}
            <Link href="/internal/admin/forgot-password" className="mt-4 inline-block text-sm underline">Forgot your password?</Link>
        </section>
    </div></main></>;
}
