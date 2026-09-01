import '../css/app.css';
import { createInertiaApp } from '@inertiajs/react';
import { createRoot } from 'react-dom/client';
import Foundation from './pages/foundation';
import Integrations from './pages/integrations';
import AdminSessionBoundary from './components/admin-session-boundary';

void createInertiaApp({
    title: (title) => `${title} | mobiST Tech`,
    resolve: (name) => {
        if (name === 'foundation') return Foundation;
        if (name === 'integrations') return Integrations;
        throw new Error('Unknown application page');
    },
    setup({ el, App, props }) {
        createRoot(el).render(<AdminSessionBoundary><App {...props} /></AdminSessionBoundary>);
    },
});
