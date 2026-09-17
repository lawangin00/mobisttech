import { Head, Link } from '@inertiajs/react';
import { useMemo, useState } from 'react';

type NavigationItem = {
    key: string;
    label: string;
    description: string;
    href: string;
};

type Outlet = { id: string; name: string };

type ShellContract = {
    identity: {
        id: string;
        name: string;
        email: string;
        job_title: string | null;
        roles: string[];
        permissions: string[];
    };
    outlets: Outlet[];
    active_outlet: Outlet | null;
    navigation: NavigationItem[];
    current_area: string | null;
    workspace: (NavigationItem & { permission: string }) | null;
};

type View =
    | { kind: 'home' }
    | { kind: 'workspace'; workspace: NavigationItem & { permission: string } };

async function csrfToken(): Promise<string> {
    const response = await fetch('/internal/admin/auth/csrf-cookie', {
        credentials: 'same-origin',
        headers: { Accept: 'application/json' },
    });
    if (!response.ok) throw new Error('Secure request token is unavailable.');
    const body = await response.json() as { data?: { csrf_token?: string } };
    const token = body.data?.csrf_token;
    if (!token) throw new Error('Secure request token is unavailable.');
    return token;
}

export default function PosShell({ shell, view }: { shell: ShellContract; view: View }) {
    const [busy, setBusy] = useState(false);
    const [message, setMessage] = useState<string | null>(null);
    const roleText = useMemo(
        () => shell.identity.roles.length ? shell.identity.roles.join(', ') : 'Direct permissions',
        [shell.identity.roles],
    );
    const needsOutlet = shell.outlets.length > 1 && !shell.active_outlet;

    async function selectOutlet(outletId: string) {
        if (!outletId) return;
        setBusy(true);
        setMessage(null);
        try {
            const token = await csrfToken();
            const response = await fetch('/internal/admin/outlets/select', {
                method: 'POST',
                credentials: 'same-origin',
                headers: {
                    Accept: 'application/json',
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': token,
                },
                body: JSON.stringify({ outlet_id: outletId }),
            });
            if (!response.ok) throw new Error('Outlet could not be selected.');
            window.location.assign('/internal/admin/pos');
        } catch (error) {
            setMessage(error instanceof Error ? error.message : 'Outlet could not be selected.');
        } finally {
            setBusy(false);
        }
    }

    async function logout() {
        setBusy(true);
        setMessage(null);
        try {
            const token = await csrfToken();
            const response = await fetch('/internal/admin/auth/logout', {
                method: 'POST',
                credentials: 'same-origin',
                headers: {
                    Accept: 'application/json',
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': token,
                },
                body: '{}',
            });
            if (!response.ok) throw new Error('Sign out failed.');
            window.location.assign('/internal/admin/pos/login');
        } catch (error) {
            setMessage(error instanceof Error ? error.message : 'Sign out failed.');
            setBusy(false);
        }
    }

    const Navigation = ({ mobile = false }: { mobile?: boolean }) => (
        <nav aria-label="POS navigation" className={mobile ? 'grid gap-1 pt-3' : 'mt-8 grid gap-1'}>
            <Link href="/internal/admin/pos" data-testid="nav-home"
                className={'rounded-xl px-3 py-2.5 text-sm font-medium ' + (!shell.current_area ? 'bg-slate-900 text-white' : 'text-slate-700 hover:bg-slate-100')}>
                Home
            </Link>
            {shell.navigation.map((item) => <Link key={item.key} href={item.href}
                data-testid={'nav-' + item.key}
                className={'rounded-xl px-3 py-2.5 text-sm font-medium ' + (shell.current_area === item.key ? 'bg-slate-900 text-white' : 'text-slate-700 hover:bg-slate-100')}>
                {item.label}
            </Link>)}
        </nav>
    );

    return <><Head title={view.kind === 'home' ? 'POS home' : view.workspace.label} />
        <div className="min-h-screen bg-slate-100 text-slate-950">
            <header className="border-b border-slate-200 bg-white lg:hidden">
                <div className="flex items-center justify-between px-4 py-3">
                    <Link href="/internal/admin/pos" className="font-semibold tracking-tight">mobiST POS</Link>
                    <details className="relative">
                        <summary className="cursor-pointer list-none rounded-lg border border-slate-300 px-3 py-2 text-sm font-medium">Menu</summary>
                        <div className="absolute right-0 z-40 mt-2 w-64 rounded-2xl border border-slate-200 bg-white p-3 shadow-xl">
                            <Navigation mobile />
                        </div>
                    </details>
                </div>
            </header>
            <div className="mx-auto grid min-h-screen max-w-[1600px] lg:grid-cols-[260px_minmax(0,1fr)]">
                <aside className="hidden border-r border-slate-200 bg-white p-5 lg:block">
                    <Link href="/internal/admin/pos" className="text-xl font-semibold tracking-tight">mobiST POS</Link>
                    <p className="mt-1 text-xs uppercase tracking-[0.18em] text-slate-500">Team workspace</p>
                    <Navigation />
                    <div className="mt-8 rounded-xl bg-slate-50 p-3 text-xs leading-5 text-slate-600">
                        <p className="font-semibold text-slate-800">{shell.identity.name}</p>
                        <p>{roleText}</p>
                    </div>
                </aside>
                <main className="min-w-0 p-4 sm:p-6 lg:p-8">
                    <div className="mx-auto max-w-6xl">
                        <div className="flex flex-col gap-4 rounded-2xl border border-slate-200 bg-white p-4 shadow-sm sm:flex-row sm:items-center sm:justify-between">
                            <div>
                                <p className="text-xs font-semibold uppercase tracking-[0.2em] text-slate-500">Signed in as Team Member</p>
                                <h1 className="mt-1 text-xl font-semibold" data-testid="member-name">{shell.identity.name}</h1>
                                <p className="mt-1 text-sm text-slate-600">{shell.identity.job_title ?? roleText}</p>
                            </div>
                            <div className="flex flex-col gap-2 sm:flex-row sm:items-center">
                                {shell.outlets.length > 0 && <label className="text-xs font-medium text-slate-600">Active outlet
                                    <select data-testid="outlet-select" disabled={busy}
                                        value={shell.active_outlet?.id ?? ''}
                                        onChange={(e) => void selectOutlet(e.target.value)}
                                        className="ml-2 rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm text-slate-900">
                                        {!shell.active_outlet && <option value="">Choose outlet</option>}
                                        {shell.outlets.map((outlet) => <option key={outlet.id} value={outlet.id}>{outlet.name}</option>)}
                                    </select>
                                </label>}
                                <button data-testid="logout" disabled={busy} onClick={() => void logout()}
                                    className="rounded-lg border border-slate-300 px-3 py-2 text-sm font-medium hover:bg-slate-50 disabled:opacity-60">
                                    Sign out
                                </button>
                            </div>
                        </div>
                        {message && <div role="alert" className="mt-4 rounded-xl border border-red-200 bg-red-50 p-3 text-sm text-red-800">{message}</div>}
                        {needsOutlet && <section data-testid="outlet-required" className="mt-6 rounded-2xl border border-amber-200 bg-amber-50 p-5">
                            <h2 className="font-semibold text-amber-950">Choose an outlet to enter POS</h2>
                            <p className="mt-2 text-sm leading-6 text-amber-900">Your credential is assigned to more than one open outlet. Select the outlet you are working in before opening operational workspaces.</p>
                        </section>}
                        {view.kind === 'home'
                            ? <Home shell={shell} />
                            : <Workspace shell={shell} workspace={view.workspace} />}
                    </div>
                </main>
            </div>
        </div>
    </>;
}

function Home({ shell }: { shell: ShellContract }) {
    return <section className="mt-6">
        <div className="rounded-3xl bg-slate-950 p-6 text-white sm:p-8">
            <p className="text-xs font-semibold uppercase tracking-[0.22em] text-slate-400">Role landing</p>
            <h2 className="mt-3 text-3xl font-semibold tracking-tight">Welcome, {shell.identity.name}.</h2>
            <p className="mt-3 max-w-2xl text-sm leading-7 text-slate-300">
                Your POS navigation is generated from effective backend permissions and your assigned outlet scope.
                Hidden menu items are not authorization.
            </p>
        </div>
        {shell.navigation.length > 0 ? <div data-testid="workspace-cards" className="mt-6 grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
            {shell.navigation.map((item) => <Link key={item.key} href={item.href}
                className="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm transition hover:-translate-y-0.5 hover:shadow-md">
                <h3 className="font-semibold">{item.label}</h3>
                <p className="mt-2 text-sm leading-6 text-slate-600">{item.description}</p>
                <p className="mt-4 text-sm font-semibold text-slate-900">Open workspace →</p>
            </Link>)}
        </div> : <div data-testid="no-pos-access" className="mt-6 rounded-2xl border border-slate-200 bg-white p-6">
            <h3 className="font-semibold">No POS operational workspace is assigned.</h3>
            <p className="mt-2 text-sm leading-6 text-slate-600">
                Your Team Member credential remains valid for separately authorized administration areas.
                POS access requires both the relevant permission and an assigned open outlet.
            </p>
        </div>}
    </section>;
}

function Workspace({ shell, workspace }: {
    shell: ShellContract;
    workspace: NavigationItem & { permission: string };
}) {
    return <section data-testid={'workspace-' + workspace.key}
        className="mt-6 rounded-3xl border border-slate-200 bg-white p-6 sm:p-8">
        <p className="text-xs font-semibold uppercase tracking-[0.22em] text-slate-500">Authorized POS workspace</p>
        <h2 className="mt-3 text-3xl font-semibold tracking-tight">{workspace.label}</h2>
        <p className="mt-3 max-w-2xl text-sm leading-7 text-slate-600">{workspace.description}</p>
        <div className="mt-6 rounded-2xl bg-slate-50 p-5">
            <p className="font-medium">Shell boundary verified</p>
            <p className="mt-2 text-sm leading-6 text-slate-600">
                This route is protected server-side by the required permission and active outlet assignment.
                Transaction forms intentionally remain outside MT-4.1 and arrive in their named interface points.
            </p>
            <p className="mt-3 text-xs text-slate-500">
                Active outlet: {shell.active_outlet?.name ?? 'Not selected'}
            </p>
        </div>
    </section>;
}
