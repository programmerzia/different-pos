import { usePage } from '@inertiajs/react';
import { TenantLogo } from '@/components/tenant-logo';

/** Top-bar mark: the business logo when it has one (else the generic mark) and the business or app name. */
export default function AppLogo() {
    const { name, tenant } = usePage().props;
    const label = tenant?.name ?? name;

    return (
        <>
            <TenantLogo
                url={tenant?.logo_url}
                name={label}
                className="size-8 shrink-0"
                imageClassName="p-0.5"
            />
            <div className="ml-1 hidden text-left text-sm md:grid">
                <span className="mb-0.5 truncate leading-tight font-semibold">
                    {label}
                </span>
            </div>
        </>
    );
}
