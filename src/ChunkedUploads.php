<?php

declare(strict_types=1);

namespace PathfinderMediaGroup\NovaChunkedFile;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use RuntimeException;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

/**
 * Stores incoming chunks on the local filesystem and assembles them into a
 * single file that can later be handed to Nova as a regular UploadedFile.
 *
 * Layout per upload: {temporary_path}/{uuid}/meta.json + {uuid}/data
 */
class ChunkedUploads
{
    public const string TOKEN_PREFIX = 'chunked-upload:';

    /**
     * Append a chunk to the upload. Chunks must arrive in order; a chunk that
     * was already received (e.g. a retry) is ignored.
     *
     * @return array{complete: bool, next_chunk: int, token: string|null}
     */
    public function append(string $uploadId, int|string $userId, int $index, int $total, string $filename, UploadedFile $chunk): array
    {
        $directory = $this->directory($uploadId);
        File::ensureDirectoryExists($directory);

        $lock = fopen($directory.'/.lock', 'c');
        flock($lock, LOCK_EX);

        try {
            $meta = $this->meta($uploadId) ?? [
                'user_id' => (string) $userId,
                'filename' => $filename,
                'total' => $total,
                'next' => 0,
                'size' => 0,
            ];

            if ($meta['user_id'] !== (string) $userId) {
                throw new AccessDeniedHttpException();
            }

            if ($index === $meta['next'] && $meta['next'] < $meta['total']) {
                $source = fopen($chunk->getRealPath(), 'rb');
                $target = fopen($directory.'/data', $index === 0 ? 'wb' : 'ab');

                $written = stream_copy_to_stream($source, $target);

                fclose($source);
                fclose($target);

                if ($written === false) {
                    throw new RuntimeException('Unable to write chunk.');
                }

                $meta['next']++;
                $meta['size'] += $written;

                File::put($directory.'/meta.json', json_encode($meta, JSON_THROW_ON_ERROR));
            }
        } finally {
            flock($lock, LOCK_UN);
            fclose($lock);
        }

        $complete = $meta['next'] >= $meta['total'];

        return [
            'complete' => $complete,
            'next_chunk' => $meta['next'],
            'token' => $complete ? self::TOKEN_PREFIX.$uploadId : null,
        ];
    }

    /**
     * Turn a token submitted with the Nova form into an UploadedFile, provided
     * the upload is complete and belongs to the given user.
     */
    public function resolve(string $token, int|string $userId): ?UploadedFile
    {
        $uploadId = $this->uploadIdFromToken($token);

        if ($uploadId === null || ($meta = $this->meta($uploadId)) === null) {
            return null;
        }

        $path = $this->directory($uploadId).'/data';

        if ($meta['user_id'] !== (string) $userId || $meta['next'] < $meta['total'] || ! is_file($path)) {
            return null;
        }

        // "test" mode so Symfony doesn't insist on is_uploaded_file().
        return new UploadedFile($path, $meta['filename'], null, UPLOAD_ERR_OK, true);
    }

    public function isToken(mixed $value): bool
    {
        return \is_string($value) && str_starts_with($value, self::TOKEN_PREFIX);
    }

    public function forget(string $token): void
    {
        if (($uploadId = $this->uploadIdFromToken($token)) !== null) {
            File::deleteDirectory($this->directory($uploadId));
        }
    }

    /**
     * Remove uploads that were not touched for the configured amount of time.
     */
    public function prune(): int
    {
        $root = config('chunked-file.temporary_path');

        if (! is_dir($root)) {
            return 0;
        }

        $threshold = now()->subMinutes((int) config('chunked-file.expire_after'))->getTimestamp();
        $pruned = 0;

        foreach (File::directories($root) as $directory) {
            if (File::lastModified($directory) < $threshold) {
                File::deleteDirectory($directory);
                $pruned++;
            }
        }

        return $pruned;
    }

    private function uploadIdFromToken(string $token): ?string
    {
        if (! $this->isToken($token)) {
            return null;
        }

        $uploadId = substr($token, \strlen(self::TOKEN_PREFIX));

        return Str::isUuid($uploadId) ? $uploadId : null;
    }

    private function directory(string $uploadId): string
    {
        if (! Str::isUuid($uploadId)) {
            throw new RuntimeException('Invalid upload id.');
        }

        return config('chunked-file.temporary_path').'/'.strtolower($uploadId);
    }

    /**
     * @return array{user_id: string, filename: string, total: int, next: int, size: int}|null
     */
    private function meta(string $uploadId): ?array
    {
        $path = $this->directory($uploadId).'/meta.json';

        return is_file($path) ? json_decode(File::get($path), true, flags: JSON_THROW_ON_ERROR) : null;
    }
}
