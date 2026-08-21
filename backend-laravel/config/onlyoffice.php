<?php

return [
    /*
    |--------------------------------------------------------------------------
    | ONLYOFFICE Docs
    |--------------------------------------------------------------------------
    |
    | server_url is loaded by the user's browser. internal_url is optional and
    | is used only by Laravel for server-to-server downloads from Document
    | Server. Both origins are accepted by the callback SSRF allowlist.
    |
    */
    'server_url' => env('ONLYOFFICE_SERVER_URL'),
    'internal_url' => env('ONLYOFFICE_INTERNAL_URL'),
    'jwt_secret' => env('ONLYOFFICE_JWT_SECRET'),
    'jwt_header' => env('ONLYOFFICE_JWT_HEADER', 'Authorization'),
    'jwt_header_prefix' => env('ONLYOFFICE_JWT_HEADER_PREFIX', 'Bearer'),
    // JWT TTL is expressed in seconds; signed route TTL values are minutes.
    'config_token_ttl_seconds' => (int) env('ONLYOFFICE_CONFIG_TOKEN_TTL_SECONDS', 3600),
    'document_url_ttl_minutes' => (int) env('ONLYOFFICE_DOCUMENT_URL_TTL_MINUTES', 15),
    'callback_url_ttl_minutes' => (int) env('ONLYOFFICE_CALLBACK_URL_TTL_MINUTES', 1440),
    // Soft lock renewed by status 1/6; it must be shorter than callback TTL.
    'session_lease_minutes' => (int) env('ONLYOFFICE_SESSION_LEASE_MINUTES', 120),
    // Short reservation while the browser iframe connects to Document Server.
    'session_startup_lease_minutes' => (int) env('ONLYOFFICE_SESSION_STARTUP_LEASE_MINUTES', 5),
    // Browser-only token used to renew an already active editing lease.
    'session_token_ttl_seconds' => (int) env('ONLYOFFICE_SESSION_TOKEN_TTL_SECONDS', 86400),
    'heartbeat_interval_seconds' => (int) env('ONLYOFFICE_HEARTBEAT_INTERVAL_SECONDS', 60),
    // A pending rebuild older than this can be safely scheduled again.
    'master_rebuild_stale_minutes' => (int) env('ONLYOFFICE_MASTER_REBUILD_STALE_MINUTES', 10),
    'download_timeout' => (int) env('ONLYOFFICE_DOWNLOAD_TIMEOUT', 120),
    'max_document_bytes' => (int) env('ONLYOFFICE_MAX_DOCUMENT_BYTES', 10485760),
];
