import { execFileSync } from 'node:child_process';
import { mkdirSync } from 'node:fs';
import { resolve } from 'node:path';

export function runH01Fixture(
    seeder: string,
    enabledVariable: string,
    actionVariable: string,
    action: 'seed' | 'cleanup',
) {
    // Clean CI checkouts do not contain the ignored repo-local evidence directory.
    // Create it before a guarded seeder can mutate the database and persist its exact-owner receipt.
    mkdirSync(resolve(process.cwd(), '..', '.local'), { recursive: true, mode: 0o700 });

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
