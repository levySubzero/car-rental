<?php

namespace App\Services\Mail;

use App\Models\MailLog;
use App\Settings\RetentionSettings;
use Carbon\CarbonImmutable;
use Illuminate\Pagination\LengthAwarePaginator;
use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\Email;
use Symfony\Component\Mime\Part\DataPart;

/**
 * The Email log: a copy of every message the app sends, for super admins to check what
 * went out (or would have, under the `log` mailer).
 */
class MailLogService
{
    public function __construct(private readonly RetentionSettings $retention) {}

    /**
     * @param  array<string, mixed>  $data  the mail event's data: the mailer name and, for notifications, its class
     */
    public function record(Email $email, array $data = []): MailLog
    {
        return MailLog::query()->create([
            'mailer' => is_string($data['mailer'] ?? null) ? $data['mailer'] : null,
            'to' => mb_substr($this->addresses($email->getTo()), 0, 1000),
            'cc' => mb_substr($this->addresses($email->getCc()), 0, 1000) ?: null,
            'subject' => mb_substr((string) $email->getSubject(), 0, 500) ?: null,
            'notification' => is_string($data['__laravel_notification'] ?? null) ? $data['__laravel_notification'] : null,
            'html' => $this->body($email->getHtmlBody()),
            'text' => $this->body($email->getTextBody()),
            'attachments' => array_map(fn (DataPart $part) => [
                'name' => (string) ($part->getFilename() ?? 'attachment'),
                'mime' => $part->getMediaType().'/'.$part->getMediaSubtype(),
                'size' => strlen($part->getBody()),
            ], $email->getAttachments()) ?: null,
            'created_at' => CarbonImmutable::now(),
        ]);
    }

    /**
     * @param  array{search?: string|null, notification?: string|null, from?: string|null, to?: string|null}  $filters
     * @return LengthAwarePaginator<int, MailLog>
     */
    public function paginate(array $filters): LengthAwarePaginator
    {
        return MailLog::query()
            ->select(['id', 'mailer', 'to', 'cc', 'subject', 'notification', 'attachments', 'created_at'])
            ->when($filters['search'] ?? null, fn ($query, string $search) => $query->where(fn ($query) => $query
                ->where('to', 'like', "%{$search}%")
                ->orWhere('subject', 'like', "%{$search}%")))
            ->when($filters['notification'] ?? null, fn ($query, string $type) => $query->where('notification', $type))
            ->when($filters['from'] ?? null, fn ($query, string $from) => $query->where('created_at', '>=', CarbonImmutable::parse($from)->startOfDay()))
            ->when($filters['to'] ?? null, fn ($query, string $to) => $query->where('created_at', '<=', CarbonImmutable::parse($to)->endOfDay()))
            ->latest('id')
            ->paginate(25)
            ->withQueryString();
    }

    /**
     * @return list<string>
     */
    public function notificationTypes(): array
    {
        return array_values(MailLog::query()->whereNotNull('notification')->distinct()->orderBy('notification')->pluck('notification')
            ->map(fn (mixed $class) => (string) $class)->all());
    }

    public function prune(): int
    {
        return MailLog::query()
            ->where('created_at', '<', CarbonImmutable::now()->subDays($this->retention->mail_log_retention_days))
            ->delete();
    }

    /**
     * @param  array<Address>  $addresses
     */
    private function addresses(array $addresses): string
    {
        return implode(', ', array_map(fn (Address $address) => $address->toString(), $addresses));
    }

    /**
     * @param  resource|string|null  $body
     */
    private function body(mixed $body): ?string
    {
        if (is_resource($body)) {
            $body = stream_get_contents($body);
        }

        return is_string($body) && $body !== '' ? $body : null;
    }
}
