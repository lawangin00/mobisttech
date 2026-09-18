import { expect, Page, Route, test } from '@playwright/test';

const password = 'SyntheticPass123!';

async function login(page: Page) {
    await page.goto('/internal/admin/pos/login');
    await page.getByTestId('login-email').fill('e2e-reset@example.invalid');
    await page.getByTestId('login-password').fill(password);
    await page.getByTestId('login-submit').click();
    await page.waitForURL('**/internal/admin/pos');
    await page.goto('/internal/admin/reset-administration');
    await expect(page.getByRole('heading', { name: 'Data Reset Administration' })).toBeVisible();
}

test('MT-4.7 reset administration previews cancels executes and surfaces recovery evidence safely', async ({ page }) => {
    test.setTimeout(90_000);
    const calls: Array<{ path: string; method: string; body: Record<string, unknown> | null }> = [];
    const factoryDomains = ['commerce','claims','repairs','loyalty_promotions','cash','digital_projects','service_requests','notification_delivery','inventory','procurement','trade_in','customers','website_content','digital_catalogue','engagement','factory_configuration'];
    const levels = {
        transactional: { label:'Transactional Data Reset', description:'Clear selected transactional data.', permission:'system.reset.transactional', can_execute:true, confirmation_required:'TRANSACTIONAL DATA RESET', available_domains:['commerce','digital_projects','service_requests'], factory_scope_locked:false },
        business: { label:'Business Data Reset', description:'Clear selected business data.', permission:'system.reset.business', can_execute:true, confirmation_required:'BUSINESS DATA RESET', available_domains:['commerce','service_requests','inventory','customers'], factory_scope_locked:false },
        factory: { label:'Factory Reset', description:'Clear full approved factory scope.', permission:'system.reset.factory', can_execute:true, confirmation_required:'FACTORY RESET', available_domains:factoryDomains, factory_scope_locked:true },
    };
    const data = {
        identity:{name:'E2E Reset Administrator',job_title:'Reset Administrator'},
        levels,
        domain_labels:Object.fromEntries(factoryDomains.map(d=>[d,d.replaceAll('_',' ')])),
        recent_authentication:true,
        preview_ttl_minutes:10,
        execution_enabled_here:true,
        production_hold:true,
        minimum_bootstrap:{
            access_tables:['admins','outlets','outlet_admins','permission_definitions','roles','role_permissions','admin_roles'],
            recovery_evidence_tables:['reset_operations','backup_restore_rehearsals','backup_manifests','backup_records','admin_audit_logs'],
            note:'Authorized bootstrap and durable recovery evidence are preserved.',
        },
        operations:[
            {
                public_id:'cleanup-1',level:'transactional',domains:['service_requests'],record_count:3,file_count:1,status:'cleanup_pending',
                failure_code:'PRIVATE_OBJECT_CLEANUP_FAILED',started_at:'2026-09-18',completed_at:null,result:{deleted_records:{service_requests:3}},created_at:'2026-09-18',
                backup:{record_id:7,status:'completed',filename:'verified.backup',size_bytes:2048,checksum:'a'.repeat(64),completed_at:'2026-09-18',manifest_verified_at:'2026-09-18',restore_rehearsal:{status:'passed',failure_code:null,checked_at:'2026-09-18'}},
            },
            {
                public_id:'failed-1',level:'transactional',domains:['service_requests'],record_count:2,file_count:0,status:'failed',
                failure_code:'BACKUP_VERIFICATION_FAILED',started_at:'2026-09-18',completed_at:null,result:null,created_at:'2026-09-18',backup:null,
            },
        ],
        audit:[
            {id:1,actor_name:'E2E Reset Administrator',actor_email:'e2e-reset@example.invalid',action:'reset_backup_failed',payload:{reset_id:'failed-1'},status_code:409,created_at:'2026-09-18'},
            {id:2,actor_name:'E2E Reset Administrator',actor_email:'e2e-reset@example.invalid',action:'reset_cleanup_pending',payload:{reset_id:'cleanup-1'},status_code:409,created_at:'2026-09-18'},
        ],
    };
    const preview = {
        public_id:'preview-1',level:'transactional',domains:['service_requests'],
        domain_counts:{service_requests:{tables:['service_requests','service_request_files'],record_count:4,file_count:1}},
        record_count:4,file_count:1,barriers:[],
        preservation:[
            {group:'transactional',action:'selected_clear',tables:['service_requests','service_request_files']},
            {group:'bootstrap',action:'preserve',tables:['admins','roles']},
            {group:'preserved_evidence',action:'preserve',tables:['reset_operations','backup_records']},
        ],
        preview_sha256:'p'.repeat(64),confirmation_required:'TRANSACTIONAL DATA RESET',execution_enabled_here:true,
    };

    const fulfill = async (route: Route, payload: unknown = {}) => route.fulfill({ status:200, contentType:'application/json', body:JSON.stringify({data:payload}) });
    const capture = async (route: Route, payload: unknown = {}) => {
        const request = route.request(); let body: Record<string, unknown> | null = null;
        try { body = request.postDataJSON() as Record<string, unknown>; } catch { body = null; }
        calls.push({ path:new URL(request.url()).pathname, method:request.method(), body });
        await fulfill(route,payload);
    };

    await page.route('**/internal/admin/reset-administration/**', async route => {
        const request = route.request(); const path = new URL(request.url()).pathname;
        if (path === '/internal/admin/reset-administration') { await route.fallback(); return; }
        if (path === '/internal/admin/reset-administration/data' && request.method()==='GET') { await fulfill(route,data); return; }
        if (path === '/internal/admin/reset-administration/preview' && request.method()==='POST') { await capture(route,preview); return; }
        if (path.includes('/operations/') && path.endsWith('/execute') && request.method()==='POST') { await capture(route,{public_id:path.split('/').at(-2),status:'completed',result:{private_objects_deleted:1,backup_record_id:7}}); return; }
        await route.fallback();
    });

    await page.setViewportSize({width:1440,height:900});
    await login(page);

    await expect(page.getByText('Production reset HOLD')).toBeVisible();
    await expect(page.getByText(/execution: enabled for disposable acceptance/i)).toBeVisible();
    await expect(page.getByText(/admins, outlets, outlet_admins/)).toBeVisible();
    await expect(page.getByText(/BACKUP_VERIFICATION_FAILED/)).toBeVisible();
    await expect(page.getByText(/Restore rehearsal: passed/)).toBeVisible();

    await page.getByRole('button',{name:/Transactional Data Reset/}).click();
    await page.getByLabel(/service requests/i).check();
    await page.getByRole('button',{name:'Create dry-run preview'}).click();
    await expect(page.getByRole('heading',{name:/Dry-run preview/})).toBeVisible();
    await expect(page.getByText('4 records · 1 private files · 1 domains')).toBeVisible();
    await expect(page.getByText(/bootstrap · preserve/)).toBeVisible();
    await expect.poll(()=>calls.some(c=>c.path.endsWith('/preview') && (c.body?.domains as string[])?.includes('service_requests'))).toBe(true);

    await page.getByRole('button',{name:'Cancel preview'}).click();
    await expect(page.getByText(/Preview cancelled locally/)).toBeVisible();
    expect(calls.some(c=>c.path.includes('/execute'))).toBe(false);

    await page.getByRole('button',{name:'Create dry-run preview'}).click();
    await page.getByPlaceholder('TRANSACTIONAL DATA RESET').fill('TRANSACTIONAL DATA RESET');
    await page.getByRole('button',{name:'Execute verified reset'}).click();
    await expect.poll(()=>calls.some(c=>c.path.endsWith('/operations/preview-1/execute') && c.body?.confirmation==='TRANSACTIONAL DATA RESET')).toBe(true);

    await page.getByRole('button',{name:/Factory Reset/}).click();
    const factoryChecks = page.locator('input[type="checkbox"]');
    await expect(factoryChecks).toHaveCount(factoryDomains.length);
    for (let i=0;i<factoryDomains.length;i++) {
        await expect(factoryChecks.nth(i)).toBeChecked();
        await expect(factoryChecks.nth(i)).toBeDisabled();
    }

    await page.getByRole('button',{name:'Resume cleanup recovery'}).click();
    await page.getByPlaceholder('TRANSACTIONAL DATA RESET').fill('TRANSACTIONAL DATA RESET');
    await page.getByRole('button',{name:'Execute verified reset'}).click();
    await expect.poll(()=>calls.some(c=>c.path.endsWith('/operations/cleanup-1/execute'))).toBe(true);

    await page.setViewportSize({width:390,height:844});
    expect(await page.evaluate(()=>document.documentElement.scrollWidth>window.innerWidth)).toBe(false);
});
