import { expect, Page, Route, test } from '@playwright/test';

const password = 'SyntheticPass123!';

async function login(page: Page) {
    await page.goto('/internal/admin/pos/login');
    await page.getByTestId('login-email').fill('e2e-digital-operations@example.invalid');
    await page.getByTestId('login-password').fill(password);
    await page.getByTestId('login-submit').click();
    await page.waitForURL('**/internal/admin/pos');
    await page.goto('/internal/admin/digital-operations');
    await expect(page.getByRole('heading', { name: 'Digital Operations' })).toBeVisible();
}

test('MT-4.8 digital operations administers services leads projects private files and aggregate reporting', async ({ page }) => {
    test.setTimeout(90_000);
    const calls: Array<{ path: string; method: string; body: Record<string, unknown> | null }> = [];
    const permissions = [
        'website.services.manage', 'website.consultations.manage', 'website.digital-leads.manage',
        'website.digital-projects.manage', 'website.proposals.approve', 'website.client-files.manage',
        'website.conversions.view',
    ];
    const leadDetail = {
        public_id: 'lead-1', reference: 'SR-E2E-001', version: 2, status: 'contacted',
        service: { slug: 'web-development', name: 'Web Development' },
        customer: { name: 'E2E Lead', business_name: 'E2E Co', mobile: '03001234567', email: 'lead@example.invalid' },
        requirements: 'Need a digital build', preferred_contact: 'whatsapp', project_type: 'website', existing_url: null,
        budget_range: '50000-100000', preferred_timeline: '4 weeks', assigned_admin_id: 1,
        follow_up_at: '2026-09-22T05:00:00.000000Z', consultation_status: 'requested',
        preferred_timezone: 'Asia/Karachi', preferred_window_start_utc: null, preferred_window_end_utc: null,
        reference_files: [{ id: 'lead-file-1', name: 'brief.pdf', mime_type: 'application/pdf', byte_size: 2048, sha256: 'a'.repeat(64) }],
        selections: [{ type: 'package', label: 'Starter', pricing_type: 'fixed', price: '50000.00', currency: 'PKR', source_version: 1 }],
        events: [{ type: 'lead_created', snapshot: {}, occurred_at: '2026-09-18T08:00:00Z' }],
    };
    const projectDetail = {
        public_id: 'project-1', reference: 'PRJ-E2E-001', title: 'E2E Client Project', status: 'approved', version: 3,
        service: { slug: 'web-development', name: 'Web Development' },
        proposals: [{
            public_id: 'proposal-1', revision: 1, state: 'draft', title: 'E2E Proposal', amount: '100000.00',
            currency: 'PKR', valid_until: '2026-10-01T00:00:00Z', scope: 'Defined project scope.',
            deliverables: ['Website', 'Handover'], schedule: [{ kind: 'deposit', label: 'Deposit', amount: '40000.00', due_at: null }, { kind: 'final', label: 'Final', amount: '60000.00', due_at: null }],
            snapshot_sha256: 'b'.repeat(64), quote: null,
            milestones: [{ id: 'milestone-1', sequence: 1, kind: 'deposit', label: 'Deposit', amount: '40000.00', currency: 'PKR', due_at: null, paid_at: null, payment_status: 'unpaid', payable: false }],
        }],
        files: [{ id: 'project-file-1', type: 'reference', name: 'client-brief.pdf', mime_type: 'application/pdf', byte_size: 4096, sha256: 'c'.repeat(64), retention_until: '2028-09-18', created_at: '2026-09-18' }],
        history: [{ type: 'project_created', snapshot: {}, occurred_at: '2026-09-18T08:00:00Z' }],
        customer_account_public_id: 'customer-1', assigned_admin_public_id: 'owner-1',
    };
    const data = {
        identity: { name: 'E2E Digital Operations Manager', job_title: 'Digital Operations Manager' },
        permissions,
        website_mode: { mode: 'digital_only' },
        mode_history: [{ id: 1, version: 2, state: 'published', mode: 'digital_only', published_at: '2026-09-18', created_at: '2026-09-18' }],
        services: [{
            slug: 'web-development', name: 'Web Development', category: 'development',
            short_description: 'Build a Website', description: 'Full digital build', price_type: 'package', price: null,
            is_active: true, sort_order: 1,
            packages: [{ public_id: 'package-1', code: 'starter', name: 'Starter', pricing_type: 'fixed', price: '50000.00', active: true, sort_order: 1, version: 1 }],
            addons: [{ public_id: 'addon-1', code: 'seo', name: 'SEO', pricing_type: 'fixed', price: '10000.00', active: true, sort_order: 1, version: 1 }],
        }],
        consultation: { enabled: true, timezone: 'Asia/Karachi', weekly_availability: [{ day: 1, start: '09:00', end: '17:00' }], version: 1, external_calendar: { enabled: false, provider: null } },
        leads: [{
            public_id: 'lead-1', reference: 'SR-E2E-001', version: 2, status: 'contacted', customer_name: 'E2E Lead', business_name: 'E2E Co',
            customer_mobile: '03001234567', customer_email: 'lead@example.invalid', requirements: 'Need a digital build', preferred_contact: 'whatsapp',
            service_slug: 'web-development', service_name: 'Web Development', project_type: 'website', budget_range: '50000-100000', preferred_timeline: '4 weeks',
            source: 'website', campaign: 'e2e', follow_up_at: '2026-09-22T05:00:00Z', consultation_requested: true, consultation_status: 'requested',
            preferred_timezone: 'Asia/Karachi', preferred_window_start_utc: null, preferred_window_end_utc: null,
            assigned_admin_public_id: 'owner-1', assigned_admin_name: 'Digital Owner', project_public_id: null, project_reference: null,
            created_at: '2026-09-18', updated_at: '2026-09-18',
        }],
        projects: [{
            public_id: 'project-1', reference: 'PRJ-E2E-001', title: 'E2E Client Project', status: 'approved', version: 3,
            service_slug: 'web-development', service_name: 'Web Development', lead_public_id: 'lead-existing', lead_reference: 'SR-E2E-000',
            customer_account_public_id: 'customer-1', customer_name: 'E2E Client', customer_email: 'client@example.invalid', customer_mobile: '03009990000',
            assigned_admin_public_id: 'owner-1', assigned_admin_name: 'Digital Owner', created_at: '2026-09-18', updated_at: '2026-09-18',
        }],
        lead_owners: [{ id: 'owner-1', name: 'Digital Owner', job_title: 'Manager' }],
        project_owners: [{ id: 'owner-1', name: 'Digital Owner', job_title: 'Manager' }],
        customers: [{ id: 'customer-1', name: 'E2E Client', email: 'client@example.invalid', mobile: '03009990000' }],
    };
    const conversion = {
        from: '2026-08-18T00:00:00Z', to: '2026-09-18T23:59:59Z', aggregate_only: true,
        totals: { leads: 8, projects: 5, approved_proposals: 4, paid_milestones: 3, completed_projects: 2 },
        by_service: [{ service: 'web-development', leads: 8, projects: 5 }],
        by_source: [{ source: 'website', leads: 8, projects: 5 }],
    };

    const fulfill = async (route: Route, payload: unknown = {}) => route.fulfill({ status: 200, contentType: 'application/json', body: JSON.stringify({ data: payload }) });
    const capture = async (route: Route, payload: unknown = {}) => {
        const request = route.request(); let body: Record<string, unknown> | null = null;
        try { body = request.postDataJSON() as Record<string, unknown>; } catch { body = null; }
        calls.push({ path: new URL(request.url()).pathname, method: request.method(), body });
        await fulfill(route, payload);
    };
    await page.route('**/internal/admin/digital-operations/**', async (route) => {
        const request = route.request(); const path = new URL(request.url()).pathname;
        if (path === '/internal/admin/digital-operations') { await route.fallback(); return; }
        if (path === '/internal/admin/digital-operations/data' && request.method() === 'GET') { await fulfill(route, data); return; }
        if (path === '/internal/admin/digital-operations/leads/lead-1' && request.method() === 'GET') { await fulfill(route, leadDetail); return; }
        if (path === '/internal/admin/digital-operations/leads/lead-1' && request.method() === 'PATCH') { await capture(route, { ...leadDetail, version: 3, status: 'qualified' }); return; }
        if (path === '/internal/admin/digital-operations/projects/project-1' && request.method() === 'GET') { await fulfill(route, projectDetail); return; }
        if (path === '/internal/admin/digital-operations/projects/project-1' && request.method() === 'PATCH') { await capture(route, { ...projectDetail, version: 4, status: 'in_progress' }); return; }
        if (path === '/internal/admin/digital-operations/conversions' && request.method() === 'GET') { await capture(route, conversion); return; }
        await capture(route, {});
    });

    await page.setViewportSize({ width: 1440, height: 900 });
    await login(page);

    await expect(page.getByRole('heading', { name: 'Published Website mode' }).locator('xpath=ancestor::section[1]')).toContainText('digital only');
    await expect(page.getByRole('heading', { name: 'Mode publication history' }).locator('xpath=ancestor::section[1]')).toContainText('digital_only');

    await page.getByRole('button', { name: 'Services' }).click();
    const services = page.getByRole('heading', { name: 'Services, packages & add-ons' }).locator('xpath=ancestor::section[1]');
    await services.locator('select').first().selectOption('web-development');
    await expect(services.locator('textarea').nth(1)).toContainText('starter');
    await expect(services.locator('textarea').nth(2)).toContainText('seo');
    await services.getByRole('button', { name: 'Save Digital Service' }).click();
    await expect.poll(() => calls.some(c => c.path.endsWith('/services') && c.body?.slug === 'web-development')).toBe(true);
    const consultation = page.getByRole('heading', { name: 'Consultation booking administration' }).locator('xpath=ancestor::section[1]');
    await consultation.getByRole('button', { name: 'Save consultation settings' }).click();
    await expect.poll(() => calls.some(c => c.path.endsWith('/consultation') && c.method === 'PUT')).toBe(true);

    await page.getByRole('button', { name: 'Leads' }).click();
    const pipeline = page.getByRole('heading', { name: 'Enquiry / lead pipeline' }).locator('xpath=ancestor::section[1]');
    await pipeline.getByRole('button', { name: /SR-E2E-001/ }).click();
    await expect(page.getByText('Need a digital build')).toBeVisible();
    await page.locator('select').filter({ has: page.locator('option[value="qualified"]') }).first().selectOption('qualified');
    await page.getByPlaceholder('Internal follow-up note').fill('Browser follow-up');
    await page.getByRole('button', { name: 'Save lead update' }).click();
    await expect.poll(() => calls.some(c => c.path.endsWith('/leads/lead-1') && c.method === 'PATCH' && c.body?.status === 'qualified')).toBe(true);
    await expect(page.getByRole('link', { name: /brief.pdf/ })).toHaveAttribute('href', '/internal/admin/digital-operations/leads/lead-1/files/lead-file-1');
    await page.getByPlaceholder('Project title').fill('Converted E2E Project');
    await page.getByRole('button', { name: 'Create client project' }).click();
    await expect.poll(() => calls.some(c => c.path.endsWith('/leads/lead-1/projects') && c.body?.title === 'Converted E2E Project')).toBe(true);

    await page.getByRole('button', { name: 'Projects' }).click();
    const projects = page.getByRole('heading', { name: 'Client projects' }).locator('xpath=ancestor::section[1]');
    await projects.getByRole('button', { name: /PRJ-E2E-001/ }).click();
    await expect(page.getByText(/Revision 1 · draft · PKR 100000\.00/)).toBeVisible();
    await expect(page.getByText(/Deposit · PKR 40000.00/)).toBeVisible();
    const transitionSelect = page.locator('select').filter({ has: page.locator('option[value="in_progress"]') }).first();
    await transitionSelect.selectOption('in_progress');
    await page.getByRole('button', { name: 'Transition project' }).click();
    await expect.poll(() => calls.some(c => c.path.endsWith('/projects/project-1') && c.method === 'PATCH' && c.body?.status === 'in_progress')).toBe(true);
    await page.getByRole('button', { name: 'Approve proposal' }).click();
    await expect.poll(() => calls.some(c => c.path.endsWith('/proposals/proposal-1/approve'))).toBe(true);
    await expect(page.getByRole('link', { name: /client-brief.pdf/ })).toHaveAttribute('href', '/internal/admin/digital-operations/projects/project-1/files/project-file-1');
    await page.locator('input[type="file"]').setInputFiles({ name: 'delivery.txt', mimeType: 'text/plain', buffer: Buffer.from('private delivery') });
    await expect.poll(() => calls.some(c => c.path.endsWith('/projects/project-1/files') && c.method === 'POST')).toBe(true);

    await page.getByRole('button', { name: 'Reporting' }).click();
    await page.getByRole('button', { name: 'Run aggregate report' }).click();
    await expect(page.getByText('Approved proposals')).toBeVisible();
    await expect(page.getByText('web-development: 8 leads · 5 projects')).toBeVisible();
    await expect.poll(() => calls.some(c => c.path.endsWith('/conversions') && c.method === 'GET')).toBe(true);

    await page.setViewportSize({ width: 390, height: 844 });
    for (const tab of ['Overview', 'Services', 'Leads', 'Projects', 'Reporting']) {
        await page.getByRole('button', { name: tab, exact: true }).click();
        expect(await page.evaluate(() => document.documentElement.scrollWidth > window.innerWidth)).toBe(false);
    }
});
