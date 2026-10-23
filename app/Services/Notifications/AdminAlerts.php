<?php

namespace App\Services\Notifications;

use App\Enums\AlertKind;
use App\Enums\Permission;
use App\Models\User;
use App\Notifications\StaffAlert;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Support\Facades\Notification;

/**
 * The in-app alerts behind the admin bell (M10B). An alert goes to every active staff
 * member who holds any of its kind's permissions, directly or through a role, and to the
 * super admins.
 */
class AdminAlerts
{
    public const LATEST = 10;

    public function send(AlertKind $kind, string $title, string $body, string $url): void
    {
        $recipients = $this->recipients($kind);

        if ($recipients->isNotEmpty()) {
            Notification::send($recipients, new StaffAlert($kind, $title, $body, $url));
        }
    }

    /**
     * @return Collection<int, User>
     */
    public function recipients(AlertKind $kind): Collection
    {
        $permissions = array_map(fn (Permission $permission) => $permission->value, $kind->audience());

        return User::query()
            ->where('is_staff', true)
            ->where('is_active', true)
            ->where(fn (Builder $query) => $query
                ->whereHas('permissions', fn (Builder $permission) => $permission->whereIn('name', $permissions))
                ->orWhereHas('roles', fn (Builder $role) => $role
                    ->where('name', config('baines.super_admin_role'))
                    ->orWhereHas('permissions', fn (Builder $permission) => $permission->whereIn('name', $permissions))))
            ->get();
    }

    /**
     * What the bell shows: the unread count and the latest alerts.
     *
     * @return array{unread: int, latest: list<array{id: string, kind: string, icon: string, title: string, body: string, url: string, read: bool, created_at: string|null}>}
     */
    public function summary(User $user): array
    {
        return [
            'unread' => $user->unreadNotifications()->count(),
            'latest' => array_values($user->notifications()->limit(self::LATEST)->get()
                ->map(fn (DatabaseNotification $alert) => [
                    'id' => (string) $alert->id,
                    'kind' => (string) $alert->type,
                    'icon' => AlertKind::tryFrom((string) $alert->type)?->icon() ?? 'bell',
                    'title' => (string) ($alert->data['title'] ?? ''),
                    'body' => (string) ($alert->data['body'] ?? ''),
                    'url' => (string) ($alert->data['url'] ?? ''),
                    'read' => $alert->read_at !== null,
                    'created_at' => $alert->created_at?->toIso8601String(),
                ])->all()),
        ];
    }

    /**
     * Mark one of the user's alerts read and return where it points. Another user's alert is
     * not found.
     */
    public function open(User $user, string $id): string
    {
        $alert = $user->notifications()->whereKey($id)->firstOrFail();
        $alert->markAsRead();

        return (string) ($alert->data['url'] ?? route('admin.dashboard'));
    }

    public function markAllRead(User $user): void
    {
        $user->unreadNotifications()->update(['read_at' => now()]);
    }

    /**
     * Read alerts older than $days are deleted (scheduled daily).
     */
    public function prune(int $days = 90): int
    {
        return DatabaseNotification::query()
            ->whereNotNull('read_at')
            ->where('created_at', '<', now()->subDays($days))
            ->delete();
    }
}
