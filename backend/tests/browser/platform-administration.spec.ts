import { expect, Page, Route, test } from '@playwright/test';
import { releaseSyntheticAdminSession } from './synthetic-admin-session';

test.afterEach(async ({ page }) => {
    await releaseSyntheticAdminSession(page);
});

const password = 'SyntheticPass123!';

async function login(page: Page) {
    await page.goto('/internal/admin/pos/login');
    await page.getByTestId('login-email').fill('e2e-platform@example.invalid');
    await page.getByTestId('login-password').fill(password);
    await page.getByTestId('login-submit').click();
    await page.waitForURL('**/internal/admin/pos');
    await page.goto('/internal/admin/platform');
    await expect(page.getByRole('heading', { name: 'Platform Administration' })).toBeVisible();
}

test('MT-4.4 platform administration delegates protected CMS POS team payment integration and software workflows', async ({ page }) => {
    test.setTimeout(90_000);
    const calls: Array<{ path: string; method: string; body: Record<string, unknown> | null }> = [];
    const permissions = [
        'shops.enter',
        'config.documents.manage', 'config.theme.manage', 'config.branding.manage', 'config.publish', 'config.payments.manage',
        'config.promotions.manage', 'config.loyalty.manage',
        'admin.business-profile.manage', 'admin.integrations.manage',
        'website.content.manage', 'website.publish', 'website.settings.manage', 'website.mode.preview', 'website.mode.publish',
        'website.media.manage', 'website.theme.manage', 'website.branding.manage', 'website.navigation.manage', 'website.seo.manage',
        'team-members.view', 'team-members.manage', 'team-members.roles.manage', 'team-members.full-access.assign',
    ];
    const data = {
        identity: { name: 'E2E Platform Administrator', job_title: 'Platform Administrator' },
        permissions,
        outlet: { id: 'e2e-platform-outlet', name: 'E2E Sales Outlet' },
        website_mode: { mode: 'hybrid' },
        mode_revisions: [{ id: 11, version: 3, state: 'draft', mode: 'digital_only', published_at: null, created_at: '2026-09-18 08:00:00' }],
        policies: [],
        policy_history: [{ id: 21, policy_type: 'privacy', version: 2, state: 'draft', effective_date: '2026-09-18', approval_state: 'owner_approved', factual_review_state: 'verified', published_at: null, created_at: '2026-09-18' }],
        templates: [
            ['invoice_whatsapp', 'invoice', 'whatsapp', 'body', 'Invoice WhatsApp {{invoice_number}}'],
            ['warranty_whatsapp', 'warranty', 'whatsapp', 'body', 'Warranty WhatsApp {{claim_number}}'],
            ['invoice_email_subject', 'invoice', 'email', 'subject', 'Invoice {{invoice_number}}'],
            ['invoice_email_body', 'invoice', 'email', 'body', 'Invoice body'],
            ['warranty_email_subject', 'warranty', 'email', 'subject', 'Warranty {{claim_number}}'],
            ['warranty_email_body', 'warranty', 'email', 'body', 'Warranty body'],
        ].map((row, index) => ({
            template_id: 'template-' + (index + 1), template_key: row[0], version: 1,
            document_type: row[1], channel: row[2], template_part: row[3], template_text: row[4],
        })),
        presentation_revisions: [{ id: 31, version: 2, state: 'draft', snapshot: { theme: { accent: '#008080' }, navigation: ['home', 'software'] }, published_at: null, created_at: '2026-09-18' }],
        pages: [{
            id: 'page-1', title: 'E2E Case Study', slug: 'e2e-case-study', state: 'draft',
            content_purpose: 'case_study', capability_scope: 'digital', current_revision_id: 41, current_revision_version: 1,
            revisions: [{ id: 41, version: 1, state: 'draft', snapshot: { title: 'E2E Case Study', slug: 'e2e-case-study', content: '<p>Case study</p>', content_purpose: 'case_study', capability_scope: 'digital', structured_content: { outcome: 'verified' }, service_slugs: ['e2e-service'] } }],
        }],
        media: [{ id: 51, original_name: 'e2e-site.webp', mime_type: 'image/webp', extension: 'webp', byte_size: 1024, width: 1200, height: 600, sha256: 'a'.repeat(64), alt_text: 'E2E Website media', status: 'active', created_at: '2026-09-18' }],
        software: [{
            id: 'software-1', name: 'E2E Software', slug: 'e2e-software', status: 'draft', archived_at: null,
            current_revision_id: 61, current_revision_version: 1, current_version: '0.9.0',
            revisions: [{ id: 61, version: 1, state: 'draft', snapshot: {
                name: 'E2E Software', slug: 'e2e-software', summary: 'E2E summary', overview: '<p>E2E overview</p>',
                features: [{ title: 'Feature', description: 'Verified feature' }], platforms: ['Windows 11 x64'],
                system_requirements: '<p>Windows 11</p>', limitations: [], support: { channel: 'support' }, cta: { type: 'contact' },
                privacy: '<p>Privacy</p>', terms: '<p>Terms</p>', faq: [{ question: 'Q?', answer: '<p>A</p>' }],
                screenshot_media_ids: [], sitemap: true,
            } }],
            releases: [{ id: 71, public_id: 'release-1', version: '0.9.0', state: 'draft', release_date: '2026-09-18', summary: 'Initial draft release', notes: { added: ['E2E release feature'], fixed: ['E2E bug fix'] }, impact_review: { privacy: 'reviewed_no_change' } }],
        }],
        team_members: [{ id: 'member-1', name: 'E2E Teammate', email: 'teammate@example.invalid', job_title: 'Editor', status: 'active', roles: [{ id: 'role-1', name: 'E2E Editor' }], outlets: [{ id: 'e2e-platform-outlet', name: 'E2E Sales Outlet' }] }],
        roles: [
            { id: 'role-1', name: 'E2E Editor', system: false, protected: false, permissions: ['website.content.manage'] },
            { id: 'role-full', name: 'Full Access', system: true, protected: true, permissions },
        ],
        permission_catalogue: permissions.map((code) => ({ code, label: code })),
        assignable_outlets: [{ id: 'e2e-platform-outlet', name: 'E2E Sales Outlet' }],
        integrations: [{ provider: 'gmail', status: 'connected', account: 'mobisttech@gmail.com' }],
        website_credentials: [],
        payment_destinations: [{ destination_id: 'destination-1', method: 'bank_transfer', display_name: 'E2E Bank', provider_label: 'Bank', masked_identifier: '****4400', active: true, version: 2 }],
        pos_configuration: {
            domains: {
                documents: {
                    definitions: {
                        'invoice.default_output_format': { domain: 'documents', group: 'invoice', label: 'Default invoice output', type: 'string', input: 'select', default: 'thermal', sort: 101, options: { thermal: 'Thermal receipt (80mm)', a4: 'A4 document' } },
                        'invoice.show_customer_cnic': { domain: 'documents', group: 'invoice', label: 'Show customer CNIC on receipt', type: 'boolean', input: 'boolean', default: true, sort: 30 },
                    },
                    values: { 'invoice.default_output_format': 'thermal', 'invoice.show_customer_cnic': true },
                    revisions: [{ id: 81, domain: 'documents', version: 1, state: 'draft', snapshot: { 'invoice.default_output_format': 'a4', 'invoice.show_customer_cnic': true }, published_at: null, restored_from_revision_id: null, created_at: '2026-09-18' }],
                },
                theme: {
                    definitions: {
                        'theme.primary': { domain: 'theme', group: 'theme', label: 'Primary', type: 'color', input: 'color', default: '#008080', sort: 181 },
                        'theme.surface': { domain: 'theme', group: 'theme', label: 'Surface', type: 'color', input: 'color', default: '#ffffff', sort: 186 },
                        'theme.text': { domain: 'theme', group: 'theme', label: 'Text', type: 'color', input: 'color', default: '#111827', sort: 187 },
                    },
                    values: { 'theme.primary': '#008080', 'theme.surface': '#ffffff', 'theme.text': '#111827' },
                    revisions: [],
                },
                branding: {
                    definitions: {
                        'branding.app_icon_media_id': { domain: 'branding', group: 'branding', label: 'App icon', type: 'integer', input: 'media', default: 0, sort: 290 },
                        'branding.header_logo_media_id': { domain: 'branding', group: 'branding', label: 'Portal header logo', type: 'integer', input: 'media', default: 0, sort: 300 },
                    },
                    values: { 'branding.app_icon_media_id': 0, 'branding.header_logo_media_id': 91 },
                    revisions: [],
                },
            },
            branding_media: [{ id: 91, original_name: 'e2e-brand.webp', mime_type: 'image/webp', byte_size: 2048, width: 1200, height: 600, aspect_ratio: '2.000000', sha256: 'b'.repeat(64), alt_text: 'E2E Brand', status: 'active', created_at: '2026-09-18' }],
        },
        business_profile: { business_name: 'mobiST Technologies', business_email: 'mobisttech@gmail.com', public_website: 'https://mobisttech.com', version: 1 },
        promotions: [{ public_id: 'promo-1', name: 'E2E Coupon', mode: 'coupon', code: 'E2E10', discount_type: 'fixed', discount_value: '10.00', max_discount: null, min_subtotal: '0.00', starts_at: null, ends_at: null, usage_limit: null, per_customer_limit: null, customer_required: false, stackable: false, priority: 100, status: 'active', version: 1, outlet_public_id: 'e2e-platform-outlet', outlet_name: 'E2E Sales Outlet' }],
        loyalty: { public_id: 'loyalty-1', version: 1, enabled: true, earn_basis_amount: '100.00', earn_points: 10, redemption_value: '1.00', min_redeem_points: 1, max_redeem_points: 100, daily_redeem_points: 100, expiry_days: 30 },
    };

    const fulfill = async (route: Route, payload: unknown = {}) => route.fulfill({
        status: 200, contentType: 'application/json', body: JSON.stringify({ data: payload }),
    });
    const capture = async (route: Route, payload: unknown = {}) => {
        const request = route.request();
        let body: Record<string, unknown> | null = null;
        try { body = request.postDataJSON() as Record<string, unknown>; } catch { body = null; }
        calls.push({ path: new URL(request.url()).pathname, method: request.method(), body });
        await fulfill(route, payload);
    };

    await page.route('**/internal/admin/platform/**', async (route) => {
        const request = route.request();
        const path = new URL(request.url()).pathname;
        if (path === '/internal/admin/platform') {
            await route.fallback();
            return;
        }
        if (path === '/internal/admin/platform/data' && request.method() === 'GET') {
            await fulfill(route, data);
            return;
        }
        if (path.match(/\/website-mode\/\d+\/preview$/) && request.method() === 'GET') {
            await capture(route, { mode: 'digital_only', capabilities: { commerce: false, digital_services: true }, sitemap: { changed: true } });
            return;
        }
        await capture(route, { id: 'e2e-result', state: 'draft', version: 1 });
    });
    await page.route('**/internal/admin/team-members/**', async (route) => capture(route, { id: 'member-1' }));
    await page.route('**/internal/admin/team-members', async (route) => capture(route, { id: 'member-new' }));
    await page.route('**/internal/admin/roles/**', async (route) => capture(route, { id: 'role-1' }));
    await page.route('**/internal/admin/roles', async (route) => capture(route, { id: 'role-new' }));

    await page.setViewportSize({ width: 1440, height: 900 });
    await login(page);

    const websiteMode = page.getByRole('heading', { name: 'Website operating mode' }).locator('xpath=ancestor::section[1]');
    await expect(websiteMode).toContainText('hybrid');
    await websiteMode.locator('select').selectOption('digital_only');
    await websiteMode.getByRole('button', { name: 'Save mode draft' }).click();
    await expect.poll(() => calls.some((call) => call.path.endsWith('/website-mode/draft') && call.body?.mode === 'digital_only')).toBe(true);
    await websiteMode.getByRole('button', { name: 'Preview impact' }).click();
    const modePreview = page.getByRole('heading', { name: 'Mode preview / publish impact' }).locator('xpath=ancestor::section[1]');
    await expect(modePreview).toContainText('digital_services');

    const presentation = page.getByRole('heading', { name: /Website presentation/ }).locator('xpath=ancestor::section[1]');
    await presentation.locator('textarea').fill('{"theme":{"accent":"#008080"},"navigation":["home","software"]}');
    await presentation.getByRole('button', { name: 'Save presentation draft' }).click();
    await expect.poll(() => calls.some((call) => call.path.endsWith('/presentation/draft'))).toBe(true);

    await page.getByRole('button', { name: 'Content' }).click();
    const pages = page.getByRole('heading', { name: 'Managed pages & digital content' }).locator('xpath=ancestor::section[1]');
    await pages.locator('select').first().selectOption('page-1');
    await pages.getByPlaceholder('Page title').fill('E2E Case Study Updated');
    await pages.getByRole('button', { name: 'Save page draft' }).click();
    await expect.poll(() => calls.some((call) => call.path.endsWith('/pages/draft') && call.body?.title === 'E2E Case Study Updated')).toBe(true);
    const siteMedia = page.getByRole('heading', { name: 'Website media library' }).locator('xpath=ancestor::section[1]');
    await expect(siteMedia).toContainText('e2e-site.webp');
    await siteMedia.locator('input[type="file"]').first().setInputFiles({
        name: 'browser-site.png', mimeType: 'image/png',
        buffer: Buffer.from('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAusB9Wl2nS0AAAAASUVORK5CYII=', 'base64'),
    });
    await expect.poll(() => calls.some((call) => call.path.endsWith('/platform/media'))).toBe(true);
    const policy = page.getByRole('heading', { name: 'Legal & policy content' }).locator('xpath=ancestor::section[1]');
    await expect(policy.getByLabel('Policy approval state')).toHaveValue('draft');
    await expect(policy.getByLabel('Policy factual review state')).toHaveValue('pending');
    await policy.getByPlaceholder('Policy text').fill('<p>E2E unreviewed privacy policy draft.</p>');
    await policy.getByRole('button', { name: 'Save policy draft' }).click();
    await expect.poll(() => calls.some((call) => call.path.includes('/policies/privacy/draft')
        && call.body?.approval_state === 'draft'
        && call.body?.factual_review_state === 'pending'
        && Array.isArray(call.body?.unresolved_decisions))).toBe(true);
    await policy.getByLabel('Policy approval state').selectOption('owner_approved');
    await policy.getByLabel('Policy factual review state').selectOption('verified');
    await policy.getByPlaceholder('Policy text').fill('<p>E2E synthetically reviewed policy draft.</p>');
    await policy.getByLabel('Professional review reference').fill('synthetic-browser-review');
    await policy.getByRole('button', { name: 'Save policy draft' }).click();
    await expect.poll(() => calls.some((call) => call.path.includes('/policies/privacy/draft')
        && call.body?.approval_state === 'owner_approved'
        && call.body?.factual_review_state === 'verified'
        && call.body?.professional_review_reference === 'synthetic-browser-review')).toBe(true);
    const policyHistory = page.getByRole('heading', { name: 'Policy revisions' }).locator('xpath=ancestor::section[1]');
    await policyHistory.getByRole('button', { name: 'Publish' }).click();
    await expect.poll(() => calls.some((call) => call.path.endsWith('/policies/21/publish'))).toBe(true);
    // A newly saved unreviewed policy draft cannot be presented as publish-ready.
    data.policy_history[0].approval_state = 'draft';
    data.policy_history[0].factual_review_state = 'pending';
    await page.reload();
    await page.getByRole('button', { name: 'Content', exact: true }).click();
    await expect(policyHistory.getByRole('button', { name: 'Publish' })).toBeDisabled();
    data.policy_history[0].approval_state = 'owner_approved';
    data.policy_history[0].factual_review_state = 'verified';
    await page.reload();
    await page.getByRole('button', { name: 'Content', exact: true }).click();
    await expect(policyHistory.getByRole('button', { name: 'Publish' })).toBeEnabled();

    await page.getByRole('button', { name: 'POS configuration' }).click();
    const documents = page.getByRole('heading', { name: 'POS document & output defaults' }).locator('xpath=ancestor::section[1]');
    await documents.locator('select').selectOption('a4');
    await documents.getByRole('button', { name: 'Preview' }).click();
    await documents.getByRole('button', { name: 'Save draft revision' }).click();
    await expect.poll(() => calls.some((call) => call.path.endsWith('/pos-config/documents/draft')
        && (call.body?.settings as Record<string, unknown>)?.['invoice.default_output_format'] === 'a4')).toBe(true);
    await documents.getByRole('button', { name: 'Publish' }).click();
    await expect.poll(() => calls.some((call) => call.path.endsWith('/pos-config/revisions/81/publish'))).toBe(true);

    const theme = page.getByRole('heading', { name: 'POS theme' }).locator('xpath=ancestor::section[1]');
    await theme.locator('input[type="color"]').first().fill('#007176');
    await theme.getByRole('button', { name: 'Save draft revision' }).click();
    await expect.poll(() => calls.some((call) => call.path.endsWith('/pos-config/theme/draft'))).toBe(true);

    const branding = page.getByRole('heading', { name: 'POS branding assets' }).locator('xpath=ancestor::section[1]');
    await branding.locator('select').first().selectOption('91');
    await branding.locator('input[type="file"]').setInputFiles({
        name: 'browser-brand.webp', mimeType: 'image/webp',
        buffer: Buffer.from('UklGRiIAAABXRUJQVlA4IBYAAAAwAQCdASoBAAEAAUAmJaQAA3AA/v89WAAAAA==', 'base64'),
    });
    await expect.poll(() => calls.some((call) => call.path.endsWith('/pos-config/branding/media'))).toBe(true);
    await branding.getByRole('button', { name: 'Save branding draft' }).click();
    await expect.poll(() => calls.some((call) => call.path.endsWith('/pos-config/branding/draft'))).toBe(true);

    const profile = page.getByRole('heading', { name: 'Canonical Business Profile' }).locator('xpath=ancestor::section[1]');
    await expect(profile).toContainText('Version 1');
    await profile.getByRole('button', { name: 'Save canonical profile' }).click();
    await expect.poll(() => calls.some((call) => call.path.endsWith('/business-profile') && call.method === 'PATCH')).toBe(true);

    await page.getByRole('button', { name: 'Documents, payments & retail' }).click();
    const templates = page.getByRole('heading', { name: 'Invoice/Warranty communication templates' }).locator('xpath=ancestor::section[1]');
    await expect(templates.getByRole('button', { name: 'Save new revision' })).toHaveCount(6);
    await templates.locator('textarea').first().fill('Updated invoice WhatsApp {{invoice_number}}');
    await templates.getByRole('button', { name: 'Save new revision' }).first().click();
    await expect.poll(() => calls.some((call) => call.path.endsWith('/templates/invoice_whatsapp'))).toBe(true);

    const destinations = page.getByRole('heading', { name: 'POS Payment Destinations' }).locator('xpath=ancestor::section[1]');
    await destinations.locator('input').nth(2).fill('E2E Bank Updated');
    await destinations.locator('input').nth(3).fill('****4499');
    await destinations.getByRole('button', { name: 'Save destination' }).click();
    await expect.poll(() => calls.some((call) => call.path.endsWith('/payment-destinations/destination-1')
        && call.body?.display_name === 'E2E Bank Updated' && call.body?.masked_identifier === '****4499')).toBe(true);

    const promotions = page.getByRole('heading', { name: 'Promotions & coupons' }).locator('xpath=ancestor::section[1]');
    await promotions.locator('select').first().selectOption('promo-1');
    await promotions.getByPlaceholder('Promotion name').fill('E2E Coupon Updated');
    await promotions.getByRole('button', { name: 'Save promotion' }).click();
    await expect.poll(() => calls.some((call) => call.path.endsWith('/promotions') && call.body?.name === 'E2E Coupon Updated')).toBe(true);
    const loyalty = page.getByRole('heading', { name: 'Optional loyalty settings' }).locator('xpath=ancestor::section[1]');
    await loyalty.getByRole('button', { name: 'Save loyalty configuration' }).click();
    await expect.poll(() => calls.some((call) => call.path.endsWith('/loyalty') && call.method === 'PUT')).toBe(true);

    await page.getByRole('button', { name: 'Team & integrations' }).click();
    const team = page.getByRole('heading', { name: 'Team Member administration' }).locator('xpath=ancestor::section[1]');
    await team.locator('select').first().selectOption('member-1');
    await team.getByRole('button', { name: 'Save Team Member' }).click();
    await expect.poll(() => calls.some((call) => call.path.endsWith('/team-members/member-1') && call.method === 'PATCH')).toBe(true);
    const roles = page.getByRole('heading', { name: 'Custom Roles' }).locator('xpath=ancestor::section[1]');
    await roles.getByPlaceholder('New role name').fill('E2E Browser Role');
    await roles.locator('input[type="checkbox"]').first().check();
    await roles.getByRole('button', { name: 'Create custom role' }).click();
    await expect.poll(() => calls.some((call) => call.path.endsWith('/internal/admin/roles') && call.method === 'POST')).toBe(true);
    const integrations = page.getByRole('heading', { name: 'Google / integration status' }).locator('xpath=ancestor::section[1]');
    await expect(integrations).toContainText('gmail:');
    await expect(integrations).toContainText('connected');
    await expect(integrations.getByRole('link', { name: 'Open integration management' })).toHaveAttribute('href', '/internal/admin/settings/integrations');

    await page.getByRole('button', { name: 'Software' }).click();
    const editor = page.getByRole('heading', { name: 'Software create/edit' }).locator('xpath=ancestor::section[1]');
    await editor.locator('select').first().selectOption('software-1');
    await editor.getByText('Preview selected revision routes').click();
    const softwarePreview = editor.getByRole('region', { name: 'Protected software route preview' });
    await expect(softwarePreview.getByText('Protected preview')).toBeVisible();
    await expect(softwarePreview.getByText('E2E overview')).toBeVisible();
    await softwarePreview.getByRole('button', { name: 'Privacy' }).click();
    await expect(softwarePreview.getByRole('heading', { name: 'E2E Software Privacy' })).toBeVisible();
    await expect(softwarePreview.getByText('Privacy', { exact: true }).last()).toBeVisible();
    await softwarePreview.getByRole('button', { name: 'Terms' }).click();
    await expect(softwarePreview.getByText('Terms', { exact: true }).last()).toBeVisible();
    await softwarePreview.getByRole('button', { name: 'Faq' }).click();
    await expect(softwarePreview.getByText('Q?')).toBeVisible();
    await softwarePreview.getByRole('button', { name: 'Releases' }).click();
    await expect(softwarePreview.getByText('Initial draft release')).toBeVisible();
    await expect(softwarePreview.getByText('E2E release feature')).toBeVisible();
    await expect(softwarePreview.getByText('E2E bug fix')).toBeVisible();
    await expect(softwarePreview.getByText('Documentation impact review: 1 recorded fields')).toBeVisible();
    await editor.getByRole('button', { name: 'Save new draft revision' }).click();
    await expect.poll(() => calls.some((call) => call.path.endsWith('/software/software-1/draft') && call.body?.name === 'E2E Software')).toBe(true);
    const release = page.getByRole('heading', { name: 'Publish, rollback, release & canonical slug' }).locator('xpath=ancestor::section[1]');
    await release.getByRole('button', { name: 'Publish draft v1' }).click();
    await expect.poll(() => calls.some((call) => call.path.endsWith('/software/revisions/61/publish'))).toBe(true);
    await release.getByPlaceholder('1.0.0').fill('1.0.0');
    await release.getByPlaceholder('Customer-readable release summary').fill('E2E stable release');
    await release.getByRole('button', { name: 'Save release draft' }).click();
    await expect.poll(() => calls.some((call) => call.path.endsWith('/software/software-1/releases')
        && call.body?.version === '1.0.0' && typeof call.body?.impact_review === 'object')).toBe(true);
    await release.getByPlaceholder('new-canonical-slug').fill('e2e-software-v2');
    await release.getByPlaceholder('Approved reason').fill('Approved product naming update');
    await release.getByRole('button', { name: 'Change canonical slug' }).click();
    await expect.poll(() => calls.some((call) => call.path.endsWith('/software/software-1/slug') && call.body?.reason === 'Approved product naming update')).toBe(true);

    await page.setViewportSize({ width: 390, height: 844 });
    for (const tab of ['Website', 'Content', 'POS configuration', 'Documents, payments & retail', 'Team & integrations', 'Software']) {
        await page.getByRole('button', { name: tab, exact: true }).click();
        const overflow = await page.evaluate(() => document.documentElement.scrollWidth > window.innerWidth);
        expect(overflow).toBe(false);
    }

    expect(calls.some((call) => call.path.includes('/website-mode/draft'))).toBe(true);
    expect(calls.some((call) => call.path.includes('/pages/draft'))).toBe(true);
    expect(calls.some((call) => call.path.includes('/pos-config/documents/draft'))).toBe(true);
    expect(calls.some((call) => call.path.includes('/payment-destinations/destination-1'))).toBe(true);
    expect(calls.some((call) => call.path.includes('/team-members/member-1'))).toBe(true);
    expect(calls.some((call) => call.path.includes('/software/software-1/releases'))).toBe(true);
    // Backend reserves publication, archive and canonical slug changes for publishers.
    data.permissions = ['website.content.manage'];
    await page.reload();
    await page.getByRole('button', { name: 'Software', exact: true }).click();
    await editor.locator('select').first().selectOption('software-1');
    await expect(editor.getByRole('button', { name: 'Save new draft revision' })).toBeEnabled();
    await expect(release.getByRole('button', { name: 'Publish draft v1' })).toBeDisabled();
    await expect(release.getByRole('button', { name: 'Archive' })).toBeDisabled();
    await release.getByPlaceholder('new-canonical-slug').fill('editor-cannot-rename');
    await release.getByPlaceholder('Approved reason').fill('Synthetic editor attempt');
    await expect(release.getByRole('button', { name: 'Change canonical slug' })).toBeDisabled();
    data.permissions = ['website.publish'];
    await page.reload();
    await page.getByRole('button', { name: 'Software', exact: true }).click();
    await editor.locator('select').first().selectOption('software-1');
    await expect(editor.getByRole('button', { name: 'Save new draft revision' })).toBeDisabled();
    await expect(release.getByRole('button', { name: 'Publish draft v1' })).toBeEnabled();
    await expect(release.getByRole('button', { name: 'Archive' })).toBeEnabled();
    await release.getByPlaceholder('new-canonical-slug').fill('publisher-can-rename');
    await release.getByPlaceholder('Approved reason').fill('Synthetic publisher approval');
    await expect(release.getByRole('button', { name: 'Change canonical slug' })).toBeEnabled();
    // P07: each POS domain has independent editable controls and revision permissions.
    data.pos_configuration.domains.branding.revisions = [
        { id: 82, domain: 'branding', version: 1, state: 'draft', snapshot: { 'branding.header_logo_media_id': 91 }, published_at: null, restored_from_revision_id: null, created_at: '2026-09-18' },
        { id: 83, domain: 'branding', version: 2, state: 'superseded', snapshot: { 'branding.header_logo_media_id': 0 }, published_at: '2026-09-18', restored_from_revision_id: null, created_at: '2026-09-18' },
    ];
    data.permissions = ['config.documents.manage'];
    await page.reload();
    await page.getByRole('button', { name: 'POS configuration' }).click();
    const documentOnly = page.getByRole('heading', { name: 'POS document & output defaults' }).locator('xpath=ancestor::section[1]');
    const themeOnly = page.getByRole('heading', { name: 'POS theme' }).locator('xpath=ancestor::section[1]');
    const brandingOnly = page.getByRole('heading', { name: 'POS branding assets' }).locator('xpath=ancestor::section[1]');
    await expect(documentOnly.locator('select').first()).toBeEnabled();
    await expect(documentOnly.getByRole('button', { name: 'Save draft revision' })).toBeEnabled();
    await expect(documentOnly.getByRole('button', { name: 'Publish', exact: true })).toBeDisabled();
    await expect(themeOnly.locator('input[type="color"]').first()).toBeDisabled();
    await expect(themeOnly.getByRole('button', { name: 'Save draft revision' })).toBeDisabled();
    await expect(brandingOnly.locator('select').first()).toBeDisabled();
    await expect(brandingOnly.locator('input[type="file"]')).toBeDisabled();
    await expect(brandingOnly.getByPlaceholder('Alternative text')).toBeDisabled();
    await expect(brandingOnly.getByRole('button', { name: 'Publish', exact: true })).toBeDisabled();
    await expect(brandingOnly.getByRole('button', { name: 'Rollback', exact: true })).toBeDisabled();
    data.permissions = ['config.branding.manage'];
    await page.reload();
    await page.getByRole('button', { name: 'POS configuration' }).click();
    await expect(documentOnly.locator('select').first()).toBeDisabled();
    await expect(themeOnly.locator('input[type="color"]').first()).toBeDisabled();
    await expect(brandingOnly.locator('select').first()).toBeEnabled();
    await expect(brandingOnly.getByRole('button', { name: 'Publish', exact: true })).toBeDisabled();
    await expect(brandingOnly.getByRole('button', { name: 'Rollback', exact: true })).toBeDisabled();
    data.permissions = ['config.branding.manage', 'config.publish'];
    await page.reload();
    await page.getByRole('button', { name: 'POS configuration' }).click();
    await expect(brandingOnly.getByRole('button', { name: 'Publish', exact: true })).toBeEnabled();
    await brandingOnly.getByRole('button', { name: 'Publish', exact: true }).click();
    await expect.poll(() => calls.some(call => call.path.endsWith('/pos-config/revisions/82/publish'))).toBe(true);
    await brandingOnly.getByRole('button', { name: 'Rollback', exact: true }).click();
    await expect.poll(() => calls.some(call => call.path.endsWith('/pos-config/revisions/83/rollback'))).toBe(true);
});
