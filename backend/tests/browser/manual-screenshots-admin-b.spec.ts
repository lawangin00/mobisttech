import { execFileSync } from 'node:child_process';
import { mkdirSync } from 'node:fs';
import { resolve } from 'node:path';
import { expect, Locator, Page, Route, test } from '@playwright/test';
import { runH01Fixture } from './h01-fixture';
import { releaseSyntheticAdminSession } from './synthetic-admin-session';

const password = 'SyntheticPass123!';
const assets = resolve(process.cwd(), '..', 'docs', 'user-manual', 'assets');

function artisan(seeder: string) {
    execFileSync('php', ['artisan', 'db:seed', '--class=Database\\Seeders\\' + seeder, '--env=testing', '--force'], {
        cwd: process.cwd(), stdio: 'inherit',
    });
}

async function login(page: Page, email: string) {
    await releaseSyntheticAdminSession(page);
    await page.context().clearCookies();
    await page.goto('/internal/admin/pos/login');
    await page.getByTestId('login-email').fill(email);
    await page.getByTestId('login-password').fill(password);
    await page.getByTestId('login-submit').click();
    await page.waitForURL('**/internal/admin/pos');
}

async function shot(page: Page, name: string) {
    mkdirSync(assets, { recursive: true });
    await page.screenshot({ path: resolve(assets, name), fullPage: false, animations: 'disabled' });
}

async function shotSection(locator: Locator, name: string) {
    mkdirSync(assets, { recursive: true });
    await locator.screenshot({ path: resolve(assets, name), animations: 'disabled' });
}

test.beforeAll(() => {
    artisan('W03CommerceE2eSeeder');
    runH01Fixture('H01BackupBrowserSeeder', 'MT75_H01_BACKUP_E2E_ENABLED', 'MT75_H01_BACKUP_FIXTURE_ACTION', 'seed');
});

test.afterAll(() => {
    artisan('W03CommerceE2eCleanupSeeder');
    runH01Fixture('H01BackupBrowserSeeder', 'MT75_H01_BACKUP_E2E_ENABLED', 'MT75_H01_BACKUP_FIXTURE_ACTION', 'cleanup');
});

test('MT-7.6 manual screenshot batch B1 - Website CMS legal and Software', async ({ page }) => {
    await page.setViewportSize({ width: 1440, height: 1000 });
    await login(page, 'e2e-platform@example.invalid');
    await page.goto('/internal/admin/platform');
    await expect(page.getByRole('heading', { name: 'Platform Administration' })).toBeVisible();

    const guided = page.getByRole('region', { name: 'Guided Website presentation builder' });
    await expect(guided).toBeVisible();
    await shotSection(guided, 'S15_cms-navigation-footer.png');

    const seo = page.getByRole('heading', { name: 'Global Website SEO' }).locator('xpath=ancestor::section[1]');
    await shotSection(seo, 'S16a_website-seo.png');

    await page.getByRole('button', { name: 'Content', exact: true }).click();
    const media = page.getByRole('heading', { name: 'Website media library' }).locator('xpath=ancestor::section[1]');
    await expect(media).toBeVisible();
    await shotSection(media, 'S16b_website-media.png');

    const policy = page.getByRole('heading', { name: 'Legal & policy content' }).locator('xpath=ancestor::section[1]');
    await expect(policy).toBeVisible();
    await shotSection(policy, 'S17_legal-policy.png');

    await page.getByRole('button', { name: 'Software', exact: true }).click();
    await expect(page.getByRole('heading', { name: 'Software create/edit' })).toBeVisible();
    await shot(page, 'S21_software-product.png');
});

test('MT-7.6 manual screenshot batch B1b - populated media and Software contract', async ({ page }) => {
    await page.setViewportSize({ width: 1440, height: 1000 });
    await login(page, 'e2e-platform@example.invalid');
    await page.route('**/internal/admin/platform/data', async (route) => {
        const upstream = await route.fetch();
        const body = await upstream.json() as { data: Record<string, unknown> };
        body.data.media = [{
            id: 9051, original_name: 'synthetic-manual-hero.webp', mime_type: 'image/webp', extension: 'webp',
            byte_size: 2048, width: 1200, height: 600, sha256: 'a'.repeat(64), alt_text: 'Synthetic manual Website media',
            status: 'active', created_at: '2026-09-25',
        }];
        body.data.software = [{
            id: 'software-manual-1', name: 'Synthetic Manual Software', slug: 'synthetic-manual-software', status: 'draft', archived_at: null,
            current_revision_id: 9061, current_revision_version: 1, current_version: '0.9.0',
            revisions: [{ id: 9061, version: 1, state: 'draft', snapshot: {
                name: 'Synthetic Manual Software', slug: 'synthetic-manual-software', summary: 'Safe synthetic Software Product example',
                overview: '<p>Synthetic overview for manual screenshot.</p>',
                features: [{ title: 'Versioned publishing', description: 'Synthetic verified feature example' }],
                platforms: ['Windows 11 x64'], system_requirements: '<p>Windows 11</p>', limitations: [],
                support: { channel: 'support' }, cta: { type: 'contact' },
                privacy: '<p>Synthetic privacy draft</p>', terms: '<p>Synthetic terms draft</p>',
                faq: [{ question: 'How is this used?', answer: '<p>Manual screenshot only.</p>' }],
                screenshot_media_ids: [9051], sitemap: true,
            } }],
            releases: [{ id: 9071, public_id: 'release-manual-1', version: '0.9.0', state: 'draft', release_date: '2026-09-25',
                summary: 'Synthetic draft release', notes: { added: ['Manual example'], fixed: [] },
                impact_review: { privacy: 'reviewed_no_change', terms: 'reviewed_no_change', faq: 'not_affected', documentation: 'reviewed_updated' } }],
        }];
        await route.fulfill({ response: upstream, json: body });
    });
    await page.goto('/internal/admin/platform');
    await expect(page.getByRole('heading', { name: 'Platform Administration' })).toBeVisible();

    await page.getByRole('button', { name: 'Content', exact: true }).click();
    const media = page.getByRole('heading', { name: 'Website media library' }).locator('xpath=ancestor::section[1]');
    await expect(media).toContainText('synthetic-manual-hero.webp');
    await shotSection(media, 'S16b_website-media.png');

    await page.getByRole('button', { name: 'Software', exact: true }).click();
    const software = page.getByRole('heading', { name: 'Software create/edit' }).locator('xpath=ancestor::section[1]');
    await software.locator('select').first().selectOption('software-manual-1');
    await expect(software.getByPlaceholder('Product name')).toHaveValue('Synthetic Manual Software');
    await expect(page.getByRole('heading', { name: 'Publish, rollback, release & canonical slug' }).locator('xpath=ancestor::section[1]')).toContainText('0.9.0');
    await shot(page, 'S21_software-product.png');
});

test('MT-7.6 manual screenshot batch B2 - Website commerce and payment settings', async ({ page }) => {
    await page.setViewportSize({ width: 1440, height: 1000 });
    await login(page, 'e2e-protected-owner@example.invalid');
    expect((await page.goto('/internal/admin/website-commerce'))?.status()).toBe(200);
    await expect(page.getByRole('heading', { name: 'Website commerce administration' })).toBeVisible();
    await expect(page.getByText('MT75-W03-ORDER', { exact: true })).toBeVisible();
    await shot(page, 'S18_website-commerce.png');

    await login(page, 'e2e-w04-payment-editor@example.invalid');
    expect((await page.goto('/internal/admin/website/payment-settings'))?.status()).toBe(200);
    await expect(page.getByRole('heading', { name: 'Payment labels, instructions and COD limits' })).toBeVisible();
    await expect(page.getByRole('rowheader', { name: 'Cash on Delivery' })).toBeVisible();
    await shot(page, 'S19_website-payment-settings.png');
});

test('MT-7.6 manual screenshot batch B3 - Digital Operations populated project view', async ({ page }) => {
    const data = {
        identity: { name: 'E2E Digital Operations Manager', job_title: 'Digital Operations Manager' },
        permissions: [
            'website.services.manage', 'website.consultations.manage', 'website.digital-leads.manage',
            'website.digital-projects.manage', 'website.proposals.approve', 'website.client-files.manage',
            'website.conversions.view',
        ],
        website_mode: { mode: 'digital_only' },
        mode_history: [{ id: 1, version: 2, state: 'published', mode: 'digital_only', published_at: '2026-09-18', created_at: '2026-09-18' }],
        services: [{
            slug: 'web-development', name: 'Web Development', category: 'development',
            short_description: 'Synthetic Website build', description: 'Synthetic digital project service',
            price_type: 'package', price: null, is_active: true, sort_order: 1,
            packages: [{ public_id: 'package-1', code: 'starter', name: 'Starter', pricing_type: 'fixed', price: '50000.00', active: true, sort_order: 1, version: 1 }],
            addons: [],
        }],
        consultation: { enabled: true, timezone: 'Asia/Karachi', weekly_availability: [{ day: 1, start: '09:00', end: '17:00' }], version: 1, external_calendar: { enabled: false, provider: null } },
        leads: [],
        projects: [{
            public_id: 'project-1', reference: 'PRJ-MANUAL-001', title: 'Synthetic Client Website', status: 'approved', version: 3,
            service_slug: 'web-development', service_name: 'Web Development', lead_public_id: 'lead-1', lead_reference: 'SR-MANUAL-001',
            customer_account_public_id: 'customer-1', customer_name: 'Synthetic Client', customer_email: 'client@example.invalid',
            customer_mobile: '03000000000', assigned_admin_public_id: 'owner-1', assigned_admin_name: 'Digital Owner',
            created_at: '2026-09-18', updated_at: '2026-09-18',
        }],
        lead_owners: [], project_owners: [{ id: 'owner-1', name: 'Digital Owner', job_title: 'Manager' }],
        customers: [{ id: 'customer-1', name: 'Synthetic Client', email: 'client@example.invalid', mobile: '03000000000' }],
    };
    const project = {
        public_id: 'project-1', reference: 'PRJ-MANUAL-001', title: 'Synthetic Client Website', status: 'approved', version: 3,
        service: { slug: 'web-development', name: 'Web Development' },
        proposals: [{
            public_id: 'proposal-1', revision: 1, state: 'approved', title: 'Synthetic Proposal', amount: '100000.00',
            currency: 'PKR', valid_until: '2026-10-01T00:00:00Z', scope: 'Synthetic project scope for manual screenshot.',
            deliverables: ['Website', 'Handover'],
            schedule: [{ kind: 'deposit', label: 'Deposit', amount: '40000.00', due_at: null }, { kind: 'final', label: 'Final', amount: '60000.00', due_at: null }],
            snapshot_sha256: 'b'.repeat(64), quote: null,
            milestones: [
                { id: 'milestone-1', sequence: 1, kind: 'deposit', label: 'Deposit', amount: '40000.00', currency: 'PKR', due_at: null, paid_at: null, payment_status: 'unpaid', payable: true },
                { id: 'milestone-2', sequence: 2, kind: 'final', label: 'Final', amount: '60000.00', currency: 'PKR', due_at: null, paid_at: null, payment_status: 'unpaid', payable: false },
            ],
        }],
        files: [{ id: 'file-1', type: 'reference', name: 'synthetic-brief.pdf', mime_type: 'application/pdf', byte_size: 4096, sha256: 'c'.repeat(64), retention_until: '2028-09-18', created_at: '2026-09-18' }],
        history: [{ type: 'project_created', snapshot: {}, occurred_at: '2026-09-18T08:00:00Z' }],
        customer_account_public_id: 'customer-1', assigned_admin_public_id: 'owner-1',
    };

    const fulfill = async (route: Route, payload: unknown) => route.fulfill({
        status: 200, contentType: 'application/json', body: JSON.stringify({ data: payload }),
    });
    await page.route('**/internal/admin/digital-operations/**', async (route) => {
        const request = route.request();
        const path = new URL(request.url()).pathname;
        if (path === '/internal/admin/digital-operations') { await route.fallback(); return; }
        if (path === '/internal/admin/digital-operations/data' && request.method() === 'GET') { await fulfill(route, data); return; }
        if (path === '/internal/admin/digital-operations/projects/project-1' && request.method() === 'GET') { await fulfill(route, project); return; }
        await route.fallback();
    });

    await page.setViewportSize({ width: 1440, height: 1000 });
    await login(page, 'e2e-digital-operations@example.invalid');
    await page.goto('/internal/admin/digital-operations');
    await expect(page.getByRole('heading', { name: 'Digital Operations' })).toBeVisible();
    await page.getByRole('button', { name: 'Projects', exact: true }).click();
    await page.getByRole('button', { name: /PRJ-MANUAL-001/ }).click();
    await expect(page.getByRole('heading', { name: /PRJ-MANUAL-001/ })).toBeVisible();
    await shot(page, 'S20_digital-operations.png');
});

test('MT-7.6 manual screenshot batch B4 - integrations and backup', async ({ page }) => {
    await page.setViewportSize({ width: 1440, height: 1000 });
    await login(page, 'e2e-protected-owner@example.invalid');
    expect((await page.goto('/internal/admin/settings/integrations'))?.status()).toBe(200);
    await expect(page.getByRole('heading', { name: 'Google integrations' })).toBeVisible();
    await shot(page, 'S22_integrations.png');

    const history = page.getByTestId('backup-operator-history');
    await history.getByRole('button', { name: 'Show backup history' }).click();
    await expect(history.locator('[data-testid^="backup-record-"]')).toHaveCount(1);
    await shotSection(history, 'S23_backup.png');
});

test('MT-7.6 manual screenshot batch B5 - guarded Data Reset preview', async ({ page }) => {
    const factoryDomains = ['commerce','claims','repairs','loyalty_promotions','cash','digital_projects','service_requests','notification_delivery','inventory','procurement','trade_in','customers','website_content','digital_catalogue','engagement','factory_configuration'];
    const data = {
        identity: { name: 'E2E Reset Administrator', job_title: 'Reset Administrator' },
        levels: {
            transactional: { label: 'Transactional Data Reset', description: 'Clear selected transactional data.', permission: 'system.reset.transactional', can_execute: true, confirmation_required: 'TRANSACTIONAL DATA RESET', available_domains: ['service_requests'], factory_scope_locked: false },
            factory: { label: 'Factory Reset', description: 'Clear full approved factory scope.', permission: 'system.reset.factory', can_execute: true, confirmation_required: 'FACTORY RESET', available_domains: factoryDomains, factory_scope_locked: true },
        },
        domain_labels: Object.fromEntries(factoryDomains.map((d) => [d, d.replaceAll('_', ' ')])),
        recent_authentication: true,
        preview_ttl_minutes: 10,
        execution_enabled_here: false,
        production_hold: true,
        minimum_bootstrap: {
            access_tables: ['admins','outlets','outlet_admins','permission_definitions','roles','role_permissions','admin_roles'],
            recovery_evidence_tables: ['reset_operations','backup_restore_rehearsals','backup_manifests','backup_records','admin_audit_logs'],
            note: 'Authorized bootstrap and durable recovery evidence are preserved.',
        },
        operations: [], audit: [],
    };
    const preview = {
        public_id: 'preview-manual', level: 'transactional', domains: ['service_requests'],
        domain_counts: { service_requests: { tables: ['service_requests','service_request_files'], record_count: 4, file_count: 1 } },
        record_count: 4, file_count: 1, barriers: [],
        preservation: [
            { group: 'transactional', action: 'selected_clear', tables: ['service_requests','service_request_files'] },
            { group: 'bootstrap', action: 'preserve', tables: ['admins','roles'] },
        ],
        preview_sha256: 'p'.repeat(64), confirmation_required: 'TRANSACTIONAL DATA RESET', execution_enabled_here: false,
    };
    const fulfill = async (route: Route, payload: unknown) => route.fulfill({
        status: 200, contentType: 'application/json', body: JSON.stringify({ data: payload }),
    });
    await page.route('**/internal/admin/reset-administration/**', async (route) => {
        const request = route.request();
        const path = new URL(request.url()).pathname;
        if (path === '/internal/admin/reset-administration') { await route.fallback(); return; }
        if (path === '/internal/admin/reset-administration/data' && request.method() === 'GET') { await fulfill(route, data); return; }
        if (path === '/internal/admin/reset-administration/preview' && request.method() === 'POST') { await fulfill(route, preview); return; }
        await route.fallback();
    });

    await page.setViewportSize({ width: 1440, height: 1000 });
    await login(page, 'e2e-reset@example.invalid');
    await page.goto('/internal/admin/reset-administration');
    await expect(page.getByRole('heading', { name: 'Data Reset Administration' })).toBeVisible();
    const hold = page.getByRole('heading', { name: 'Production reset HOLD' }).locator('xpath=ancestor::section[1]');
    await shotSection(hold, 'S24a_reset-production-hold.png');
    await page.getByRole('button', { name: /Transactional Data Reset/ }).click();
    await page.getByLabel(/service requests/i).check();
    await page.getByRole('button', { name: 'Create dry-run preview' }).click();
    const dryRun = page.getByRole('heading', { name: /Dry-run preview/ }).locator('xpath=ancestor::section[1]');
    await expect(dryRun).toBeVisible();
    await shotSection(dryRun, 'S24b_reset-dry-run-preview.png');
});
