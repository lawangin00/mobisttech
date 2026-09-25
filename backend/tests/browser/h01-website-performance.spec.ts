import {expect,test} from '@playwright/test';
import {releaseSyntheticAdminSession} from './synthetic-admin-session';
import {runH01Fixture} from './h01-fixture';


let h01FixtureSeeded = false;
test.beforeAll(() => {
    runH01Fixture('H01WebsitePerformanceBrowserSeeder', 'MT75_H01_WEBSITE_PERF_E2E_ENABLED', 'MT75_H01_WEBSITE_PERF_FIXTURE_ACTION', 'seed');
    h01FixtureSeeded = true;
});
test.afterAll(() => {
    if (h01FixtureSeeded) {
        runH01Fixture('H01WebsitePerformanceBrowserSeeder', 'MT75_H01_WEBSITE_PERF_E2E_ENABLED', 'MT75_H01_WEBSITE_PERF_FIXTURE_ACTION', 'cleanup');
    }
});
test.afterEach(async({page})=>{await releaseSyntheticAdminSession(page);});
async function signIn(page:import('@playwright/test').Page,email:string){
    await page.goto('/internal/admin/pos/login');
    await page.getByTestId('login-email').fill(email);
    await page.getByTestId('login-password').fill('SyntheticPass123!');
    await page.getByTestId('login-submit').click();
    await page.waitForURL('**/internal/admin/pos');
}
test('H01 Website combined live Admin performance and all-order CSV',async({page})=>{
    await page.setViewportSize({width:390,height:844});
    await signIn(page,'e2e-protected-owner@example.invalid');
    expect((await page.goto('/internal/admin/platform'))?.status()).toBe(200);
    await page.getByRole('link',{name:'Website performance'}).click();
    await expect(page.getByRole('heading',{name:'Website performance'})).toBeVisible();
    const summary=page.getByTestId('website-performance-summary');
    await expect(summary).toContainText('Combined paid face total (PKR)150.00');
    await expect(summary).toContainText('Payment reconciliation1');
    const download=page.waitForEvent('download');
    await page.getByRole('button',{name:'Export combined order CSV'}).click();
    expect((await download).suggestedFilename()).toMatch(/^website-performance-\d{8}-\d{6}\.csv$/);
    await expect(page.getByRole('status')).toContainText('Exported 3 Website order(s).');
    await expect(page.locator('body')).toHaveJSProperty('scrollWidth',390);
});
test('H01 report is denied to a sales-only Admin',async({page})=>{
    await signIn(page,'e2e-sales@example.invalid');
    expect((await page.goto('/internal/admin/website-performance'))?.status()).toBe(403);
    expect((await page.goto('/internal/admin/website-performance/data'))?.status()).toBe(403);
});
