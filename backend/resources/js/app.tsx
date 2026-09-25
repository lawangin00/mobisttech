import '../css/app.css';
import { createInertiaApp } from '@inertiajs/react';
import { createRoot } from 'react-dom/client';
import DigitalOperations from './pages/digital-operations';
import Foundation from './pages/foundation';
import ResetAdministration from './pages/reset-administration';
import PosLogin from './pages/pos-login';
import PosShell from './pages/pos-shell';
import OutletManagement from './pages/outlet-management';
import AdminAccount from './pages/admin-account';
import AdminRecovery from './pages/admin-recovery';
import PosAuditViewer from './pages/pos-audit-viewer';
import PosPortalPreferences from './pages/pos-portal-preferences';
import PosDashboardReportPreferences from './pages/pos-dashboard-report-preferences';
import AdminSessionBoundary from './components/admin-session-boundary';

void createInertiaApp({
    title: (title) => `${title} | mobiST Tech`,
    resolve: (name) => {
        if (name === 'digital-operations') return DigitalOperations;
        if (name === 'foundation') return Foundation;
        if (name === 'integrations') return import('./pages/integrations').then(page => page.default);
        if (name === 'platform-admin') return import('./pages/platform-admin-payment-navigation').then(page => page.default);
        if (name === 'reset-administration') return ResetAdministration;
        if (name === 'pos-login') return PosLogin;
        if (name === 'pos-shell') return PosShell;
        if (name === 'outlet-management') return OutletManagement;
        if (name === 'admin-account') return AdminAccount;
        if (name === 'admin-recovery') return AdminRecovery;
        if (name === 'pos-audit-viewer') return PosAuditViewer;
        if (name === 'pos-portal-preferences') return PosPortalPreferences;
        if (name === 'pos-dashboard-report-preferences') return PosDashboardReportPreferences;
        if (name === 'website-performance') return import('./pages/website-performance').then(page => page.default);
        if (name === 'website-audit-viewer') return import('./pages/website-audit-viewer').then(page => page.default);
        if (name === 'website-commerce-administration') return import('./pages/website-commerce-administration').then((page) => page.default);
        if (name === 'website-payment-settings') return import('./pages/website-payment-settings').then((page) => page.default);
        throw new Error('Unknown application page');
    },
    setup({ el, App, props }) {
        createRoot(el).render(<AdminSessionBoundary><App {...props} /></AdminSessionBoundary>);
    },
});
