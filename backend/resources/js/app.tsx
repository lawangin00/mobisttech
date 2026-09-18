import '../css/app.css';
import { createInertiaApp } from '@inertiajs/react';
import { createRoot } from 'react-dom/client';
import Foundation from './pages/foundation';
import Integrations from './pages/integrations';
import PlatformAdmin from './pages/platform-admin';
import PosLogin from './pages/pos-login';
import PosShell from './pages/pos-shell';
import AdminSessionBoundary from './components/admin-session-boundary';

void createInertiaApp({
    title: (title) => `${title} | mobiST Tech`,
    resolve: (name) => {
        if (name === 'foundation') return Foundation;
        if (name === 'integrations') return Integrations;
        if (name === 'platform-admin') return PlatformAdmin;
        if (name === 'pos-login') return PosLogin;
        if (name === 'pos-shell') return PosShell;
        throw new Error('Unknown application page');
    },
    setup({ el, App, props }) {
        createRoot(el).render(<AdminSessionBoundary><App {...props} /></AdminSessionBoundary>);
    },
});
