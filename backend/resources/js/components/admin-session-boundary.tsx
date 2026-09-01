import { type PropsWithChildren, useEffect, useRef, useState } from 'react';

const inactivityMs = 30 * 60 * 1000;
const warningMs = 5 * 60 * 1000;

export default function AdminSessionBoundary({ children }: PropsWithChildren) {
    const [warning, setWarning] = useState(false);
    const lastActivity = useRef(Date.now());
    const activityPending = useRef(false);
    const isAdmin = typeof window !== 'undefined' && window.location.pathname.startsWith('/internal/admin');

    useEffect(() => {
        if (!isAdmin) return;
        const noteActivity = () => {
            lastActivity.current = Date.now();
            activityPending.current = true;
            setWarning(false);
        };
        const events: Array<keyof WindowEventMap> = ['pointerdown', 'keydown', 'touchstart'];
        events.forEach((event) => window.addEventListener(event, noteActivity, { passive: true }));
        const timer = window.setInterval(() => {
            const idle = Date.now() - lastActivity.current;
            setWarning(idle >= inactivityMs - warningMs);
            if (activityPending.current && idle < inactivityMs - warningMs) {
                activityPending.current = false;
                void continueSession();
            }
        }, 60_000);
        return () => {
            window.clearInterval(timer);
            events.forEach((event) => window.removeEventListener(event, noteActivity));
        };
    }, [isAdmin]);

    async function continueSession() {
        const csrfResponse = await fetch('/internal/admin/auth/csrf-cookie', { credentials: 'same-origin', headers: { Accept: 'application/json', 'X-MobiST-Background': '1' } });
        if (!csrfResponse.ok) return;
        const token = document.cookie.split('; ').find((item) => item.startsWith('XSRF-TOKEN-admin='))?.split('=')[1];
        if (!token) return;
        const response = await fetch('/internal/admin/auth/activity', { method: 'POST', credentials: 'same-origin',
            headers: { Accept: 'application/json', 'Content-Type': 'application/json', 'X-XSRF-TOKEN': decodeURIComponent(token), 'X-MobiST-User-Activity': '1' }, body: '{}' });
        if (response.ok) {
            lastActivity.current = Date.now();
            setWarning(false);
        }
    }

    return <>{children}{isAdmin && warning && <div role="alertdialog" aria-modal="true" aria-labelledby="session-warning-title" className="fixed inset-x-4 bottom-4 z-50 mx-auto max-w-lg rounded-xl border border-amber-300 bg-white p-5 shadow-xl">
        <h2 id="session-warning-title" className="text-lg font-semibold text-slate-950">Your session will expire in 5 minutes.</h2>
        <p className="mt-2 text-sm text-slate-600">Only your action can continue this Team Member session.</p>
        <button type="button" onClick={() => void continueSession()} className="mt-4 rounded-lg bg-slate-950 px-4 py-2 text-sm font-medium text-white">Continue Session</button>
    </div>}</>;
}
