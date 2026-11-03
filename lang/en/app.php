<?php

declare(strict_types=1);

/* Server-side strings (flash messages, API errors). UI text lives in resources/js/i18n. */
return [
    'saved' => 'Saved.',
    'logo_saved' => 'Logo saved.',
    'logo_removed' => 'Logo removed.',
    'password_changed' => 'Password changed.',
    'created' => 'Created.',
    'updated' => 'Updated.',
    'pin_reset' => 'PIN updated for :name.',
    'customer_pin' => [
        'set' => 'Security PIN set for :name.',
        'reset' => 'Security PIN cleared for :name. They choose a new one at their next visit.',
        'unlocked' => 'Security PIN unlocked for :name.',
        'errors' => [
            'format' => 'The PIN must be 4 to 6 digits.',
            'mismatch' => 'The two PIN entries do not match.',
            'weak' => 'This PIN is too easy to guess. Avoid repeated or sequential digits and the phone number.',
            'reason' => 'A reason is required.',
            'already_set' => 'This customer already has a PIN. Ask a manager to reset it.',
        ],
    ],
    'deleted' => 'Deleted.',
    'forbidden' => 'You do not have permission to do that.',
    'not_found' => 'Not found.',
    'preferences_saved' => 'Preferences saved.',
    'branch_required' => 'Select a branch to continue.',
    'sale_voided' => 'Sale voided.',
    'backup_started' => 'Backup started. This page updates when it finishes.',
    'tenant_provisioned' => 'Business :name created. The Owner invitation was sent to :email.',
    'tenant_suspended' => 'Business :name suspended. Its users are signed out on their next request.',
    'tenant_resumed' => 'Business :name resumed.',
    'impersonation_started' => 'You are now signed in as :name. Use Exit in the banner to return.',
    'impersonation_ended' => 'Back on the platform console.',
    'impersonation_expired' => 'The impersonation session expired after 60 minutes.',
    'impersonation_no_owner' => 'This business has no active Owner to sign in as.',
    'sale_saved' => 'Sale completed.',
    'system_method_missing' => 'The business has no active :method payment method. Add it under Settings, Payment methods.',
    'export' => [
        'generated' => 'Generated :at',
        'period' => 'Period: :period',
        'rows' => ':count rows',
        'page' => 'Page',
        'empty' => 'No rows for this selection.',
        'pdf_too_large' => 'This export has :count rows; a PDF holds up to :max. Narrow the period or filters, or download it as Excel or CSV.',
    ],
    'method_types' => [
        'cash' => 'cash',
        'customer_credit' => 'store credit',
        'exchange_credit' => 'exchange credit',
    ],
];
