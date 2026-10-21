<?php

namespace App\Services\Pricing\Fx;

final readonly class FxRefreshResult
{
    public const STATUS_REFRESHED = 'refreshed';

    public const STATUS_SKIPPED = 'skipped';

    public const STATUS_FAILED = 'failed';

    /**
     * @param  array<string, string>  $stored  code => market rate
     * @param  array<string, array{previous: string, fetched: string, change: string}>  $rejected  moved more than the jump guard allows
     */
    public function __construct(
        public string $status,
        public string $message,
        public array $stored = [],
        public array $rejected = [],
    ) {}

    public function failed(): bool
    {
        return $this->status === self::STATUS_FAILED;
    }

    /**
     * @return array{status: string, message: string, stored: array<string, string>, rejected: array<string, array{previous: string, fetched: string, change: string}>}
     */
    public function toArray(): array
    {
        return [
            'status' => $this->status,
            'message' => $this->message,
            'stored' => $this->stored,
            'rejected' => $this->rejected,
        ];
    }
}
