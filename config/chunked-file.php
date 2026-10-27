<?php

declare(strict_types=1);

return [
    /*
     * Size of a single chunk in bytes. Every chunk is sent as its own request,
     * so this must stay below the Cloudflare request body limit as well as
     * PHP's upload_max_filesize / post_max_size.
     */
    'chunk_size' => (int) env('CHUNKED_FILE_CHUNK_SIZE', 20 * 1024 * 1024),

    /*
     * Directory (on the local filesystem) where chunks are assembled until
     * the Nova form is submitted.
     */
    'temporary_path' => storage_path('app/chunked-uploads'),

    /*
     * Uploads that were never attached to a resource are removed by the
     * chunked-file:prune command after this many minutes.
     */
    'expire_after' => 24 * 60,
];
