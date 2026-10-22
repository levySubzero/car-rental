<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Http\Resources\Public\ServiceResource;
use App\Models\Service;
use App\Services\Cms\PageContent;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The Services page (M7.12): every active service, each with an anchor (#slug) the home
 * page cards link to, and its own call to action (book, WhatsApp enquiry or a link).
 */
class ServicesController extends Controller
{
    public function __invoke(PageContent $pages): Response
    {
        $page = $pages->for('services');

        return Inertia::render('public/services', [
            'heading' => PageContent::text($page, 'heading', 'Our services'),
            'intro' => PageContent::text($page, 'intro'),
            'header_image' => $page['header_image'] ?? null,
            'services' => ServiceResource::collection(Service::query()->active()->ordered()->with('image')->get()),
        ]);
    }
}
