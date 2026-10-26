<?php

return [
    'enabled' => env('SSO_ENABLED', false),
    'server_url' => env('SSO_SERVER_URL', 'https://sivmi.id'),
    'client_id' => env('SSO_CLIENT_ID'),
    'client_secret' => env('SSO_CLIENT_SECRET'),
    'redirect_uri' => env('SSO_REDIRECT_URI', 'https://crm.sivmi.id/sso/callback'),
];
