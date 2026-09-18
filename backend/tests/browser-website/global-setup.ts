import { execFileSync } from 'node:child_process';

export default async function globalSetup() {
    execFileSync('php', ['artisan', 'migrate', '--env=testing', '--force'], {
        cwd: process.cwd(),
        stdio: 'inherit',
    });
    for (const seeder of [
        'Database\\Seeders\\DynamicWebsiteE2eCleanupSeeder',
        'Database\\Seeders\\CustomerWebsiteE2eCleanupSeeder',
        'Database\\Seeders\\WebsiteStorefrontE2eCleanupSeeder',
        'Database\\Seeders\\PosShellE2eCleanupSeeder',
    ]) {
        execFileSync('php', ['artisan', 'db:seed', '--class=' + seeder, '--env=testing', '--force'], {
            cwd: process.cwd(),
            stdio: 'inherit',
        });
    }
    execFileSync('php', ['artisan', 'cache:clear', '--env=testing'], { cwd: process.cwd(), stdio: 'inherit' });
    execFileSync('php', [
        'artisan', 'db:seed', '--class=Database\\Seeders\\PosShellE2eSeeder',
        '--env=testing', '--force',
    ], { cwd: process.cwd(), stdio: 'inherit' });
    execFileSync('php', [
        'artisan', 'db:seed', '--class=Database\\Seeders\\WebsiteStorefrontE2eSeeder',
        '--env=testing', '--force',
    ], { cwd: process.cwd(), stdio: 'inherit' });
    execFileSync('php', [
        'artisan', 'db:seed', '--class=Database\\Seeders\\CustomerWebsiteE2eSeeder',
        '--env=testing', '--force',
    ], { cwd: process.cwd(), stdio: 'inherit' });
    execFileSync('php', [
        'artisan', 'db:seed', '--class=Database\\Seeders\\DynamicWebsiteE2eSeeder',
        '--env=testing', '--force',
    ], { cwd: process.cwd(), stdio: 'inherit' });
}
