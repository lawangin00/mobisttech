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
    execFileSync('php', ['artisan', 'cache:clear', '--env=testing'], {
        cwd: process.cwd(),
        stdio: 'inherit',
    });
}
