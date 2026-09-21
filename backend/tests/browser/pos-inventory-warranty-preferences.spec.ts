import {execFileSync} from 'node:child_process';
import {expect,test} from '@playwright/test';
import { releaseSyntheticAdminSession } from './synthetic-admin-session';

test.afterEach(async ({ page }) => {
    await releaseSyntheticAdminSession(page);
});

test('actual inventory and warranty lists consume saved portal paging and category search',async ({page,browser})=>{
    execFileSync('php',['artisan','db:seed','--class=Database\\Seeders\\PosInventoryWarrantyE2eSeeder','--env=testing','--force'],{cwd:process.cwd(),stdio:'inherit'});
    await page.goto('/internal/admin/pos/login');
    await page.getByTestId('login-email').fill('e2e-iw-inventory@example.invalid');
    await page.getByTestId('login-password').fill('SyntheticPass123!');
    await page.getByTestId('login-submit').click();
    await page.waitForURL('**/internal/admin/pos');
    await expect(page.getByTestId('outlet-select')).toBeVisible();
    const selected=page.waitForResponse(r=>r.url().endsWith('/internal/admin/outlets/select')&&r.ok());
    await page.getByTestId('outlet-select').selectOption({label:'E2E Sales Outlet'});
    await selected;
    await page.goto('/internal/admin/pos/workspace/inventory');
    await expect(page.getByTestId('workspace-inventory')).toBeVisible();
    await expect(page.getByTestId('inventory-page')).toContainText('32 products');
    await expect(page.getByTestId('pos-lookup')).toBeFocused();
    await expect(page.getByTestId('inventory-category')).toHaveValue('all');
    await expect(page.getByTestId('inventory-page')).toContainText('10 per page');
    await page.getByRole('button',{name:'Next',exact:true}).first().click();
    await expect(page.getByTestId('inventory-page')).toContainText('Page 2/4');
    await page.getByTestId('pos-lookup').fill('MT75-IW Product 022');
    await page.getByTestId('inventory-category').selectOption('product_name');
    await page.getByRole('button',{name:'Search',exact:true}).first().click();
    await expect(page.getByTestId('inventory-page')).toContainText('1 products');
    const context=await browser.newContext();
    const warranty=await context.newPage();
    try{
        await warranty.goto('/internal/admin/pos/login');
        await warranty.getByTestId('login-email').fill('e2e-iw-warranty@example.invalid');
        await warranty.getByTestId('login-password').fill('SyntheticPass123!');
        await warranty.getByTestId('login-submit').click();
        await warranty.waitForURL('**/internal/admin/pos');
        await warranty.goto('/internal/admin/pos/workspace/warranty');
        await expect(warranty.getByTestId('mt43-warranty')).toBeVisible();
        await expect(warranty.getByTestId('history-total')).toContainText('22 records');
        await expect(warranty.getByTestId('history-total')).toContainText('15 per page');
        await warranty.getByTestId('history-next').click();
        await expect(warranty.getByTestId('history-total')).toContainText('2/2');
        await warranty.getByTestId('history-category').selectOption('customer_name');
        await warranty.getByTestId('history-search').fill('MT75-IW Customer 019');
        await warranty.getByTestId('history-apply').click();
        await expect(warranty.getByTestId('history-total')).toContainText('1 records');
        await expect(warranty.getByTestId('mt43-warranty').locator('option').filter({hasText:'MT75-IW-CLAIM-019'})).toHaveCount(1);
    }finally{await releaseSyntheticAdminSession(warranty); await context.close();}
});
