<?php

use App\Enums\Permission;
use App\Models\MailLog;
use App\Notifications\IntegrationTestMail;
use App\Services\Mail\MailLogService;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    config(['mail.default' => 'array']);
});

it('keeps a copy of every email sent, notifications included', function () {
    Notification::route('mail', 'renter@example.test')->notify(new IntegrationTestMail);
    Mail::raw('Plain hello', fn ($message) => $message->to('other@example.test')->subject('Raw mail'));

    $logs = MailLog::query()->orderBy('id')->get();

    expect($logs)->toHaveCount(2)
        ->and($logs[0]->to)->toContain('renter@example.test')
        ->and($logs[0]->notification)->toBe(IntegrationTestMail::class)
        ->and($logs[0]->mailer)->toBe('array')
        ->and($logs[0]->html)->not->toBeEmpty()
        ->and($logs[1]->subject)->toBe('Raw mail')
        ->and($logs[1]->text)->toBe('Plain hello');
});

it('stores attachment names and the body encrypted', function () {
    Mail::raw('With file', fn ($message) => $message->to('a@example.test')->subject('File')->attachData('%PDF-1.4', 'Voucher BE-1.pdf', ['mime' => 'application/pdf']));

    $log = MailLog::query()->sole();
    $raw = DB::table('mail_logs')->value('text');

    expect($log->attachments)->toBe([['name' => 'Voucher BE-1.pdf', 'mime' => 'application/pdf', 'size' => 8]])
        ->and($raw)->not->toContain('With file');
});

it('is for super admins only', function () {
    Mail::raw('x', fn ($message) => $message->to('a@example.test')->subject('Hidden'));
    $log = MailLog::query()->sole();

    $this->actingAs(staffWith([Permission::BookingsView, Permission::ActivityView]))
        ->get(route('admin.mail-log.index'))->assertForbidden();
    $this->actingAs(staffWith([Permission::BookingsView]))
        ->get(route('admin.mail-log.show', $log))->assertForbidden();

    $this->actingAs(superAdmin())
        ->get(route('admin.mail-log.index', ['search' => 'Hidden']))
        ->assertInertia(fn (Assert $page) => $page->component('admin/mail-log/index')->has('emails.data', 1)->missing('emails.data.0.html'));

    $this->actingAs(superAdmin())
        ->get(route('admin.mail-log.show', $log))
        ->assertInertia(fn (Assert $page) => $page->component('admin/mail-log/show')->where('email.text', 'x'));
});

it('prunes entries older than the retention setting', function () {
    MailLog::query()->create(['to' => 'old@example.test', 'created_at' => now()->subDays(31)]);
    MailLog::query()->create(['to' => 'new@example.test', 'created_at' => now()->subDays(29)]);

    expect(app(MailLogService::class)->prune())->toBe(1)
        ->and(MailLog::query()->pluck('to')->all())->toBe(['new@example.test']);
});
