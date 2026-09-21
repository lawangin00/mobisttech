# MT-7.5 P07 Source File and Route Disposition Crosswalk

Status: **COMPLETE INVENTORY DISPOSITION; P07 RUNTIME ACCEPTANCE REMAINS OPEN**.

Authority: `docs/migration/SOURCE_SYMBOL_INVENTORY.json` (pinned MT-1.1 source inventory). This crosswalk disposes every item assigned to P07 without claiming that the remaining rendered dashboard/report, column-presentation, theme/branding propagation or browser gates have passed.

- Source files: **148/148 disposed**.
- Resolved routes: **32/32 disposed**.
- Inventory SHA-256: `c5368e4121eaaac727efe72b87064ae8367ed1231419b6159642115d2bcbb17e`.

## Route dispositions

| # | Source route | Source name | Target disposition |
|---:|---|---|---|
| 1 | `GET|HEAD admin-panel/control-center` | `admin.control-center` | Unified Admin /platform and /pos/portal-preferences entry surfaces; legacy Admin/Super Admin realm duplication retired. |
| 2 | `GET|HEAD admin-panel/control-center/branding` | `admin.branding.index` | Unified Admin /platform POS branding data, preview, draft, media, publish or rollback endpoint. |
| 3 | `PUT admin-panel/control-center/branding` | `admin.branding.publish` | Unified Admin /platform POS branding data, preview, draft, media, publish or rollback endpoint. |
| 4 | `POST admin-panel/control-center/branding/assets` | `admin.branding.upload` | Unified Admin /platform POS branding data, preview, draft, media, publish or rollback endpoint. |
| 5 | `PUT admin-panel/control-center/branding/preview` | `admin.branding.preview` | Unified Admin /platform POS branding data, preview, draft, media, publish or rollback endpoint. |
| 6 | `PUT admin-panel/control-center/branding/revisions/{revision}/rollback` | `admin.branding.rollback` | Unified Admin /platform POS branding data, preview, draft, media, publish or rollback endpoint. |
| 7 | `GET|HEAD admin-panel/control-center/navigation` | `admin.portal-navigation.index` | Unified Admin /pos/portal-preferences GET/PUT contract. |
| 8 | `PUT admin-panel/control-center/navigation` | `admin.portal-navigation.update` | Unified Admin /pos/portal-preferences GET/PUT contract. |
| 9 | `GET|HEAD admin-panel/control-center/shops/{shop}` | `admin.control-center.shop` | Unified Admin outlet-management/profile and POS outlet-profile contract (P01/P07 overlap). |
| 10 | `PUT admin-panel/control-center/shops/{shop}` | `admin.control-center.shop.update` | Unified Admin outlet-management/profile and POS outlet-profile contract (P01/P07 overlap). |
| 11 | `GET|HEAD admin-panel/control-center/table-presentation` | `admin.table-presentation.index` | Unified Admin /pos/portal-preferences GET/PUT contract. |
| 12 | `PUT admin-panel/control-center/table-presentation` | `admin.table-presentation.update` | Unified Admin /pos/portal-preferences GET/PUT contract. |
| 13 | `GET|HEAD admin-panel/control-center/theme` | `admin.theme.index` | Unified Admin /platform POS theme data, preview, draft, publish or rollback endpoint. |
| 14 | `PUT admin-panel/control-center/theme` | `admin.theme.publish` | Unified Admin /platform POS theme data, preview, draft, publish or rollback endpoint. |
| 15 | `PUT admin-panel/control-center/theme/preview` | `admin.theme.preview` | Unified Admin /platform POS theme data, preview, draft, publish or rollback endpoint. |
| 16 | `PUT admin-panel/control-center/theme/revisions/{revision}/rollback` | `admin.theme.rollback` | Unified Admin /platform POS theme data, preview, draft, publish or rollback endpoint. |
| 17 | `GET|HEAD super-admin/control-center` | `superadmin.control-center` | Unified Admin /platform and /pos/portal-preferences entry surfaces; legacy Admin/Super Admin realm duplication retired. |
| 18 | `GET|HEAD super-admin/control-center/branding` | `superadmin.branding.index` | Unified Admin /platform POS branding data, preview, draft, media, publish or rollback endpoint. |
| 19 | `PUT super-admin/control-center/branding` | `superadmin.branding.publish` | Unified Admin /platform POS branding data, preview, draft, media, publish or rollback endpoint. |
| 20 | `POST super-admin/control-center/branding/assets` | `superadmin.branding.upload` | Unified Admin /platform POS branding data, preview, draft, media, publish or rollback endpoint. |
| 21 | `PUT super-admin/control-center/branding/preview` | `superadmin.branding.preview` | Unified Admin /platform POS branding data, preview, draft, media, publish or rollback endpoint. |
| 22 | `PUT super-admin/control-center/branding/revisions/{revision}/rollback` | `superadmin.branding.rollback` | Unified Admin /platform POS branding data, preview, draft, media, publish or rollback endpoint. |
| 23 | `GET|HEAD super-admin/control-center/navigation` | `superadmin.portal-navigation.index` | Unified Admin /pos/portal-preferences GET/PUT contract. |
| 24 | `PUT super-admin/control-center/navigation` | `superadmin.portal-navigation.update` | Unified Admin /pos/portal-preferences GET/PUT contract. |
| 25 | `GET|HEAD super-admin/control-center/preferences` | `superadmin.preferences` | Unified Admin /pos/portal-preferences GET/PUT contract. |
| 26 | `PUT super-admin/control-center/preferences` | `superadmin.preferences.update` | Unified Admin /pos/portal-preferences GET/PUT contract. |
| 27 | `GET|HEAD super-admin/control-center/table-presentation` | `superadmin.table-presentation.index` | Unified Admin /pos/portal-preferences GET/PUT contract. |
| 28 | `PUT super-admin/control-center/table-presentation` | `superadmin.table-presentation.update` | Unified Admin /pos/portal-preferences GET/PUT contract. |
| 29 | `GET|HEAD super-admin/control-center/theme` | `superadmin.theme.index` | Unified Admin /platform POS theme data, preview, draft, publish or rollback endpoint. |
| 30 | `PUT super-admin/control-center/theme` | `superadmin.theme.publish` | Unified Admin /platform POS theme data, preview, draft, publish or rollback endpoint. |
| 31 | `PUT super-admin/control-center/theme/preview` | `superadmin.theme.preview` | Unified Admin /platform POS theme data, preview, draft, publish or rollback endpoint. |
| 32 | `PUT super-admin/control-center/theme/revisions/{revision}/rollback` | `superadmin.theme.rollback` | Unified Admin /platform POS theme data, preview, draft, publish or rollback endpoint. |

## File dispositions

| # | Source file | Disposition | Target/evidence |
|---:|---|---|---|
| 1 | `app/Http/Controllers/Admin/ControlCenterController.php` | **ADAPT-P01-P07** | Unified Admin platform, outlet profile and portal-preferences controllers; realm duplication retired. |
| 2 | `app/Http/Controllers/PosBrandingController.php` | **ADAPT-CONFIG** | PlatformAdministrationController plus App\Pos\PosConfiguration revision/media authority. |
| 3 | `app/Http/Controllers/PosPortalNavigationController.php` | **ADAPT-PORTAL** | PosPortalPreferencesController plus App\Pos\PortalPreferences and protected React settings. |
| 4 | `app/Http/Controllers/PosPortalTablePresentationController.php` | **ADAPT-PORTAL** | PosPortalPreferencesController plus App\Pos\PortalPreferences and protected React settings. |
| 5 | `app/Http/Controllers/PosThemeController.php` | **ADAPT-CONFIG** | PlatformAdministrationController plus App\Pos\PosConfiguration revision/media authority. |
| 6 | `app/Http/Controllers/SuperAdmin/ControlCenterController.php` | **ADAPT-P01-P07** | Unified Admin platform, outlet profile and portal-preferences controllers; realm duplication retired. |
| 7 | `app/Http/Middleware/RequirePosConfigurationPermission.php` | **ADAPT-AUTH** | Unified Admin permissions and service-level authorization; legacy guard middleware retired. |
| 8 | `app/Models/PosConfigurationRevision.php` | **REUSE-SCHEMA** | Shared-schema POS setting/revision/media models retained or represented by current target tables and services. |
| 9 | `app/Models/PosMediaAsset.php` | **REUSE-SCHEMA** | Shared-schema POS setting/revision/media models retained or represented by current target tables and services. |
| 10 | `app/Models/PosMediaUsage.php` | **REUSE-SCHEMA** | Shared-schema POS setting/revision/media models retained or represented by current target tables and services. |
| 11 | `app/Models/PosSetting.php` | **REUSE-SCHEMA** | Shared-schema POS setting/revision/media models retained or represented by current target tables and services. |
| 12 | `app/Services/PosBranding.php` | **ADAPT-CONFIG** | App\Pos\PosConfiguration and Platform Admin preserve revision, safe-media, publish and rollback rules. |
| 13 | `app/Services/PosBrandingMediaSelector.php` | **ADAPT-CONFIG** | App\Pos\PosConfiguration and Platform Admin preserve revision, safe-media, publish and rollback rules. |
| 14 | `app/Services/PosConfigurationRevisions.php` | **ADAPT-CONFIG** | App\Pos\PosConfiguration and Platform Admin preserve revision, safe-media, publish and rollback rules. |
| 15 | `app/Services/PosPortalNavigation.php` | **ADAPT-PORTAL** | App\Pos\PortalPreferences, PosShell and protected React workspaces own presentation defaults. |
| 16 | `app/Services/PosPortalTablePresentation.php` | **ADAPT-PORTAL** | App\Pos\PortalPreferences, PosShell and protected React workspaces own presentation defaults. |
| 17 | `app/Services/PosSettings.php` | **ADAPT-PORTAL** | App\Pos\PortalPreferences, PosShell and protected React workspaces own presentation defaults. |
| 18 | `app/Services/PosTheme.php` | **ADAPT-CONFIG** | App\Pos\PosConfiguration and Platform Admin preserve revision, safe-media, publish and rollback rules. |
| 19 | `app/Services/SafePosMedia.php` | **ADAPT-CONFIG** | App\Pos\PosConfiguration and Platform Admin preserve revision, safe-media, publish and rollback rules. |
| 20 | `app/Support/PosSettingRegistry.php` | **ADAPT-PORTAL** | App\Pos\PortalPreferences, PosShell and protected React workspaces own presentation defaults. |
| 21 | `database/migrations/2026_08_25_100000_create_pos_settings_table.php` | **MIGRATED-SCHEMA** | Equivalent shared-schema settings, revisions and media foundations exist in target migrations. |
| 22 | `database/migrations/2026_08_25_110000_add_portal_preference_settings.php` | **MIGRATED-SCHEMA** | Equivalent shared-schema settings, revisions and media foundations exist in target migrations. |
| 23 | `database/migrations/2026_08_28_133000_create_pos_configuration_revisions_table.php` | **MIGRATED-SCHEMA** | Equivalent shared-schema settings, revisions and media foundations exist in target migrations. |
| 24 | `database/migrations/2026_08_28_151000_create_pos_media_foundation.php` | **MIGRATED-SCHEMA** | Equivalent shared-schema settings, revisions and media foundations exist in target migrations. |
| 25 | `mobiST Control Center/MobiSTControlCenter.cs` | **RETIRE-CLIENT** | Legacy desktop control-center transport is retired; protected unified Admin is authoritative. |
| 26 | `public/assets/js/mobist-portal-header-layout.js` | **REPLACE-REACT** | Legacy global Blade/JavaScript/CSS presentation replaced by scoped React/Inertia/Tailwind surfaces. |
| 27 | `public/assets/js/mobist-portal-preferences.js` | **REPLACE-REACT** | Legacy global Blade/JavaScript/CSS presentation replaced by scoped React/Inertia/Tailwind surfaces. |
| 28 | `resources/css/app.css` | **REPLACE-REACT** | Legacy global Blade/JavaScript/CSS presentation replaced by scoped React/Inertia/Tailwind surfaces. |
| 29 | `resources/css/design-tokens.css` | **REPLACE-REACT** | Legacy global Blade/JavaScript/CSS presentation replaced by scoped React/Inertia/Tailwind surfaces. |
| 30 | `resources/js/app.js` | **REPLACE-REACT** | Legacy global Blade/JavaScript/CSS presentation replaced by scoped React/Inertia/Tailwind surfaces. |
| 31 | `resources/js/bootstrap.js` | **REPLACE-REACT** | Legacy global Blade/JavaScript/CSS presentation replaced by scoped React/Inertia/Tailwind surfaces. |
| 32 | `resources/views/admin/ajax/badgeEdit.blade.php` | **RETIRE-FRAGMENT** | Legacy Blade/Ajax presentation fragment is not copied; domain behavior stays with its owning parity family and shared React shell. |
| 33 | `resources/views/admin/ajax/badgeView.blade.php` | **RETIRE-FRAGMENT** | Legacy Blade/Ajax presentation fragment is not copied; domain behavior stays with its owning parity family and shared React shell. |
| 34 | `resources/views/admin/ajax/cardEdit.blade.php` | **RETIRE-FRAGMENT** | Legacy Blade/Ajax presentation fragment is not copied; domain behavior stays with its owning parity family and shared React shell. |
| 35 | `resources/views/admin/ajax/courseAdd.blade.php` | **RETIRE-FRAGMENT** | Legacy Blade/Ajax presentation fragment is not copied; domain behavior stays with its owning parity family and shared React shell. |
| 36 | `resources/views/admin/ajax/courseEdit.blade.php` | **RETIRE-FRAGMENT** | Legacy Blade/Ajax presentation fragment is not copied; domain behavior stays with its owning parity family and shared React shell. |
| 37 | `resources/views/admin/ajax/courseEditSubject.blade.php` | **RETIRE-FRAGMENT** | Legacy Blade/Ajax presentation fragment is not copied; domain behavior stays with its owning parity family and shared React shell. |
| 38 | `resources/views/admin/ajax/coursesHome.blade.php` | **RETIRE-FRAGMENT** | Legacy Blade/Ajax presentation fragment is not copied; domain behavior stays with its owning parity family and shared React shell. |
| 39 | `resources/views/admin/ajax/searchView.blade.php` | **RETIRE-FRAGMENT** | Legacy Blade/Ajax presentation fragment is not copied; domain behavior stays with its owning parity family and shared React shell. |
| 40 | `resources/views/admin/backups.blade.php` | **OWNED-P08** | Operational/backup behavior is disposed under completed P08; shared presentation shell remains P07. |
| 41 | `resources/views/admin/control-center-shop.blade.php` | **OWNED-P01** | Identity, account and outlet-profile behavior is disposed under P01; shared presentation shell remains P07. |
| 42 | `resources/views/admin/control-center.blade.php` | **RETIRE-FRAGMENT** | Legacy Blade/Ajax presentation fragment is not copied; domain behavior stays with its owning parity family and shared React shell. |
| 43 | `resources/views/admin/dashboard-presentation.blade.php` | **OWNED-P06-P07** | Report/document calculations are disposed under P06; dashboard/report presentation remains an explicit P07 runtime gate. |
| 44 | `resources/views/admin/dashboard.blade.php` | **OWNED-P06-P07** | Report/document calculations are disposed under P06; dashboard/report presentation remains an explicit P07 runtime gate. |
| 45 | `resources/views/admin/layouts/main.blade.php` | **REPLACE-SHELL** | Legacy realm layouts replaced by the unified Admin and POS React shell. |
| 46 | `resources/views/admin/layouts/top_bar.blade.php` | **REPLACE-SHELL** | Legacy realm layouts replaced by the unified Admin and POS React shell. |
| 47 | `resources/views/admin/login.blade.php` | **OWNED-P01** | Identity, account and outlet-profile behavior is disposed under P01; shared presentation shell remains P07. |
| 48 | `resources/views/admin/manage-account.blade.php` | **OWNED-P01** | Identity, account and outlet-profile behavior is disposed under P01; shared presentation shell remains P07. |
| 49 | `resources/views/admin/portal-navigation.blade.php` | **REPLACE-P07** | Legacy Blade editor replaced by Platform Admin or POS Portal Preferences React surface. |
| 50 | `resources/views/admin/portal-table-presentation.blade.php` | **REPLACE-P07** | Legacy Blade editor replaced by Platform Admin or POS Portal Preferences React surface. |
| 51 | `resources/views/admin/pos-branding.blade.php` | **REPLACE-P07** | Legacy Blade editor replaced by Platform Admin or POS Portal Preferences React surface. |
| 52 | `resources/views/admin/pos-documents.blade.php` | **OWNED-P06-P07** | Report/document calculations are disposed under P06; dashboard/report presentation remains an explicit P07 runtime gate. |
| 53 | `resources/views/admin/pos-master-data.blade.php` | **OWNED-P02-P03** | Catalogue/master-data/stock behavior is disposed under P02/P03; React shell presentation remains P07. |
| 54 | `resources/views/admin/pos-theme.blade.php` | **REPLACE-P07** | Legacy Blade editor replaced by Platform Admin or POS Portal Preferences React surface. |
| 55 | `resources/views/admin/report-presentation.blade.php` | **RETIRE-FRAGMENT** | Legacy Blade/Ajax presentation fragment is not copied; domain behavior stays with its owning parity family and shared React shell. |
| 56 | `resources/views/admin/reports.blade.php` | **OWNED-P06-P07** | Report/document calculations are disposed under P06; dashboard/report presentation remains an explicit P07 runtime gate. |
| 57 | `resources/views/admin/shop-select.blade.php` | **RETIRE-FRAGMENT** | Legacy Blade/Ajax presentation fragment is not copied; domain behavior stays with its owning parity family and shared React shell. |
| 58 | `resources/views/auth/forgot-password.blade.php` | **OWNED-P01** | Identity, account and outlet-profile behavior is disposed under P01; shared presentation shell remains P07. |
| 59 | `resources/views/auth/portal-login.blade.php` | **OWNED-P01** | Identity, account and outlet-profile behavior is disposed under P01; shared presentation shell remains P07. |
| 60 | `resources/views/auth/reset-password.blade.php` | **OWNED-P01** | Identity, account and outlet-profile behavior is disposed under P01; shared presentation shell remains P07. |
| 61 | `resources/views/auth/unified-login.blade.php` | **OWNED-P01** | Identity, account and outlet-profile behavior is disposed under P01; shared presentation shell remains P07. |
| 62 | `resources/views/backups/_content.blade.php` | **OWNED-P08** | Operational/backup behavior is disposed under completed P08; shared presentation shell remains P07. |
| 63 | `resources/views/claims/_overview_scripts.blade.php` | **OWNED-P05** | Warranty/claim behavior is disposed under P05; React shell presentation remains P07. |
| 64 | `resources/views/claims/_overview.blade.php` | **OWNED-P05** | Warranty/claim behavior is disposed under P05; React shell presentation remains P07. |
| 65 | `resources/views/emails/mobist-password-reset.blade.php` | **RETIRE-FRAGMENT** | Legacy Blade/Ajax presentation fragment is not copied; domain behavior stays with its owning parity family and shared React shell. |
| 66 | `resources/views/inventory/_oversight.blade.php` | **OWNED-P02-P03** | Catalogue/master-data/stock behavior is disposed under P02/P03; React shell presentation remains P07. |
| 67 | `resources/views/partials/business-identifiers-fields.blade.php` | **RETIRE-FRAGMENT** | Legacy Blade/Ajax presentation fragment is not copied; domain behavior stays with its owning parity family and shared React shell. |
| 68 | `resources/views/partials/pos-branding-management.blade.php` | **REPLACE-P07** | Legacy Blade editor replaced by Platform Admin or POS Portal Preferences React surface. |
| 69 | `resources/views/partials/pos-dashboard-presentation.blade.php` | **OWNED-P06-P07** | Report/document calculations are disposed under P06; dashboard/report presentation remains an explicit P07 runtime gate. |
| 70 | `resources/views/partials/pos-document-management.blade.php` | **REPLACE-P07** | Legacy Blade editor replaced by Platform Admin or POS Portal Preferences React surface. |
| 71 | `resources/views/partials/pos-master-data-management.blade.php` | **OWNED-P02-P03** | Catalogue/master-data/stock behavior is disposed under P02/P03; React shell presentation remains P07. |
| 72 | `resources/views/partials/pos-portal-navigation.blade.php` | **REPLACE-P07** | Legacy Blade editor replaced by Platform Admin or POS Portal Preferences React surface. |
| 73 | `resources/views/partials/pos-portal-table-presentation.blade.php` | **REPLACE-P07** | Legacy Blade editor replaced by Platform Admin or POS Portal Preferences React surface. |
| 74 | `resources/views/partials/pos-report-presentation.blade.php` | **RETIRE-FRAGMENT** | Legacy Blade/Ajax presentation fragment is not copied; domain behavior stays with its owning parity family and shared React shell. |
| 75 | `resources/views/partials/pos-theme-management.blade.php` | **REPLACE-P07** | Legacy Blade editor replaced by Platform Admin or POS Portal Preferences React surface. |
| 76 | `resources/views/partials/pos-theme.blade.php` | **REPLACE-P07** | Legacy Blade editor replaced by Platform Admin or POS Portal Preferences React surface. |
| 77 | `resources/views/pos-backup-operations/index.blade.php` | **OWNED-P08** | Operational/backup behavior is disposed under completed P08; shared presentation shell remains P07. |
| 78 | `resources/views/pos-integration-status/index.blade.php` | **OWNED-P08** | Operational/backup behavior is disposed under completed P08; shared presentation shell remains P07. |
| 79 | `resources/views/shop/ajax/badgeEdit.blade.php` | **RETIRE-FRAGMENT** | Legacy Blade/Ajax presentation fragment is not copied; domain behavior stays with its owning parity family and shared React shell. |
| 80 | `resources/views/shop/ajax/badgeView.blade.php` | **RETIRE-FRAGMENT** | Legacy Blade/Ajax presentation fragment is not copied; domain behavior stays with its owning parity family and shared React shell. |
| 81 | `resources/views/shop/ajax/cardEdit.blade.php` | **RETIRE-FRAGMENT** | Legacy Blade/Ajax presentation fragment is not copied; domain behavior stays with its owning parity family and shared React shell. |
| 82 | `resources/views/shop/ajax/courseAdd.blade.php` | **RETIRE-FRAGMENT** | Legacy Blade/Ajax presentation fragment is not copied; domain behavior stays with its owning parity family and shared React shell. |
| 83 | `resources/views/shop/ajax/courseEdit.blade.php` | **RETIRE-FRAGMENT** | Legacy Blade/Ajax presentation fragment is not copied; domain behavior stays with its owning parity family and shared React shell. |
| 84 | `resources/views/shop/ajax/courseEditSubject.blade.php` | **RETIRE-FRAGMENT** | Legacy Blade/Ajax presentation fragment is not copied; domain behavior stays with its owning parity family and shared React shell. |
| 85 | `resources/views/shop/ajax/coursesHome.blade.php` | **RETIRE-FRAGMENT** | Legacy Blade/Ajax presentation fragment is not copied; domain behavior stays with its owning parity family and shared React shell. |
| 86 | `resources/views/shop/ajax/searchView.blade.php` | **RETIRE-FRAGMENT** | Legacy Blade/Ajax presentation fragment is not copied; domain behavior stays with its owning parity family and shared React shell. |
| 87 | `resources/views/shop/backups.blade.php` | **OWNED-P08** | Operational/backup behavior is disposed under completed P08; shared presentation shell remains P07. |
| 88 | `resources/views/shop/claims.blade.php` | **OWNED-P05** | Warranty/claim behavior is disposed under P05; React shell presentation remains P07. |
| 89 | `resources/views/shop/dashboard-widgets/inventory-cost.blade.php` | **OWNED-P02-P03** | Catalogue/master-data/stock behavior is disposed under P02/P03; React shell presentation remains P07. |
| 90 | `resources/views/shop/dashboard-widgets/month-profit.blade.php` | **OWNED-P06-P07** | Report/document calculations are disposed under P06; dashboard/report presentation remains an explicit P07 runtime gate. |
| 91 | `resources/views/shop/dashboard-widgets/month-sales.blade.php` | **OWNED-P06-P07** | Report/document calculations are disposed under P06; dashboard/report presentation remains an explicit P07 runtime gate. |
| 92 | `resources/views/shop/dashboard-widgets/recent-invoices.blade.php` | **OWNED-P04** | Sales/invoice behavior is disposed under P04; React shell presentation remains P07. |
| 93 | `resources/views/shop/dashboard-widgets/recent-stock-activity.blade.php` | **OWNED-P06-P07** | Report/document calculations are disposed under P06; dashboard/report presentation remains an explicit P07 runtime gate. |
| 94 | `resources/views/shop/dashboard-widgets/sales-profit-trend.blade.php` | **OWNED-P06-P07** | Report/document calculations are disposed under P06; dashboard/report presentation remains an explicit P07 runtime gate. |
| 95 | `resources/views/shop/dashboard-widgets/sales-today.blade.php` | **OWNED-P06-P07** | Report/document calculations are disposed under P06; dashboard/report presentation remains an explicit P07 runtime gate. |
| 96 | `resources/views/shop/dashboard-widgets/stock-attention.blade.php` | **OWNED-P06-P07** | Report/document calculations are disposed under P06; dashboard/report presentation remains an explicit P07 runtime gate. |
| 97 | `resources/views/shop/dashboard-widgets/stock-health.blade.php` | **OWNED-P06-P07** | Report/document calculations are disposed under P06; dashboard/report presentation remains an explicit P07 runtime gate. |
| 98 | `resources/views/shop/dashboard-widgets/top-products.blade.php` | **OWNED-P06-P07** | Report/document calculations are disposed under P06; dashboard/report presentation remains an explicit P07 runtime gate. |
| 99 | `resources/views/shop/dashboard-widgets/warranty-claims.blade.php` | **OWNED-P05** | Warranty/claim behavior is disposed under P05; React shell presentation remains P07. |
| 100 | `resources/views/shop/dashboard.blade.php` | **OWNED-P06-P07** | Report/document calculations are disposed under P06; dashboard/report presentation remains an explicit P07 runtime gate. |
| 101 | `resources/views/shop/inventory.blade.php` | **OWNED-P02-P03** | Catalogue/master-data/stock behavior is disposed under P02/P03; React shell presentation remains P07. |
| 102 | `resources/views/shop/invoices.blade.php` | **OWNED-P04** | Sales/invoice behavior is disposed under P04; React shell presentation remains P07. |
| 103 | `resources/views/shop/layouts/main.blade.php` | **REPLACE-SHELL** | Legacy realm layouts replaced by the unified Admin and POS React shell. |
| 104 | `resources/views/shop/layouts/top_bar.blade.php` | **REPLACE-SHELL** | Legacy realm layouts replaced by the unified Admin and POS React shell. |
| 105 | `resources/views/shop/login.blade.php` | **OWNED-P01** | Identity, account and outlet-profile behavior is disposed under P01; shared presentation shell remains P07. |
| 106 | `resources/views/shop/pos.blade.php` | **OWNED-P04** | Sales/invoice behavior is disposed under P04; React shell presentation remains P07. |
| 107 | `resources/views/shop/product_imeis.blade.php` | **OWNED-P02-P03** | Catalogue/master-data/stock behavior is disposed under P02/P03; React shell presentation remains P07. |
| 108 | `resources/views/shop/warranty.blade.php` | **OWNED-P05** | Warranty/claim behavior is disposed under P05; React shell presentation remains P07. |
| 109 | `resources/views/superadmin/admins.blade.php` | **OWNED-P01** | Identity, account and outlet-profile behavior is disposed under P01; shared presentation shell remains P07. |
| 110 | `resources/views/superadmin/ajax/badgeEdit.blade.php` | **RETIRE-FRAGMENT** | Legacy Blade/Ajax presentation fragment is not copied; domain behavior stays with its owning parity family and shared React shell. |
| 111 | `resources/views/superadmin/ajax/badgeView.blade.php` | **RETIRE-FRAGMENT** | Legacy Blade/Ajax presentation fragment is not copied; domain behavior stays with its owning parity family and shared React shell. |
| 112 | `resources/views/superadmin/ajax/cardEdit.blade.php` | **RETIRE-FRAGMENT** | Legacy Blade/Ajax presentation fragment is not copied; domain behavior stays with its owning parity family and shared React shell. |
| 113 | `resources/views/superadmin/ajax/courseAdd.blade.php` | **RETIRE-FRAGMENT** | Legacy Blade/Ajax presentation fragment is not copied; domain behavior stays with its owning parity family and shared React shell. |
| 114 | `resources/views/superadmin/ajax/courseEdit.blade.php` | **RETIRE-FRAGMENT** | Legacy Blade/Ajax presentation fragment is not copied; domain behavior stays with its owning parity family and shared React shell. |
| 115 | `resources/views/superadmin/ajax/courseEditSubject.blade.php` | **RETIRE-FRAGMENT** | Legacy Blade/Ajax presentation fragment is not copied; domain behavior stays with its owning parity family and shared React shell. |
| 116 | `resources/views/superadmin/ajax/coursesHome.blade.php` | **RETIRE-FRAGMENT** | Legacy Blade/Ajax presentation fragment is not copied; domain behavior stays with its owning parity family and shared React shell. |
| 117 | `resources/views/superadmin/ajax/searchView.blade.php` | **RETIRE-FRAGMENT** | Legacy Blade/Ajax presentation fragment is not copied; domain behavior stays with its owning parity family and shared React shell. |
| 118 | `resources/views/superadmin/audit-log.blade.php` | **OWNED-P08** | Operational/backup behavior is disposed under completed P08; shared presentation shell remains P07. |
| 119 | `resources/views/superadmin/backups.blade.php` | **OWNED-P08** | Operational/backup behavior is disposed under completed P08; shared presentation shell remains P07. |
| 120 | `resources/views/superadmin/business-profile.blade.php` | **OWNED-P01** | Identity, account and outlet-profile behavior is disposed under P01; shared presentation shell remains P07. |
| 121 | `resources/views/superadmin/control-center.blade.php` | **RETIRE-FRAGMENT** | Legacy Blade/Ajax presentation fragment is not copied; domain behavior stays with its owning parity family and shared React shell. |
| 122 | `resources/views/superadmin/dashboard-presentation.blade.php` | **OWNED-P06-P07** | Report/document calculations are disposed under P06; dashboard/report presentation remains an explicit P07 runtime gate. |
| 123 | `resources/views/superadmin/dashboard.blade.php` | **OWNED-P06-P07** | Report/document calculations are disposed under P06; dashboard/report presentation remains an explicit P07 runtime gate. |
| 124 | `resources/views/superadmin/layouts/main.blade.php` | **REPLACE-SHELL** | Legacy realm layouts replaced by the unified Admin and POS React shell. |
| 125 | `resources/views/superadmin/layouts/top_bar.blade.php` | **REPLACE-SHELL** | Legacy realm layouts replaced by the unified Admin and POS React shell. |
| 126 | `resources/views/superadmin/login.blade.php` | **OWNED-P01** | Identity, account and outlet-profile behavior is disposed under P01; shared presentation shell remains P07. |
| 127 | `resources/views/superadmin/manage-account.blade.php` | **OWNED-P01** | Identity, account and outlet-profile behavior is disposed under P01; shared presentation shell remains P07. |
| 128 | `resources/views/superadmin/portal-navigation.blade.php` | **REPLACE-P07** | Legacy Blade editor replaced by Platform Admin or POS Portal Preferences React surface. |
| 129 | `resources/views/superadmin/portal-preferences.blade.php` | **REPLACE-P07** | Legacy Blade editor replaced by Platform Admin or POS Portal Preferences React surface. |
| 130 | `resources/views/superadmin/portal-table-presentation.blade.php` | **REPLACE-P07** | Legacy Blade editor replaced by Platform Admin or POS Portal Preferences React surface. |
| 131 | `resources/views/superadmin/pos-branding.blade.php` | **REPLACE-P07** | Legacy Blade editor replaced by Platform Admin or POS Portal Preferences React surface. |
| 132 | `resources/views/superadmin/pos-documents.blade.php` | **OWNED-P06-P07** | Report/document calculations are disposed under P06; dashboard/report presentation remains an explicit P07 runtime gate. |
| 133 | `resources/views/superadmin/pos-master-data.blade.php` | **OWNED-P02-P03** | Catalogue/master-data/stock behavior is disposed under P02/P03; React shell presentation remains P07. |
| 134 | `resources/views/superadmin/pos-theme.blade.php` | **REPLACE-P07** | Legacy Blade editor replaced by Platform Admin or POS Portal Preferences React surface. |
| 135 | `resources/views/superadmin/report-presentation.blade.php` | **RETIRE-FRAGMENT** | Legacy Blade/Ajax presentation fragment is not copied; domain behavior stays with its owning parity family and shared React shell. |
| 136 | `resources/views/superadmin/reports.blade.php` | **OWNED-P06-P07** | Report/document calculations are disposed under P06; dashboard/report presentation remains an explicit P07 runtime gate. |
| 137 | `resources/views/superadmin/shops.blade.php` | **OWNED-P01** | Identity, account and outlet-profile behavior is disposed under P01; shared presentation shell remains P07. |
| 138 | `resources/views/welcome.blade.php` | **REPLACE-SHELL** | Legacy realm layouts replaced by the unified Admin and POS React shell. |
| 139 | `tests/Feature/PosBrandingPublishAcceptanceTest.php` | **ADAPT-TEST** | Behavior retained in current PlatformAdministrationInterfaceTest, PosShellTest, PosTransactionInterfaceTest and PosCustomerReportingInterfaceTest gates. |
| 140 | `tests/Feature/PosBrandingResolverTest.php` | **ADAPT-TEST** | Behavior retained in current PlatformAdministrationInterfaceTest, PosShellTest, PosTransactionInterfaceTest and PosCustomerReportingInterfaceTest gates. |
| 141 | `tests/Feature/PosConfigurationPermissionAuditTest.php` | **ADAPT-TEST** | Behavior retained in current PlatformAdministrationInterfaceTest, PosShellTest, PosTransactionInterfaceTest and PosCustomerReportingInterfaceTest gates. |
| 142 | `tests/Feature/PosConfigurationRevisionTest.php` | **ADAPT-TEST** | Behavior retained in current PlatformAdministrationInterfaceTest, PosShellTest, PosTransactionInterfaceTest and PosCustomerReportingInterfaceTest gates. |
| 143 | `tests/Feature/PosMediaFoundationTest.php` | **ADAPT-TEST** | Behavior retained in current PlatformAdministrationInterfaceTest, PosShellTest, PosTransactionInterfaceTest and PosCustomerReportingInterfaceTest gates. |
| 144 | `tests/Feature/PosP4FormTablePresentationTest.php` | **ADAPT-TEST** | Behavior retained in current PlatformAdministrationInterfaceTest, PosShellTest, PosTransactionInterfaceTest and PosCustomerReportingInterfaceTest gates. |
| 145 | `tests/Feature/PosP4PortalPresentationAcceptanceTest.php` | **ADAPT-TEST** | Behavior retained in current PlatformAdministrationInterfaceTest, PosShellTest, PosTransactionInterfaceTest and PosCustomerReportingInterfaceTest gates. |
| 146 | `tests/Feature/PosP4SafePortalNavigationTest.php` | **ADAPT-TEST** | Behavior retained in current PlatformAdministrationInterfaceTest, PosShellTest, PosTransactionInterfaceTest and PosCustomerReportingInterfaceTest gates. |
| 147 | `tests/Feature/PosSettingsTest.php` | **ADAPT-TEST** | Behavior retained in current PlatformAdministrationInterfaceTest, PosShellTest, PosTransactionInterfaceTest and PosCustomerReportingInterfaceTest gates. |
| 148 | `tests/Feature/PosThemeAcceptanceTest.php` | **ADAPT-TEST** | Behavior retained in current PlatformAdministrationInterfaceTest, PosShellTest, PosTransactionInterfaceTest and PosCustomerReportingInterfaceTest gates. |

## Remaining P07 gates

The inventory and route disposition is complete. P07 remains open until safe column presentation, dashboard/report presentation, full published theme/branding propagation, and final rendered role/outlet browser acceptance are independently verified. Overlapping business behavior remains owned by P01-P06 and P08 and is not reopened by this presentation crosswalk.
