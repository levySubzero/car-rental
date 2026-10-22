<?php

namespace App\Http\Controllers\Public;

use App\Enums\ContactMessageStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Public\Contact\StoreContactMessageRequest;
use App\Models\Car;
use App\Models\ContactMessage;
use App\Notifications\NewContactMessage;
use App\Services\Cms\PageContent;
use App\Settings\NotificationSettings;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Notification;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The Contact page and form (M7.15). Messages land in Admin → Contact messages, and the
 * staff emails in Notification settings get a copy. "Book this car" links here with the
 * car chosen (?car=slug) until online booking arrives (M8).
 */
class ContactController extends Controller
{
    public function show(Request $request, PageContent $pages): Response
    {
        $page = $pages->for('contact');
        $cars = Car::query()->published()->orderBy('name')->get(['name', 'slug']);
        $selected = $request->string('car')->toString();

        return Inertia::render('public/contact', [
            'heading' => PageContent::text($page, 'heading', 'Contact us'),
            'intro' => PageContent::text($page, 'intro'),
            'header_image' => $page['header_image'] ?? null,
            'cars' => $cars,
            'selected_car' => $cars->contains('slug', $selected) ? $selected : null,
            'sent' => (bool) $request->session()->get('contact_sent'),
            'turnstile_site_key' => config('services.turnstile.site_key'),
        ]);
    }

    public function store(StoreContactMessageRequest $request, NotificationSettings $notifications): RedirectResponse
    {
        $message = ContactMessage::create([
            ...$request->attributesForMessage(),
            'status' => ContactMessageStatus::New,
        ]);

        if ($notifications->staff_emails !== []) {
            Notification::route('mail', $notifications->staff_emails)->notify(new NewContactMessage($message));
        }

        return to_route('contact')->with('contact_sent', true);
    }
}
