<?php

namespace Tests\Support;

use App\Services\Media\Contracts\TokenUploaderResolver;
use Illuminate\Database\Eloquent\Model;

/**
 * Stands in for the booking token lookup, so media tests can use any owner model.
 */
class FakeTokenResolver implements TokenUploaderResolver
{
    /**
     * @param  array<string, Model>  $tokens
     */
    public function __construct(private readonly array $tokens) {}

    public function resolve(string $plainToken): ?Model
    {
        return $this->tokens[$plainToken] ?? null;
    }
}
