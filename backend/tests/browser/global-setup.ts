import { execFileSync } from 'node:child_process';

export default async function globalSetup() {
    execFileSync('php', ['artisan', 'migrate', '--env=testing', '--force'], {
        cwd: process.cwd(),
        stdio: 'inherit',
    });
    execFileSync('php', [
        'artisan',
        'db:seed',
        '--class=Database\\Seeders\\PosShellE2eSeeder',
        '--env=testing',
        '--force',
    ], {
        cwd: process.cwd(),
        stdio: 'inherit',
    });
}
