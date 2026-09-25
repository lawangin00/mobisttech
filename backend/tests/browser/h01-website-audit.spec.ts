import {expect, test} from '@playwright/test';
import {releaseSyntheticAdminSession} from './synthetic-admin-session';
import {runH01Fixture} from './h01-fixture';


let h01FixtureSeeded = false;
test.beforeAll(() => {
    runH01Fixture('H01WebsiteAuditBrowserSeeder', 'MT75_H01_WEBSITE_AUDIT_E2E_ENABLED', 'MT75_H01_WEBSITE_AUDIT_FIXTURE_ACTION', 'seed');
    h01FixtureSeeded = true;
});
test.afterAll(() => {
    if (h01FixtureSeeded) {
        runH01Fixture('H01WebsiteAuditBrowserSeeder', 'MT75_H01_WEBSITE_AUDIT_E2E_ENABLED', 'MT75_H01_WEBSITE_AUDIT_FIXTURE_ACTION', 'cleanup');
    }
});
test.afterEach(async ({page}) => { await releaseSyntheticAdminSession(page); });
async function signIn(page: import('@playwright/test').Page, email: string) {
    await page.goto('/internal/admin/pos/login');
    await page.getByTestId('login-email').fill(email);
    await page.getByTestId('login-password').fill('SyntheticPass123!');
    await page.getByTestId('login-submit').click();
    await page.waitForURL('**/internal/admin/pos');
}
test('H01 Website owner sees redacted recorded event and method filter via real Admin UI', async ({page}) => {
    await page.setViewportSize({width:390,height:844});
    await signIn(page, 'e2e-protected-owner@example.invalid');
    expect((await page.goto('/internal/admin/platform'))?.status()).toBe(200);
    await expect(page.getByRole('link',{name:'Website activity'})).toBeVisible();
    await page.getByRole('link',{name:'Website activity'}).click();
    await expect(page.getByRole('heading',{name:'Website activity'})).toBeVisible();
    await expect(page.getByTestId('website-audit-records')).toContainText('website_h01_browser_event');
    await expect(page.locator('body')).not.toContainText('H01-SYNTHETIC-PRIVATE-NOT-IN-UI');
    await page.getByTestId('website-audit-filters').getByRole('combobox').selectOption('POST');
    await page.getByRole('button',{name:'Apply filters'}).click();
    await expect(page.getByTestId('website-audit-records')).not.toContainText('website_h01_browser_event');
    await expect(page.locator('body')).toHaveJSProperty('scrollWidth',390);
});
test('H01 Website audit cannot be opened by role without website.audit.view',async ({page})=>{
    await signIn(page,'e2e-sales@example.invalid');
    expect((await page.goto('/internal/admin/website-audit'))?.status()).toBe(403);
});
