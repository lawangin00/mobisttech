import {Head, Link} from '@inertiajs/react';
import {FormEvent, useState} from 'react';
import {adminAuthRequest} from '../components/admin-auth-request';
export default function AdminRecovery({mode}:{mode:'request'|'reset'}) {
    const query = new URLSearchParams(window.location.search);
    const [email,setEmail]=useState(query.get('email')??'');
    const [token,setToken]=useState(query.get('token')??'');
    const [secret,setSecret]=useState(''); const [confirm,setConfirm]=useState('');
    const [busy,setBusy]=useState(false); const [message,setMessage]=useState(''); const [completed,setCompleted]=useState(false);
    async function submit(event:FormEvent<HTMLFormElement>){
        event.preventDefault();setBusy(true);setMessage('');
        try {
            if(mode==='reset') {
                if(secret!==confirm) throw new Error('New passwords do not match.');
                await adminAuthRequest('/internal/admin/auth/reset-password','POST',{email,token,password:secret,password_confirmation:confirm});
                setToken('');setSecret('');setConfirm('');setCompleted(true);
                window.history.replaceState(null,'','/internal/admin/reset-password');
                setMessage('Password updated. Sign in again.');
            } else {
                const response=await adminAuthRequest('/internal/admin/auth/forgot-password','POST',{email});
                setCompleted(true);setMessage(String(response.message??'If an eligible account matches, a recovery link has been sent.'));
            }
        } catch(error){setMessage(error instanceof Error?error.message:'Account recovery is unavailable.');}
        finally{setBusy(false);}
    }
    return <><Head title="Team Member account recovery"/><main className="min-h-screen bg-slate-100 p-5 text-slate-950 sm:p-10"><section className="mx-auto max-w-lg space-y-4 rounded-2xl border bg-white p-6">
        <img src="/brand/mobist-wordmark.svg" alt="mobiST Technologies" className="h-9"/>
        <h1 className="text-2xl font-semibold">{mode==='request'?'Team Member account recovery':'Set a new password'}</h1>
        <p className="text-sm text-slate-600">{mode==='request'?'Recovery email is available only when the approved delivery integration is enabled.':'Use the single-use link sent to your registered email.'}</p>
        {!completed&&<form onSubmit={submit} className="space-y-3">
            <label className="block text-sm">Email<input data-testid="recovery-email" type="email" autoComplete="email" required value={email} onChange={e=>setEmail(e.target.value)} className="mt-1 w-full rounded border p-2"/></label>
            {mode==='reset'&&<>
                <label className="block text-sm">New password<input data-testid="recovery-new" type="password" autoComplete="new-password" minLength={8} maxLength={128} required value={secret} onChange={e=>setSecret(e.target.value)} className="mt-1 w-full rounded border p-2"/></label>
                <label className="block text-sm">Confirm password<input data-testid="recovery-confirm" type="password" autoComplete="new-password" required value={confirm} onChange={e=>setConfirm(e.target.value)} className="mt-1 w-full rounded border p-2"/></label>
            </>}
            <button data-testid="recovery-submit" type="submit" disabled={busy||(mode==='reset'&&!token)} className="rounded bg-slate-950 px-4 py-2 text-sm text-white disabled:opacity-50">{mode==='request'?'Request recovery link':'Reset password'}</button>
        </form>}
        {mode==='reset'&&!token&&!completed&&<p role="alert" className="text-sm">A valid recovery link is required.</p>}
        {message&&<p role="status" data-testid="recovery-message" className="text-sm">{message}</p>}
        <Link href="/internal/admin/pos/login" className="inline-block text-sm underline">Return to sign in</Link>
    </section></main></>;
}
