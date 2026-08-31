import { Head } from '@inertiajs/react';

export default function Foundation({ application, scope }: { application: string; scope: string }) {
    return (
        <>
            <Head title="POS foundation" />
            <main className="mx-auto flex min-h-screen max-w-3xl flex-col justify-center px-6 py-12">
                <p className="mb-3 text-sm font-semibold uppercase tracking-widest text-slate-500">{application}</p>
                <h1 className="text-4xl font-semibold tracking-tight text-slate-950">POS and administration foundation</h1>
                <p className="mt-5 text-lg leading-8 text-slate-600">The isolated Laravel, React, TypeScript, Inertia and Tailwind foundation is ready. Business workflows and identity migration are not yet available.</p>
                <p className="mt-8 rounded-xl border border-slate-200 bg-white p-4 text-sm text-slate-600">{scope}</p>
            </main>
        </>
    );
}
