<?php

namespace App\Services\Booking;

use App\Enums\BookingRequestStatus;
use App\Models\BookingRequest;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;

/**
 * Renter requests from the portal, as staff see them (M10B). Handling one only records who
 * dealt with it and how: the cancellation or change itself is done by hand (M11).
 */
class BookingRequestService
{
    public const LOG_NAME = 'booking-requests';

    /**
     * Oldest open first; handled ones newest first.
     *
     * @param  array{search?: string|null, status?: string|null}  $filters
     * @return LengthAwarePaginator<int, BookingRequest>
     */
    public function paginate(array $filters, int $perPage = 20): LengthAwarePaginator
    {
        $status = BookingRequestStatus::tryFrom((string) ($filters['status'] ?? '')) ?? BookingRequestStatus::Open;

        return BookingRequest::query()
            ->with(['booking.car:id,name', 'booking.driver:id,booking_id,first_name,surname,phone,email', 'handler:id,name'])
            ->where('status', $status)
            ->when($filters['search'] ?? null, fn (Builder $query, string $search) => $query->whereHas(
                'booking',
                fn (Builder $booking) => $booking->where('reference', 'like', "%{$search}%"),
            ))
            ->orderBy('created_at', $status === BookingRequestStatus::Open ? 'asc' : 'desc')
            ->orderBy('id')
            ->paginate($perPage)
            ->withQueryString();
    }

    /**
     * @return array<string, int> status => number of requests
     */
    public function counts(): array
    {
        /** @var array<string, int> */
        return BookingRequest::query()
            ->selectRaw('status, count(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status')
            ->map(fn ($total) => (int) $total)
            ->all();
    }

    public function handle(BookingRequest $request, ?string $note, User $actor): BookingRequest
    {
        $note = $note !== null && trim($note) !== '' ? trim($note) : null;

        $request->forceFill([
            'status' => BookingRequestStatus::Handled,
            'staff_note' => $note,
            'handled_by' => $actor->id,
            'handled_at' => now(),
        ])->save();

        activity(self::LOG_NAME)->performedOn($request->booking)->causedBy($actor)
            ->withProperties(['request' => $request->id, 'type' => $request->type->value, 'note' => $note])
            ->event('handled')
            ->log("{$request->type->label()} request handled");

        return $request;
    }
}
