import { execFileSync } from 'node:child_process';
import { rmSync } from 'node:fs';

const env = {
    ...process.env,
    DB_DATABASE: 'pos_e2e',
    APP_ENV: 'local',
    QUEUE_CONNECTION: 'sync',
};

function run(cmd: string, args: string[]) {
    execFileSync(cmd, args, { env, stdio: 'inherit' });
}

/** Fresh pos_e2e database (migrate + demo seed + the small e2e data set) and an up-to-date front-end build. */
export default function globalSetup() {
    // Session and hand-over files of a previous run belong to the old database.
    rmSync('e2e/.auth.json', { force: true });
    rmSync('e2e/.state.json', { force: true });
    const container = process.env.E2E_MYSQL_CONTAINER ?? 'pos-rewrite-mysql-1';
    if (!process.env.E2E_SKIP_DB_CREATE) {
        execFileSync(
            'docker',
            [
                'exec',
                container,
                'mysql',
                '-uroot',
                '-proot',
                '-e',
                'CREATE DATABASE IF NOT EXISTS pos_e2e',
            ],
            { stdio: 'inherit' },
        );
    }
    if (!process.env.E2E_SKIP_DB_RESET) {
        run('php', ['artisan', 'migrate:fresh', '--seed', '--force']);
        run('php', ['artisan', 'db:seed', '--class=E2eSeeder', '--force']);
    }
    if (!process.env.E2E_SKIP_BUILD) {
        run('npm', ['run', 'build']);
    }
}
