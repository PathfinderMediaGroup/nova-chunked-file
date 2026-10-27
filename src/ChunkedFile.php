<?php

declare(strict_types=1);

namespace PathfinderMediaGroup\NovaChunkedFile;

use Laravel\Nova\Fields\File;

/**
 * Drop-in replacement for Nova's File field that uploads the file in chunks
 * before the form is submitted. Everything else (disk, path, storeAs,
 * validation rules, prunable, download, ...) behaves exactly like File.
 */
class ChunkedFile extends File
{
    public $component = 'chunked-file';

    #[\Override]
    public function jsonSerialize(): array
    {
        return array_merge(parent::jsonSerialize(), [
            'chunkSize' => (int) config('chunked-file.chunk_size'),
        ]);
    }
}
