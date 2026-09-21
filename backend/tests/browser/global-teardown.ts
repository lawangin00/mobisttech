import { execFileSync } from 'node:child_process';

export default async function globalTeardown() {
    execFileSync('php', [
        'artisan',
        'db:seed',
        '--class=Database\\Seeders\\PosShellE2eCleanupSeeder',
        '--env=testing',
        '--force',
    ], {
        cwd: process.cwd(),
        stdio: 'inherit',
    });
    // Global setup creates a fresh synthetic owner and canonical master-data defaults
    // only in CI or the explicitly opted-in disposable browser scenario. Release the
    // scoped POS fixture first, then invoke the existing guarded fresh-owner cleanup.
    if (process.env.CI === 'true' || process.env.MT75_FIRST_OUTLET_E2E_ENABLED === '1') {
        execFileSync('php', [
            'artisan',
            'db:seed',
            '--class=Database\\Seeders\\FirstOutletE2eCleanupSeeder',
            '--env=testing',
            '--force',
        ], {
            cwd: process.cwd(),
            stdio: 'inherit',
            env: { ...process.env, MT75_FIRST_OUTLET_E2E_ENABLED: '1' },
        });
    }
    // Names and counts only; CI diagnosis never reads or prints business row values.
    if (process.env.CI === 'true') {
        execFileSync('php', ['tests/browser/fixture-residue-diagnostic.php'], {
            cwd: process.cwd(),
            stdio: 'inherit',
        });
    }
    execFileSync('php', ['artisan', 'cache:clear', '--env=testing'], {
        cwd: process.cwd(),
        stdio: 'inherit',
    });
}
