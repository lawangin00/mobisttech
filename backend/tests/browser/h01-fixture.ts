import { execFileSync } from 'node:child_process';

export function runH01Fixture(
    seeder: string,
    enabledVariable: string,
    actionVariable: string,
    action: 'seed' | 'cleanup',
) {
    execFileSync('php', [
        'artisan',
        'db:seed',
        '--class=Database\\Seeders\\' + seeder,
        '--env=testing',
        '--force',
    ], {
        cwd: process.cwd(),
        stdio: 'inherit',
        env: {
            ...process.env,
            [enabledVariable]: '1',
            [actionVariable]: action,
        },
    });
}
