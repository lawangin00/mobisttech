import {Head, Link} from '@inertiajs/react';
import {FormEvent, useState} from 'react';
import {adminAuthRequest} from '../components/admin-auth-request';
type Identity = {name:string;email:string;job_title:string|null;roles:string[]};
export default function AdminAccount({identity}:{identity:Identity}) {
    const [current,setCurrent]=useState('');
    const [next,setNext]=useState('');
    const [confirm,setConfirm]=useState('');
    const [busy,setBusy]=useState(false);
    const [message,setMessage]=useState('');
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
