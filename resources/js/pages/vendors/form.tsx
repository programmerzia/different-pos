import { Head } from '@inertiajs/react';
import { Page } from '@/components/page-header';
import {
    VendorForm,
    type VendorFormValue,
} from '@/components/vendors/vendor-form';
import { useT } from '@/i18n';

export default function VendorFormPage({
    vendor,
}: {
    vendor: VendorFormValue | null;
}) {
    const { t } = useT();

    return (
        <>
            <Head title={vendor ? t('vendors.edit') : t('vendors.new')} />
            <Page>
                <VendorForm vendor={vendor} variant="page" />
            </Page>
        </>
    );
}
