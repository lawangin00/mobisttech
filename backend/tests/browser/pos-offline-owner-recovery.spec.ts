import {expect, test} from '@playwright/test';

const ownerEmail = 'e2e-protected-owner@example.invalid';
const originalPassword = 'SyntheticPass123!';
const recoveredPassword = 'SyntheticD05UpdatedPass123!';

test('isolated enabled offline owner recovery is owner-only and consumes a UI-issued code', async ({page, browser}) => {
    test.skip(process.env.MT75_D05_E2E_ENABLED !== '1', 'Explicit isolated synthetic D05 browser run only');
    test.setTimeout(90_000);
    await page.goto('/internal/admin/pos/login');
    await page.getByTestId('login-email').fill(ownerEmail);
    await page.getByTestId('login-password').fill(originalPassword);
    await page.getByTestId('login-submit').click();
    await page.waitForURL('**/internal/admin/pos');
    await page.goto('/internal/admin/manage-account');
    await expect(page.getByTestId('offline-owner-enrollment')).toBeVisible();
    const restrictedContext = await browser.newContext();
    try {
        const restricted = await restrictedContext.newPage();
        await restricted.goto('/internal/admin/pos/login');
        await restricted.getByTestId('login-email').fill('e2e-inventory@example.invalid');
        await restricted.getByTestId('login-password').fill(originalPassword);
        await restricted.getByTestId('login-submit').click();
        await restricted.waitForURL('**/internal/admin/pos');
        await restricted.goto('/internal/admin/manage-account');
        await expect(restricted.getByTestId('offline-owner-enrollment')).toHaveCount(0);
    } finally { await restrictedContext.close(); }
    await page.getByTestId('offline-owner-current').fill(originalPassword);
    const issue = page.waitForResponse(r => r.url().endsWith('/internal/admin/auth/offline-owner-recovery/rotate') && r.request().method() === 'POST');
    await page.getByTestId('offline-owner-rotate').click();
    expect((await issue).status()).toBe(200);
    const codes = page.getByTestId('offline-owner-codes').locator('li');
    await expect(codes).toHaveCount(8);
    const code = (await codes.first().innerText()).trim();
    expect(code).toMatch(/^(?:[A-F0-9]{6}-){5}[A-F0-9]{6}$/);
    await page.getByRole('button', {name:'Hide secret codes'}).click();
    await expect(page.getByTestId('offline-owner-codes')).toHaveCount(0);
    await page.reload();
    await expect(page.getByTestId('offline-owner-codes')).toHaveCount(0);
    const guestContext = await browser.newContext();
    try {
        const recovery = await guestContext.newPage();
        await recovery.goto('/internal/admin/reset-password?offline=1');
        await expect(recovery.getByTestId('offline-owner-recovery-code')).toBeVisible();
        await recovery.getByTestId('recovery-email').fill(ownerEmail);
        await recovery.getByTestId('offline-owner-recovery-code').fill(code);
        await recovery.getByTestId('recovery-new').fill(recoveredPassword);
        await recovery.getByTestId('recovery-confirm').fill(recoveredPassword);
        const redeem = recovery.waitForResponse(r => r.url().endsWith('/internal/admin/auth/offline-owner-recovery/redeem') && r.request().method() === 'POST');
        await recovery.getByTestId('recovery-submit').click();
        expect((await redeem).status()).toBe(200);
        await expect(recovery.getByTestId('recovery-message')).toHaveText('Password updated. Sign in again.');
        expect((await page.goto('/internal/admin/account'))?.status()).toBe(401);
        await recovery.goto('/internal/admin/pos/login');
        await recovery.getByTestId('login-email').fill(ownerEmail);
        await recovery.getByTestId('login-password').fill(recoveredPassword);
        await recovery.getByTestId('login-submit').click();
        await recovery.waitForURL('**/internal/admin/pos');
        await expect(recovery.getByTestId('member-name')).toHaveText('E2E Protected Owner');
    } finally { await guestContext.close(); }
});
