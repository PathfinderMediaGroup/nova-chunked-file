<?php

declare(strict_types=1);

namespace PathfinderMediaGroup\NovaChunkedFile\Http\Middleware;

use Closure;
use PathfinderMediaGroup\NovaChunkedFile\ChunkedUploads;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\Response;

/**
 * Replaces "chunked-upload:{uuid}" tokens in a Nova form submission with the
 * assembled file, so the ChunkedFile field (a regular Nova File field) sees a
 * normal UploadedFile and validation, storage and pruning work unchanged.
 */
class ResolveChunkedUploads
{
    /** @var list<string> */
    private array $resolved = [];

    public function __construct(private readonly ChunkedUploads $uploads)
    {
    }

    public function handle(Request $request, Closure $next): Response
    {
        if ($request->isMethod('GET') || $request->user() === null) {
            return $next($request);
        }

        $files = [];
        $input = $this->extract($request->request->all(), $files, [], $request);

        if ($files !== []) {
            $request->request->replace($input);
            $request->files->replace(array_replace_recursive($request->files->all(), $files));

            // Make sure Laravel's cached file conversion is rebuilt.
            (fn () => $this->convertedFiles = null)->call($request);
        }

        $response = $next($request);

        // Keep the upload around on validation errors so the form can be resubmitted.
        if ($response->isSuccessful()) {
            foreach ($this->resolved as $token) {
                $this->uploads->forget($token);
            }
        }

        return $response;
    }

    /**
     * @param array<array-key, mixed> $input
     * @param array<array-key, mixed> $files
     * @param list<array-key>         $path
     *
     * @return array<array-key, mixed>
     */
    private function extract(array $input, array &$files, array $path, Request $request): array
    {
        foreach ($input as $key => $value) {
            $current = [...$path, $key];

            if (\is_array($value)) {
                $input[$key] = $this->extract($value, $files, $current, $request);

                continue;
            }

            if (! $this->uploads->isToken($value)) {
                continue;
            }

            $file = $this->uploads->resolve($value, $request->user()->getAuthIdentifier());

            if ($file === null) {
                throw ValidationException::withMessages([
                    implode('.', $current) => __('The uploaded file has expired, please upload it again.'),
                ]);
            }

            unset($input[$key]);
            $this->resolved[] = $value;

            $target = &$files;
            foreach ($current as $segment) {
                $target[$segment] ??= [];
                $target = &$target[$segment];
            }
            $target = $file;
            unset($target);
        }

        return $input;
    }
}
