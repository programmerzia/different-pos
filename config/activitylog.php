<?php

declare(strict_types=1);

use App\Models\Activity;

return [

    /*
     * If set to false, no activities will be saved to the database.
     */
    'enabled' => env('ACTIVITYLOG_ENABLED', true),

    /*
     * activitylog:clean deletes activities older than this many days.
     */
    'delete_records_older_than_days' => 365,

    /*
     * If no log name is passed to the activity() helper
     * we use this default log name.
     */
    'default_log_name' => 'default',

    /*
     * You can specify an auth driver here that gets user models.
     * If this is null we'll use the current Laravel auth driver.
     */
    'default_auth_driver' => null,

    /*
     * If set to true, the subject relationship on activities
     * will include soft deleted models.
     */
    'subject_returns_soft_deleted_models' => false,

    /*
     * The model used to log activity. It stamps tenant_id and scrubs secrets on every row.
     */
    'activity_model' => Activity::class,

    'table_name' => env('ACTIVITY_LOGGER_TABLE_NAME', 'activity_log'),

    'database_connection' => env('ACTIVITY_LOGGER_DB_CONNECTION'),
];
