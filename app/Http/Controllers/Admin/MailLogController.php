<?php

namespace App\Http\Controllers\Admin;

use App\Enums\Permission;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\MailLog\IndexMailLogRequest;
use App\Models\MailLog;
use App\Services\Mail\MailLogService;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

/**
 * System → Email log: every email the app sent, as the recipient saw it.
 */
class MailLogController extends Controller
{
    public function index(IndexMailLogRequest $request, MailLogService $log): Response
    {
        return Inertia::render('admin/mail-log/index', [
            'emails' => $log->paginate($request->validated())->through(fn (MailLog $mail) => [
                'id' => $mail->id,
                'to' => $mail->to,
                'subject' => $mail->subject,
                'type' => $mail->notification === null ? null : class_basename($mail->notification),
                'mailer' => $mail->mailer,
                'attachments' => count($mail->attachments ?? []),
                'created_at' => $mail->created_at->toIso8601String(),
            ]),
            'filters' => $request->validated(),
            'types' => array_map(fn (string $class) => ['value' => $class, 'label' => class_basename($class)], $log->notificationTypes()),
        ]);
    }

    public function show(MailLog $mailLog): Response
    {
        Gate::authorize(Permission::MailLogView->value);

        return Inertia::render('admin/mail-log/show', [
            'email' => [
                'id' => $mailLog->id,
                'to' => $mailLog->to,
                'cc' => $mailLog->cc,
                'subject' => $mailLog->subject,
                'type' => $mailLog->notification === null ? null : class_basename($mailLog->notification),
                'mailer' => $mailLog->mailer,
                'html' => $mailLog->html,
                'text' => $mailLog->text,
                'attachments' => $mailLog->attachments ?? [],
                'created_at' => $mailLog->created_at->toIso8601String(),
            ],
        ]);
    }
}
