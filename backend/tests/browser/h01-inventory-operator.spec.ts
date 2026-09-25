import {expect,test} from '@playwright/test';
import {resolve} from 'node:path';
import {releaseSyntheticAdminSession} from './synthetic-admin-session';
import {runH01Fixture} from './h01-fixture';


let h01FixtureSeeded = false;
test.beforeAll(() => {
    runH01Fixture('H01InventoryOperatorBrowserSeeder', 'MT75_H01_INVENTORY_OPERATOR_E2E_ENABLED', 'MT75_H01_INVENTORY_OPERATOR_FIXTURE_ACTION', 'seed');
    h01FixtureSeeded = true;
});
test.afterAll(() => {
    if (h01FixtureSeeded) {
        runH01Fixture('H01InventoryOperatorBrowserSeeder', 'MT75_H01_INVENTORY_OPERATOR_E2E_ENABLED', 'MT75_H01_INVENTORY_OPERATOR_FIXTURE_ACTION', 'cleanup');
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
test('H01 real Inventory private acquisition evidence then zero-stock product archive retains source history',async({page})=>{
    await page.setViewportSize({width:390,height:844});
    await signIn(page,'e2e-protected-owner@example.invalid');
    expect((await page.goto('/internal/admin/pos/workspace/inventory'))?.status()).toBe(200);
    const inventory=page.getByTestId('workspace-inventory');
    await expect(inventory).toContainText('H01 isolated evidence accessory');
    const record=inventory.getByText('H01 isolated evidence accessory',{exact:true}).locator('xpath=../..');
    const file=record.locator('input[data-testid^="acquisition-evidence-"][data-testid$="-front"]');
    await expect(file).toHaveCount(1);
    const saved=page.waitForResponse(r=>/\/pos\/inventory\/acquisitions\/\d+\/evidence\/front$/.test(r.url())&&r.request().method()==='POST');
    await file.setInputFiles(resolve('public/icon-192.png'));
    expect((await saved).status()).toBe(200);
    await expect(record.getByRole('link',{name:'Front private image'})).toBeVisible();
    const download=page.waitForEvent('download');
    await record.getByRole('link',{name:'Front private image'}).click();
    expect((await download).suggestedFilename()).toMatch(/^acquisition-\d+-front\.png$/);
    page.once('dialog',dialog=>dialog.accept());
    const archive=page.waitForResponse(r=>r.url().endsWith('/archive')&&r.request().method()==='POST');
    await record.getByRole('button',{name:'Archive zero-stock product'}).click();
    expect((await archive).status()).toBe(200);
    await expect(inventory.locator('button[data-testid^="product-edit-"]')).toHaveCount(0);
});
test('H01 sales-only operator cannot open private acquisition document route',async({page})=>{
    await signIn(page,'e2e-sales@example.invalid');
    const unauth=await page.goto('/internal/admin/pos/inventory/acquisitions/999999/evidence/front');
    expect(unauth?.status()).toBe(403);
});
