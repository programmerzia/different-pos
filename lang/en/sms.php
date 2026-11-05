<?php

declare(strict_types=1);

return [
    'settings_saved' => 'SMS settings saved.',
    'test_sent' => 'Test SMS sent to :phone.',
    'test_failed' => 'Test SMS failed: :error',
    'requeued' => 'Message queued again.',
    'ad_hoc_queued' => ':count message(s) queued. They show as Queued in the log until the queue worker sends them.',
    'ad_hoc_none' => 'No customer in this selection has a valid mobile number.',
    'not_configured' => 'No SMS provider is configured. Choose one in Settings, SMS.',
    'sending_off' => 'SMS sending is switched off. Turn it on in Settings, SMS.',
    'sending_turned_on' => 'SMS sending turned on. Customers will receive messages again.',
    'sending_turned_off' => 'SMS sending turned off. No customer SMS will go out.',
    'notice' => [
        'sending_off' => 'SMS not sent: SMS sending is switched off (Settings > SMS).',
        'not_configured' => 'SMS not sent: the SMS gateway is not set up yet (Settings > SMS).',
    ],
    'whatsapp' => [
        'not_configured' => 'No WhatsApp provider is configured. Choose one in Settings, Messaging Providers.',
        'bulk_needs_api' => 'WhatsApp click-to-chat opens one chat at a time. Sending to many customers at once needs the WhatsApp Business Cloud API.',
        'test_sent' => 'Test WhatsApp message sent to :phone.',
        'test_failed' => 'Test WhatsApp message failed: :error',
    ],
    'direct' => [
        'too_many' => 'Send to at most :max customers at a time.',
    ],
    'template_unknown_placeholder' => 'Unknown detail in this template: :names. Use only the details listed for it.',
    'broadcast' => [
        'unfilled' => 'This message has details a broadcast cannot fill: :names. Remove them first. Sale, payment, return and cancel messages go out automatically with each transaction.',
        'duplicate' => 'This message was already sent to :count customer(s); it was not sent again.',
        'body_required' => 'Write the message first.',
        'body_too_long' => 'The message is longer than :max characters.',
        'no_target' => 'Choose who receives the message.',
    ],
];
