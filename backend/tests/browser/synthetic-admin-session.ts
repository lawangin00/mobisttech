import { expect, type Page } from '@playwright/test';

// Release only the session held by this synthetic browser context; never reset server sessions.
export async function releaseSyntheticAdminSession(page: Page): Promise<void> {
    if (page.isClosed()) return;
    const response = await page.goto('/internal/admin/pos');
    if (response?.status() !== 200) return;
    const logout = page.getByTestId('logout');
    if (await logout.count() !== 1) return;
    const signedOut = page.waitForResponse(r => r.url().endsWith('/internal/admin/auth/logout')
        && r.request().method() === 'POST');
    await logout.click();
    expect((await signedOut).status()).toBe(200);
    await page.waitForURL('**/internal/admin/pos/login');
}
