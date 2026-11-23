<?php

return [
    'enabled' => env('CLINIC_BACKUP_ENABLED', false),
    'disk' => env('CLINIC_BACKUP_DISK', 'clinical_backups'),
    'key_file' => env('CLINIC_BACKUP_KEY_FILE'),
    'offsite_confirmed' => env('CLINIC_BACKUP_OFFSITE_CONFIRMED', false),
    'maximum_age_hours' => 26,
];
