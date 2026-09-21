import { execFileSync } from 'node:child_process';
import { expect, Page, test } from '@playwright/test';

const artisan = (seeder: string) => execFileSync('php', ['artisan', 'db:seed', '--class=Database\\Seeders\\' + seeder, '--env=testing', '--force'], { cwd: process.cwd(), stdio: 'inherit' });

async function login(page: Page) {
    await page.goto('/internal/admin/pos/login');
    await page.getByTestId('login-email').fill('e2e-protected-owner@example.invalid');
    await page.getByTestId('login-password').fill('SyntheticPass123!');
    await page.getByTestId('login-submit').click();
    await page.waitForURL('**/internal/admin/pos');
}

test.beforeAll(() => artisan('W03CommerceE2eSeeder'));
test.afterAll(() => artisan('W03CommerceE2eCleanupSeeder'));

test('MT-7.5 W03 Admin advances Website fulfillment exports CSV and moderates a purchased review', async ({ page }) => {
    await login(page);
    expect((await page.goto('/internal/admin/website-commerce'))?.status()).toBe(200);
    await expect(page.getByRole('heading', { name: 'Website commerce administration' })).toBeVisible();
    const orderRow = page.getByRole('row').filter({ hasText: 'MT75-W03-ORDER' });
    await expect(orderRow).toContainText('pending');
    const statusResponse = page.waitForResponse(response => response.url().includes('/website-commerce/orders/') && response.request().method() === 'PATCH');
    await orderRow.getByRole('button', { name: 'Mark processing' }).click();
    expect((await statusResponse).status()).toBe(200);
    await expect(orderRow).toContainText('processing');

    const download = page.waitForEvent('download');
    await page.getByRole('button', { name: 'Export CSV' }).click();
    await expect.poll(async () => (await download).suggestedFilename()).toMatch(/^website-orders-\d{8}-\d{6}\.csv$/);

    const review = page.getByRole('article').filter({ hasText: 'W03 verified purchase' });
    await review.getByPlaceholder('Optional Admin reply').fill('Thank you for your verified feedback.');
    const reviewResponse = page.waitForResponse(response => response.url().includes('/website-commerce/reviews/') && response.request().method() === 'PATCH');
    await review.getByRole('button', { name: 'Approve' }).click();
    expect((await reviewResponse).status()).toBe(200);
    await expect(page.getByRole('status')).toContainText('Review approved.');
    await expect(review).toHaveCount(0);
});
