<?php

namespace App\Services\Cms;

use App\Enums\ContactMessageStatus;
use App\Models\ContactMessage;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;

/**
 * The contact form inbox (M5.8). Messages arrive from the public site in M7.15.
 */
class ContactMessageService
{
    /**
     * Newest first. Without a status filter, spam is left out.
     *
     * @param  array{search?: string|null, status?: string|null}  $filters
     * @return LengthAwarePaginator<int, ContactMessage>
     */
    public function paginate(array $filters, int $perPage = 20): LengthAwarePaginator
    {
        return ContactMessage::query()
            ->with(['car:id,name,slug', 'handler:id,name'])
            ->when(
                $filters['status'] ?? null,
                fn (Builder $query, string $status) => $query->where('status', $status),
                fn (Builder $query) => $query->where('status', '!=', ContactMessageStatus::Spam),
            )
            ->when($filters['search'] ?? null, fn (Builder $query, string $search) => $query->where(
                fn (Builder $query) => $query
                    ->where('name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%")
                    ->orWhere('phone', 'like', "%{$search}%"),
            ))
            ->latest()
            ->orderByDesc('id')
            ->paginate($perPage)
            ->withQueryString();
    }

    /**
     * @return array<string, int> status => number of messages
     */
    public function counts(): array
    {
        /** @var array<string, int> */
        return ContactMessage::query()
            ->selectRaw('status, count(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status')
            ->map(fn ($total) => (int) $total)
            ->all();
    }

    /**
     * Any status may follow any other (D26). The staff member who moves it is recorded.
     */
    public function setStatus(ContactMessage $message, ContactMessageStatus $status, User $actor): ContactMessage
    {
        if ($message->status === $status) {
            return $message;
        }

        $message->status = $status;
        $message->handled_by = $actor->id;
        $message->handled_at = now();
        $message->save();

        return $message;
    }

    public function delete(ContactMessage $message): void
    {
        $message->delete();
    }
}
