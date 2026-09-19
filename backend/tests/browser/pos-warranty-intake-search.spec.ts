import {execFileSync} from 'node:child_process';
import {expect,test} from '@playwright/test';

test('warranty intake finds and selects an older source-matched sale in real UI',async ({page})=>{
    execFileSync('php',['artisan','db:seed','--class=Database\\Seeders\\PosWarrantyIntakeE2eSeeder','--env=testing','--force'],{cwd:process.cwd(),stdio:'inherit'});
    await page.goto('/internal/admin/pos/login');
    await page.getByTestId('login-email').fill('e2e-intake@example.invalid');
    await page.getByTestId('login-password').fill('SyntheticPass123!');
    await page.getByTestId('login-submit').click();
    await page.waitForURL('**/internal/admin/pos');
    await page.goto('/internal/admin/pos/workspace/claims');
    await expect(page.getByTestId('mt43-claims')).toBeVisible();
    await expect(page.getByTestId('intake-result-count')).toContainText('Latest 100');
    await expect(page.getByTestId('intake-sale').locator('option').filter({hasText:'MT75-INTAKE-001'})).toHaveCount(0);
    await page.getByTestId('intake-category').selectOption('invoice_id');
    await page.getByTestId('intake-query').fill('MT75-INTAKE-001');
    const searched=page.waitForResponse((r)=>r.url().includes('/claims/sale-search?')&&r.ok());
    await page.getByTestId('intake-find').click(); await searched;
    await expect(page.getByTestId('intake-result-count')).toContainText('1 matching sale');
    await expect(page.getByTestId('intake-sale').locator('option').filter({hasText:'MT75-INTAKE-001'})).toHaveCount(1);
    const older = await page.getByTestId('intake-sale').locator('option').filter({hasText:'MT75-INTAKE-001'}).getAttribute('value');
    expect(older).toBeTruthy();
    await page.getByTestId('intake-sale').selectOption(older!);
    await page.getByPlaceholder('Issue description').fill('Test-only old-sale warranty intake');
    await expect(page.getByTestId('claim-open')).toBeEnabled();
});
