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
