<?php

namespace App\Services\Audit;

use Closure;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Spatie\Activitylog\Models\Activity;

class ActivityLogService
{
    /**
     * @param  array{log?: string|null, event?: string|null, causer?: int|string|null, subject_type?: string|null, from?: string|null, to?: string|null}  $filters
     * @return LengthAwarePaginator<int, Activity>
     */
    public function paginate(array $filters, int $perPage = 30): LengthAwarePaginator
    {
        return Activity::query()
            ->with(['causer', 'subject'])
            ->when($filters['log'] ?? null, fn (Builder $query, string $log) => $query->where('log_name', $log))
            ->when($filters['event'] ?? null, fn (Builder $query, string $event) => $query->where('event', $event))
            ->when($filters['causer'] ?? null, fn (Builder $query, int|string $causer) => $query->where('causer_id', $causer))
            ->when($filters['subject_type'] ?? null, fn (Builder $query, string $type) => $query->where('subject_type', $type))
            ->when($filters['from'] ?? null, fn (Builder $query, string $from) => $query->where('created_at', '>=', $from))
            ->when($filters['to'] ?? null, fn (Builder $query, string $to) => $query->where('created_at', '<', now()->parse($to)->addDay()))
            ->latest('id')
            ->paginate($perPage)
            ->withQueryString();
    }

    /**
     * Distinct values for the filter dropdowns.
     *
     * @return array{logs: array<int, string>, events: array<int, string>, subject_types: array<int, string>}
     */
    public function filterOptions(): array
    {
        /** @var Closure(string): array<int, string> $distinct */
        $distinct = fn (string $column) => Activity::query()->whereNotNull($column)->distinct()->orderBy($column)->pluck($column)->all();

        return [
            'logs' => $distinct('log_name'),
            'events' => $distinct('event'),
            'subject_types' => $distinct('subject_type'),
        ];
    }
}
