import { Head } from '@inertiajs/react';
import { FormEvent, useState } from 'react';

type ErrorBody = {
    error?: {
        message?: string;
        fields?: Record<string, string[]>;
    };
};

export default function PosLogin() {
    const [email, setEmail] = useState('');
    const [password, setPassword] = useState('');
    const [busy, setBusy] = useState(false);
    const [message, setMessage] = useState<string | null>(null);

    async function submit(event: FormEvent<HTMLFormElement>) {
        event.preventDefault();
        setBusy(true);
        setMessage(null);
        try {
            const csrfResponse = await fetch('/internal/admin/auth/csrf-cookie', {
                credentials: 'same-origin',
                headers: { Accept: 'application/json' },
            });
            if (!csrfResponse.ok) throw new Error('Secure sign-in could not be initialized.');
            const csrfBody = await csrfResponse.json() as { data?: { csrf_token?: string } };
            const token = csrfBody.data?.csrf_token;
            if (!token) throw new Error('Secure sign-in token is unavailable.');

            const response = await fetch('/internal/admin/auth/login', {
                method: 'POST',
                credentials: 'same-origin',
                headers: {
                    Accept: 'application/json',
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': token,
                },
                body: JSON.stringify({ email, password }),
            });
            const body = await response.json() as ErrorBody;
            if (!response.ok) {
                const fieldMessage = body.error?.fields?.email?.[0] ?? body.error?.fields?.password?.[0];
                throw new Error(fieldMessage ?? body.error?.message ?? 'Sign-in failed.');
            }
            window.location.assign('/internal/admin/pos');
        } catch (error) {
            setMessage(error instanceof Error ? error.message : 'Sign-in failed.');
        } finally {
            setBusy(false);
        }
    }

    return <><Head title="Team Member sign in" /><main className="min-h-screen bg-slate-950 px-5 py-8 sm:px-8">
        <div className="mx-auto grid min-h-[calc(100vh-4rem)] max-w-6xl overflow-hidden rounded-3xl bg-white shadow-2xl lg:grid-cols-[1.1fr_0.9fr]">
            <section className="hidden bg-slate-900 p-12 text-white lg:flex lg:flex-col lg:justify-between">
                <div><p className="text-sm font-semibold uppercase tracking-[0.28em] text-slate-300">mobiST</p>
                    <h1 className="mt-8 max-w-xl text-5xl font-semibold leading-tight">One Team Member identity for POS and administration.</h1>
                    <p className="mt-6 max-w-lg text-lg leading-8 text-slate-300">Permissions and outlet assignments are enforced by the Laravel backend. This screen never grants access by hiding or showing a menu.</p>
                </div>
                <p className="text-sm text-slate-400">Admin sessions expire after true inactivity. Remember Me is unavailable for Team Members.</p>
            </section>
            <section className="flex items-center p-7 sm:p-12">
                <div className="w-full max-w-md mx-auto">
                    <p className="text-sm font-semibold uppercase tracking-[0.24em] text-slate-500">mobiST POS</p>
                    <h2 className="mt-4 text-3xl font-semibold tracking-tight text-slate-950">Team Member sign in</h2>
                    <p className="mt-3 text-sm leading-6 text-slate-600">Use your individual work credential. Shared employee logins are not supported.</p>
                    {message && <div role="alert" className="mt-5 rounded-xl border border-red-200 bg-red-50 p-3 text-sm text-red-800">{message}</div>}
                    <form onSubmit={submit} className="mt-7 space-y-5">
                        <label className="block text-sm font-medium text-slate-800">Email
                            <input data-testid="login-email" type="email" autoComplete="username" required value={email} onChange={(e) => setEmail(e.target.value)}
                                className="mt-2 w-full rounded-xl border border-slate-300 bg-white px-4 py-3 outline-none transition focus:border-slate-600 focus:ring-2 focus:ring-slate-200" />
                        </label>
                        <label className="block text-sm font-medium text-slate-800">Password
                            <input data-testid="login-password" type="password" autoComplete="current-password" required value={password} onChange={(e) => setPassword(e.target.value)}
                                className="mt-2 w-full rounded-xl border border-slate-300 bg-white px-4 py-3 outline-none transition focus:border-slate-600 focus:ring-2 focus:ring-slate-200" />
                        </label>
                        <button data-testid="login-submit" disabled={busy} type="submit" className="w-full rounded-xl bg-slate-950 px-4 py-3 text-sm font-semibold text-white transition hover:bg-slate-800 disabled:cursor-not-allowed disabled:opacity-60">
                            {busy ? 'Signing in?' : 'Sign in'}
                        </button>
                    </form>
                    <p className="mt-6 text-xs leading-5 text-slate-500">For account recovery or access changes, use the approved administrative process. This POS shell does not create alternate credential realms.</p>
                </div>
            </section>
        </div>
    </main></>;
}
