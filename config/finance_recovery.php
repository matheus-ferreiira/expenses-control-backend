<?php

return [
    // Enable only in an explicitly authorized maintenance window, after backup/restore verification.
    'approved_target_enabled' => (bool) env('FINANCE_RECOVERY_APPROVED_TARGET_ENABLED', false),
    'approved_host' => env('FINANCE_RECOVERY_APPROVED_HOST'),
    'approved_database' => env('FINANCE_RECOVERY_APPROVED_DATABASE'),
    'approved_user' => env('FINANCE_RECOVERY_APPROVED_USER'),
    'approved_plan_sha256' => env('FINANCE_RECOVERY_APPROVED_PLAN_SHA256'),
];
