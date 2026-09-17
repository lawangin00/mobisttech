import { type PropsWithChildren, useCallback, useEffect, useRef, useState } from 'react';

type SessionPolicy = {
    inactivity_minutes: number;
    warning_minutes: number | null;
};

type SessionState = {
    server_epoch: number;
    last_human_activity_epoch: number;
    warning_epoch: number | null;
    expires_epoch: number;
};

type AccountResponse = {
    data?: { session_policy?: SessionPolicy; session_state?: SessionState };
};

export default function AdminSessionBoundary({ children }: PropsWithChildren) {
    const [policy, setPolicy] = useState<SessionPolicy | null>(null);
    const [session, setSession] = useState<SessionState | null>(null);
    const [warning, setWarning] = useState(false);
    const serverOffsetMs = useRef(0);
    const activityPending = useRef(false);
    const lastActivitySync = useRef(0);
    const path = typeof window !== 'undefined' ? window.location.pathname : '';
    const enabled = path.startsWith('/internal/admin') && path !== '/internal/admin/pos/login';

    const applyState = useCallback((nextPolicy?: SessionPolicy, nextState?: SessionState) => {
        if (nextPolicy) setPolicy(nextPolicy);
        if (nextState) {
            serverOffsetMs.current = nextState.server_epoch * 1000 - Date.now();
            setSession(nextState);
        }
    }, []);

    const redirectToLogin = useCallback(() => {
        window.location.assign('/internal/admin/pos/login');
    }, []);

    const refreshState = useCallback(async () => {
        const response = await fetch('/internal/admin/account', {
            credentials: 'same-origin',
            headers: { Accept: 'application/json', 'X-MobiST-Background': '1' },
        });
        if (response.status === 401) return redirectToLogin();
        if (!response.ok) return;
        const body = await response.json() as AccountResponse;
        applyState(body.data?.session_policy, body.data?.session_state);
    }, [applyState, redirectToLogin]);

    const continueSession = useCallback(async () => {
        const csrf = await fetch('/internal/admin/auth/csrf-cookie', {
            credentials: 'same-origin',
            headers: { Accept: 'application/json', 'X-MobiST-Background': '1' },
        });
        if (csrf.status === 401) return redirectToLogin();
        if (!csrf.ok) return;
        const csrfBody = await csrf.json() as { data?: { csrf_token?: string } };
        const token = csrfBody.data?.csrf_token;
        if (!token) return;
        const response = await fetch('/internal/admin/auth/activity', {
            method: 'POST',
            credentials: 'same-origin',
            headers: {
                Accept: 'application/json',
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': token,
                'X-MobiST-User-Activity': '1',
            },
            body: '{}',
        });
        if (response.status === 401) return redirectToLogin();
        if (!response.ok) return;
        const body = await response.json() as AccountResponse;
        activityPending.current = false;
        lastActivitySync.current = Date.now();
        setWarning(false);
        applyState(body.data?.session_policy, body.data?.session_state);
    }, [applyState, redirectToLogin]);

    useEffect(() => {
        if (!enabled) return;
        void refreshState();
        const noteActivity = () => {
            activityPending.current = true;
            if (warning) void continueSession();
        };
        const events: Array<keyof WindowEventMap> = ['pointerdown', 'keydown', 'touchstart'];
        events.forEach((event) => window.addEventListener(event, noteActivity, { passive: true }));
        const timer = window.setInterval(() => {
            const now = Date.now() + serverOffsetMs.current;
            if (session?.expires_epoch && now >= session.expires_epoch * 1000) {
                redirectToLogin();
                return;
            }
            setWarning(Boolean(session?.warning_epoch && now >= session.warning_epoch * 1000));
            if (activityPending.current && Date.now() - lastActivitySync.current >= 60_000) {
                void continueSession();
            }
        }, 5_000);
        const poll = window.setInterval(() => void refreshState(), 60_000);
        return () => {
            window.clearInterval(timer);
            window.clearInterval(poll);
            events.forEach((event) => window.removeEventListener(event, noteActivity));
        };
    }, [continueSession, enabled, redirectToLogin, refreshState, session?.expires_epoch, session?.warning_epoch, warning]);

    const warningMinutes = policy?.warning_minutes ?? 5;
    return <>{children}{enabled && warning && <div role="alertdialog" aria-modal="true" aria-labelledby="session-warning-title" className="fixed inset-x-4 bottom-4 z-50 mx-auto max-w-lg rounded-2xl border border-amber-300 bg-white p-5 shadow-2xl">
        <h2 id="session-warning-title" className="text-lg font-semibold text-slate-950">Your session will expire in about {warningMinutes} minutes.</h2>
        <p className="mt-2 text-sm leading-6 text-slate-600">Background refresh does not keep a Team Member signed in. Continue only if you are still working.</p>
        <button type="button" onClick={() => void continueSession()} className="mt-4 rounded-lg bg-slate-950 px-4 py-2 text-sm font-semibold text-white focus:outline-none focus:ring-2 focus:ring-slate-500">Continue Session</button>
    </div>}</>;
}
