<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Import chunking
    |--------------------------------------------------------------------------
    |
    | Posts and pages are processed in chunks of this size, each wrapped in a
    | DB transaction. Smaller chunks = more frequent commits and lower memory,
    | but more transaction overhead.
    */
    'chunk_size' => env('WP_IMPORTER_CHUNK_SIZE', 50),

    /*
    |--------------------------------------------------------------------------
    | Streaming parser threshold
    |--------------------------------------------------------------------------
    |
    | If the uploaded XML exceeds this many bytes, switch from SimpleXMLElement
    | (loads everything in memory) to XMLReader (streams). 25 MB is a safe
    | default — below that, the simple parser is faster.
    */
    'streaming_threshold_bytes' => env('WP_IMPORTER_STREAMING_THRESHOLD_BYTES', 25 * 1024 * 1024),

    /*
    |--------------------------------------------------------------------------
    | Image queue
    |--------------------------------------------------------------------------
    */
    'queue' => env('WP_IMPORTER_QUEUE', 'default'),
    'image_concurrency' => env('WP_IMPORTER_IMAGE_CONCURRENCY', 5),
    'image_timeout_seconds' => env('WP_IMPORTER_IMAGE_TIMEOUT', 30),
    'image_max_bytes' => env('WP_IMPORTER_IMAGE_MAX_BYTES', 20 * 1024 * 1024),

    /*
    |--------------------------------------------------------------------------
    | SSRF allow-list
    |--------------------------------------------------------------------------
    |
    | Hosts allowed when image_mode = queue. Use '*' to allow any host. Use
    | exact hostnames ('example.com') or wildcard subdomains ('*.example.com').
    | Defaults to wildcard for backward compatibility — tighten this in
    | production if the source is untrusted.
    */
    'allowed_image_hosts' => array_filter(explode(',', (string) env('WP_IMPORTER_ALLOWED_IMAGE_HOSTS', '*'))) ?: ['*'],
];
