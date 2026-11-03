import { Ban, Database, Download, LogIn, Play, Plus } from 'lucide-react';
import { actions } from './actions';

let registered = false;

/** Backup rows, tenant rows (platform console) and their palette commands. Idempotent; wired in defaults.ts. */
export function registerPlatformActions(): void {
    if (registered) {
        return;
    }
    registered = true;
    actions.register(
        {
            id: 'backup.download',
            entity: 'backup',
            labelKey: 'backup.download',
            icon: Download,
            permission: 'backup.run',
            requires: 'handler',
            visible: (row: { download_url?: string | null }) =>
                typeof row.download_url === 'string',
            run: ({ row, handlers }) => handlers?.['backup.download']?.(row),
        },
        {
            id: 'cmd.backup_now',
            entity: null,
            labelKey: 'backup.run_now',
            icon: Database,
            permission: 'backup.run',
            href: '/app/settings/backups?run=1',
        },
    );
    actions.register(
        {
            id: 'tenant.impersonate',
            entity: 'tenant',
            labelKey: 'platform.access',
            icon: LogIn,
            permission: 'platform.admin',
            requires: 'handler',
            visible: (row: { status?: string }) => row.status === 'active',
            run: ({ row, handlers }) => handlers?.['tenant.impersonate']?.(row),
        },
        {
            id: 'tenant.suspend',
            entity: 'tenant',
            labelKey: 'platform.suspend',
            icon: Ban,
            permission: 'platform.admin',
            group: 'danger',
            requires: 'handler',
            visible: (row: { status?: string }) => row.status === 'active',
            run: ({ row, handlers }) => handlers?.['tenant.suspend']?.(row),
        },
        {
            id: 'tenant.resume',
            entity: 'tenant',
            labelKey: 'platform.resume',
            icon: Play,
            permission: 'platform.admin',
            requires: 'handler',
            visible: (row: { status?: string }) => row.status === 'suspended',
            run: ({ row, handlers }) => handlers?.['tenant.resume']?.(row),
        },
        {
            id: 'cmd.provision_tenant',
            entity: null,
            labelKey: 'platform.provision',
            icon: Plus,
            permission: 'platform.admin',
            href: '/platform?provision=1',
        },
    );
}
