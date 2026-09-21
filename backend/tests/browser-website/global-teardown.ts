import { execFileSync } from 'node:child_process';

export default async function globalTeardown() {
    execFileSync('php', [
        'artisan', 'db:seed', '--class=Database\\Seeders\\ClientProjectWebsiteE2eCleanupSeeder',
        '--env=testing', '--force',
    ], { cwd: process.cwd(), stdio: 'inherit' });
    execFileSync('php', [
        'artisan', 'db:seed', '--class=Database\\Seeders\\DynamicWebsiteE2eCleanupSeeder',
        '--env=testing', '--force',
    ], { cwd: process.cwd(), stdio: 'inherit' });
    execFileSync('php', [
        'artisan', 'db:seed', '--class=Database\\Seeders\\CustomerWebsiteE2eCleanupSeeder',
        '--env=testing', '--force',
    ], { cwd: process.cwd(), stdio: 'inherit' });
    execFileSync('php', [
        'artisan', 'db:seed', '--class=Database\\Seeders\\WebsiteStorefrontE2eCleanupSeeder',
        '--env=testing', '--force',
    ], { cwd: process.cwd(), stdio: 'inherit' });
    execFileSync('php', [
        'artisan', 'db:seed', '--class=Database\\Seeders\\PosShellE2eCleanupSeeder',
        '--env=testing', '--force',
    ], { cwd: process.cwd(), stdio: 'inherit' });
    // Counts and table names only, from the guarded disposable CI schema.
    // Measure residue after each Website fixture teardown; never hide it from the final gate.
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
