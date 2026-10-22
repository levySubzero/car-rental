<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Models\Brand;
use App\Models\Car;
use App\Models\CarType;
use Illuminate\Http\Response;

/**
 * sitemap.xml (M7.17): the public pages, every published car, and the brand and car type
 * pages that have published cars (TECHNICAL_PLAN §14).
 */
class SitemapController extends Controller
{
    public function __invoke(): Response
    {
        $urls = collect([
            ['loc' => route('home'), 'priority' => '1.0'],
            ['loc' => route('fleet'), 'priority' => '0.9'],
            ['loc' => route('services'), 'priority' => '0.8'],
            ['loc' => route('about'), 'priority' => '0.6'],
            ['loc' => route('rental-policy'), 'priority' => '0.6'],
            ['loc' => route('contact'), 'priority' => '0.6'],
            ['loc' => route('privacy'), 'priority' => '0.3'],
        ])
            ->concat(Car::query()->published()->orderBy('name')->get(['slug', 'updated_at'])->map(fn (Car $car) => [
                'loc' => route('cars.show', $car),
                'lastmod' => $car->updated_at?->toDateString(),
                'priority' => '0.8',
            ]))
            ->concat(Brand::query()->active()->hasPublishedCars()->orderBy('name')->get(['slug'])->map(fn (Brand $brand) => [
                'loc' => route('car-brands.show', $brand),
                'priority' => '0.7',
            ]))
            ->concat(CarType::query()->active()->hasPublishedCars()->ordered()->get(['slug'])->map(fn (CarType $type) => [
                'loc' => route('car-types.show', $type),
                'priority' => '0.7',
            ]));

        return response()
            ->view('sitemap', ['urls' => $urls])
            ->header('Content-Type', 'application/xml; charset=UTF-8');
    }
}
