import { execFileSync } from 'node:child_process';
import { expect, test } from '@playwright/test';

function state(mode: 'hybrid' | 'digital_only' | 'commerce_only') {
    execFileSync('php', [
        'artisan', 'db:seed', '--class=Database\\Seeders\\WebsiteStorefrontE2eStateSeeder',
        '--env=testing', '--force',
    ], {
        cwd: process.cwd(),
        stdio: 'inherit',
        env: { ...process.env, WEBSITE_E2E_MODE: mode, WEBSITE_E2E_ACTION: '' },
    });
}

async function login(page: import('@playwright/test').Page) {
    await page.goto('/account');
    await page.getByLabel('Email').fill('mt52-customer@example.invalid');
    await page.getByLabel('Password').fill('SyntheticPass123!');
    const accountReady = page.waitForResponse((response) =>
        response.url().endsWith('/api/customer/account') && response.status() === 200);
    const projectsReady = page.waitForResponse((response) =>
        response.url().endsWith('/api/customer/projects') && response.status() === 200);
    await page.locator('form').getByRole('button', { name: 'Sign in', exact: true }).click();
    await Promise.all([accountReady, projectsReady]);
    await expect(page.getByRole('heading', { name: 'MT52 Customer' })).toBeVisible();
}
test('MT-5.5 customer project portal preserves private history and truthful provider availability', async ({ page }) => {
    test.setTimeout(90_000);
    await login(page);

    const projectLink = page.getByRole('link', { name: 'MT55 Client Project', exact: true });
    await expect(projectLink).toBeVisible();
    await expect(projectLink.locator('..')).toContainText('delivered');
    const projectReady = page.waitForResponse((response) =>
        /\/api\/customer\/projects\/[0-9a-f-]+$/.test(response.url()) && response.status() === 200);
    await projectLink.click();
    await projectReady;

    await expect(page.getByRole('heading', { name: 'MT55 Client Project' })).toBeVisible();
    await expect(page.getByText('MT55 Client Project Service · delivered')).toBeVisible();
    await expect(page.getByRole('heading', { name: 'Proposals & milestones' }).locator('..')).toContainText('MT55 Approved Scope');
    await expect(page.getByText('PKR 10000.00', { exact: true })).toBeVisible();
    await expect(page.getByText('No external payment provider is configured.', { exact: true })).toBeVisible();

    const files = page.getByRole('heading', { name: 'Private files' }).locator('..');
    await expect(files).toContainText('mt55-client-reference.txt');
    await expect(files).toContainText('mt55-delivery.txt');
    const downloadPromise = page.waitForEvent('download');
    await files.getByRole('link', { name: 'Download securely' }).last().click();
    const download = await downloadPromise;
    expect(download.suggestedFilename()).toBe('mt55-delivery.txt');

    const upload = page.waitForResponse((response) =>
        response.url().includes('/api/customer/projects/')
        && response.url().endsWith('/files/reference')
        && response.request().method() === 'POST'
        && response.status() === 201);
    await files.locator('input[type=file]').setInputFiles({
        name: 'mt55-browser-reference.txt',
        mimeType: 'text/plain',
        buffer: Buffer.from('MT55 browser private reference'),
    });
    await files.getByRole('button', { name: 'Upload reference' }).click();
    await upload;
    await expect(page.getByRole('status')).toHaveText('Reference file uploaded.');
    await expect(files).toContainText('mt55-browser-reference.txt');

    const history = page.getByRole('heading', { name: 'Project history' }).locator('..');
    await expect(history).toContainText('proposal approved');
    await expect(history.getByText('project status changed', { exact: true })).toHaveCount(4);

    state('commerce_only');
    await page.reload();
    await expect(page.getByRole('heading', { name: 'MT55 Client Project' })).toBeVisible();
    state('digital_only');
    await page.reload();
    await expect(page.getByRole('heading', { name: 'MT55 Client Project' })).toBeVisible();
    state('hybrid');
});
