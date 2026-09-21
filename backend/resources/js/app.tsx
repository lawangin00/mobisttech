import '../css/app.css';
import { createInertiaApp } from '@inertiajs/react';
import { createRoot } from 'react-dom/client';
import DigitalOperations from './pages/digital-operations';
import Foundation from './pages/foundation';
import Integrations from './pages/integrations';
import PlatformAdmin from './pages/platform-admin';
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
        if (name === 'integrations') return Integrations;
        if (name === 'platform-admin') return PlatformAdmin;
        if (name === 'reset-administration') return ResetAdministration;
        if (name === 'pos-login') return PosLogin;
        if (name === 'pos-shell') return PosShell;
        if (name === 'outlet-management') return OutletManagement;
        if (name === 'admin-account') return AdminAccount;
        if (name === 'admin-recovery') return AdminRecovery;
        if (name === 'pos-audit-viewer') return PosAuditViewer;
        if (name === 'pos-portal-preferences') return PosPortalPreferences;
        if (name === 'pos-dashboard-report-preferences') return PosDashboardReportPreferences;
        throw new Error('Unknown application page');
    },
    setup({ el, App, props }) {
        createRoot(el).render(<AdminSessionBoundary><App {...props} /></AdminSessionBoundary>);
    },
});
