<?php

return [
    'enabled' => filter_var(env('PUBLIC_API_ENABLED', false), FILTER_VALIDATE_BOOL),
    'rate_limit' => (int) env('PUBLIC_API_RATE_LIMIT', 60),
    'webhook_rate_limit' => (int) env('WAHA_WEBHOOK_RATE_LIMIT', 120),
    'max_per_page' => (int) env('PUBLIC_API_MAX_PER_PAGE', 100),
    'idempotency_ttl_hours' => (int) env('PUBLIC_API_IDEMPOTENCY_TTL_HOURS', 24),
];
