<?php

namespace App\Http\Controllers\Admin;

use App\Enums\ContactMessageStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\ContactMessage\IndexContactMessageRequest;
use App\Http\Requests\Admin\ContactMessage\UpdateContactMessageStatusRequest;
use App\Http\Resources\Admin\ContactMessageResource;
use App\Models\ContactMessage;
use App\Services\Cms\ContactMessageService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class ContactMessageController extends Controller
{
    public function __construct(private readonly ContactMessageService $messages) {}

    public function index(IndexContactMessageRequest $request): Response
    {
        return Inertia::render('admin/contact-messages/index', [
            'messages' => ContactMessageResource::collection($this->messages->paginate($request->validated())),
            'filters' => $request->validated(),
            'counts' => $this->messages->counts(),
            'statusOptions' => $this->statusOptions(),
        ]);
    }

    public function show(ContactMessage $contactMessage): Response
    {
        Gate::authorize('view', $contactMessage);

        return Inertia::render('admin/contact-messages/show', [
            'message' => ContactMessageResource::make($contactMessage->load(['car', 'handler'])),
            'statusOptions' => $this->statusOptions(),
        ]);
    }

    public function update(UpdateContactMessageStatusRequest $request, ContactMessage $contactMessage): RedirectResponse
    {
        $status = ContactMessageStatus::from((string) $request->validated('status'));
        $this->messages->setStatus($contactMessage, $status, $request->user());

        Inertia::flash('toast', ['type' => 'success', 'message' => "Marked as {$status->label()}."]);

        return back();
    }

    public function destroy(ContactMessage $contactMessage): RedirectResponse
    {
        Gate::authorize('delete', $contactMessage);

        $this->messages->delete($contactMessage);

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Message deleted.']);

        return to_route('admin.contact-messages.index');
    }

    /**
     * @return list<array{value: string, label: string}>
     */
    private function statusOptions(): array
    {
        return array_map(fn (ContactMessageStatus $status) => ['value' => $status->value, 'label' => $status->label()], ContactMessageStatus::cases());
    }
}
