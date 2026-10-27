<?php

declare(strict_types=1);

namespace PathfinderMediaGroup\NovaChunkedFile\Http\Controllers;

use PathfinderMediaGroup\NovaChunkedFile\ChunkedUploads;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ChunkUploadController
{
    public function __invoke(Request $request, ChunkedUploads $uploads): JsonResponse
    {
        $maxChunkKilobytes = (int) ceil(config('chunked-file.chunk_size') / 1024);

        $validated = $request->validate([
            'upload_id' => ['required', 'uuid'],
            'chunk_index' => ['required', 'integer', 'min:0'],
            'total_chunks' => ['required', 'integer', 'min:1', 'gt:chunk_index'],
            'filename' => ['required', 'string', 'max:255'],
            'chunk' => ['required', 'file', 'max:'.$maxChunkKilobytes],
        ]);

        $result = $uploads->append(
            $validated['upload_id'],
            $request->user()->getAuthIdentifier(),
            (int) $validated['chunk_index'],
            (int) $validated['total_chunks'],
            basename($validated['filename']),
            $request->file('chunk'),
        );

        // A chunk further ahead than expected: tell the client where to resume.
        $status = $result['next_chunk'] < (int) $validated['chunk_index'] ? 409 : 200;

        return response()->json($result, $status);
    }
}
