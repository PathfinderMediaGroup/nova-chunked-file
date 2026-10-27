# Nova Chunked File

> **Note:** This package is intended for internal use at Pathfinder Media Group only. It is not supported for use outside the company.

A drop-in replacement for Laravel Nova's `File` field that uploads files in chunks before the form is submitted. Use it to get past request body limits from PHP (`upload_max_filesize` / `post_max_size`), Cloudflare and other proxies.

Each chunk is sent as its own request and assembled on the local filesystem. When the form is submitted, the field sends a token instead of the file, and a middleware replaces that token with the assembled `UploadedFile`. Validation, `disk()`, `path()`, `storeAs()`, `store()`, `prunable()`, downloads and the rest work exactly like Nova's `File` field.

## Installation

```bash
composer require pathfindermediagroup/nova-chunked-file
```

The service provider is auto-discovered.

## Usage

```php
use PathfinderMediaGroup\NovaChunkedFile\ChunkedFile;

ChunkedFile::make('Lua file')
    ->disk('local')
    ->rules('required');
```

This works in resource fields and in action fields.

## Configuration

Publish the config file:

```bash
php artisan vendor:publish --tag=chunked-file-config
```

| Option           | Default                           | Description                                                                                         |
|------------------|-----------------------------------|-----------------------------------------------------------------------------------------------------|
| `chunk_size`     | `20 MB` (`CHUNKED_FILE_CHUNK_SIZE`) | Size of one chunk in bytes. Keep it below your proxy body limit and PHP's upload limits.            |
| `temporary_path` | `storage/app/chunked-uploads`     | Local directory where chunks are assembled.                                                         |
| `expire_after`   | `1440` (minutes)                  | Uploads that were never attached to a resource are pruned after this time.                          |

## Pruning

Abandoned uploads are removed by `php artisan chunked-file:prune`. The package schedules this command hourly, so it runs as long as the Laravel scheduler is running.

## Development

```bash
composer install
npm install
npm run production
```

Commit the built `dist/` directory.

## License

MIT. See [LICENSE](LICENSE).
