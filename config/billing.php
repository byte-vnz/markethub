<?php

return [
    'scheduled_drafts' => (bool) env('BILLING_SCHEDULED_DRAFTS', false),
    'actor' => env('BILLING_ACTOR_ID'),
    'due_day' => (int) env('BILLING_DUE_DAY', 0),
    // Enable only after the fixed-day migration has been rehearsed and applied.
    'fixed_day_enabled' => env('FIXED_DAY_BILLING_ENABLED', false),
    'default_interval_days' => env('FIXED_DAY_BILLING_DAYS', 7),
];
