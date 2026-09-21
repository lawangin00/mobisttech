import { execFileSync } from 'node:child_process';
import { rmSync } from 'node:fs';
import { resolve } from 'node:path';

export default async function globalSetup() {
    // Browser acceptance uses the built Vite manifest, never a development HMR server.
    // A force-stopped local Vite process can leave public/hot pointing at a dead port.
    rmSync(resolve(process.cwd(), 'public', 'hot'), { force: true });
    execFileSync('php', ['artisan', 'migrate', '--env=testing', '--force'], {
        cwd: process.cwd(),
        stdio: 'inherit',
    });
    // The guarded fresh-owner seeder requires an empty synthetic database and must
    // precede shared POS fixtures. Its explicit opt-in is scoped to the child process;
    // production bootstrap and local non-CI browser runs remain unchanged.
    if (process.env.CI === 'true' || process.env.MT75_FIRST_OUTLET_E2E_ENABLED === '1') {
        execFileSync('php', [
            'artisan',
            'db:seed',
            '--class=Database\\Seeders\\FirstOutletE2eSeeder',
            '--env=testing',
            '--force',
        ], {
            cwd: process.cwd(),
            stdio: 'inherit',
            env: { ...process.env, MT75_FIRST_OUTLET_E2E_ENABLED: '1' },
        });
    }
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
