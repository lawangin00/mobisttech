import { Link } from '@inertiajs/react';
import { useEffect, useState } from 'react';
import PlatformAdmin from './platform-admin';

type Identity = { name: string; job_title: string | null };

export default function PlatformAdminPaymentNavigation({ identity }: { identity: Identity }) {
    const [canViewPaymentStatus, setCanViewPaymentStatus] = useState(false);

    useEffect(() => {
        let active = true;
        void fetch('/internal/admin/website/payment-channels', {
            credentials: 'same-origin',
            headers: { Accept: 'application/json' },
            cache: 'no-store',
        }).then(response => {
            if (active) setCanViewPaymentStatus(response.ok);
        }).catch(() => {
            if (active) setCanViewPaymentStatus(false);
        });
        return () => { active = false; };
    }, []);

    return <>
        {canViewPaymentStatus && <nav aria-label="Payment administration" className="flex justify-end border-b bg-white px-4 py-2 sm:px-6">
            <Link href="/internal/admin/website/payment-settings" className="rounded border px-3 py-2 text-sm">Payment channel status (read-only)</Link>
        </nav>}
        <PlatformAdmin identity={identity} />
    </>;
}
