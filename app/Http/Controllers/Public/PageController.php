<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Services\Cms\PageContent;
use App\Settings\HomeSettings;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The About and Privacy pages (M7.13), rendered from their preset pages (D22).
 */
class PageController extends Controller
{
    public function about(HomeSettings $home, PageContent $pages): Response
    {
        $about = $pages->for('about');
        $homePage = $pages->for('home');

        return Inertia::render('public/about', [
            'heading' => PageContent::text($about, 'heading', 'About Baines Executives'),
            'intro' => PageContent::text($about, 'intro'),
            'header_image' => $about['header_image'] ?? null,
            // Until the About page has its own story, the home page's About copy stands in.
            'body_html' => filled($about['body_html'] ?? null) ? $about['body_html'] : ($homePage['about_body_html'] ?? ''),
            'image' => $about['image'] ?? null,
            'reasons' => array_values(array_filter(
                $homePage['why_choose_us'] ?? [],
                fn (array $item) => filled($item['title'] ?? null),
            )),
            'trust_figures' => $home->trust_figures,
        ]);
    }

    public function privacy(PageContent $pages): Response
    {
        $privacy = $pages->for('privacy');

        return Inertia::render('public/privacy', [
            'heading' => PageContent::text($privacy, 'heading', 'Privacy notice'),
            'body_html' => $privacy['body_html'] ?? '',
        ]);
    }
}
