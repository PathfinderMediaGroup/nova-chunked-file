<?php

declare(strict_types=1);

namespace PathfinderMediaGroup\NovaChunkedFile\Console;

use PathfinderMediaGroup\NovaChunkedFile\ChunkedUploads;
use Illuminate\Console\Command;

class PruneChunkedUploads extends Command
{
    protected $signature = 'chunked-file:prune';

    protected $description = 'Remove abandoned chunked Nova uploads';

    public function handle(ChunkedUploads $uploads): int
    {
        $this->info(\sprintf('Pruned %d abandoned upload(s).', $uploads->prune()));

        return self::SUCCESS;
    }
}
