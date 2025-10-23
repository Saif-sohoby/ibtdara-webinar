<?php
return [
    'client_id' => env('ZOHO_CLIENT_ID', 'YOUR_CLIENT_ID'),
    'client_secret' => env('ZOHO_CLIENT_SECRET', 'YOUR_CLIENT_SECRET'),
    'refresh_token' => env('ZOHO_REFRESH_TOKEN', 'YOUR_REFRESH_TOKEN'),
    'api_domain' => env('ZOHO_API_DOMAIN', 'https://www.zohoapis.com'),
    'oauth_url' => 'https://accounts.zoho.com/oauth/v2/token',
    'base_url' => env('ZOHO_BASE_URL', 'https://meeting.zoho.com/api/v2'),
    'organization_id' => env('ZOHO_ORGANIZATION_ID', 'YOUR_ORGANIZATION_ID'),
];
