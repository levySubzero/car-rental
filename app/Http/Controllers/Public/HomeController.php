<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Http\Resources\Media\MediaResource;
use App\Http\Resources\Public\BrandResource;
use App\Http\Resources\Public\CarCardResource;
use App\Http\Resources\Public\CarTypeResource;
use App\Http\Resources\Public\PageResource;
use App\Http\Resources\Public\PolicyResource;
use App\Http\Resources\Public\ServiceResource;
use App\Models\Brand;
use App\Models\Car;
use App\Models\CarType;
use App\Models\Media;
use App\Models\Page;
use App\Models\Policy;
use App\Models\Service;
use App\Settings\HomeSettings;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Collection;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The home page (M7.5–M7.7). Section order, visibility, hero and trust figures come from
 * HomeSettings; section copy from the `home` preset page. Hidden sections load no data.
 */
class HomeController extends Controller
{
    // Cars per car type row, as on the reference site.
    private const ROW_SIZE = 4;

    private const CARD_RELATIONS = ['brand.logo', 'carType', 'cover'];

    public function __invoke(HomeSettings $home): Response
    {
        $sections = array_column(
            array_filter($home->sections, fn (array $section) => $section['visible']),
            'key',
        );
        $shows = fn (string $key) => in_array($key, $sections, true);

        $page = Page::query()->with('media')->firstWhere('slug', 'home');

        return Inertia::render('public/home', [
            'hero' => [
                'media' => $this->hero($home->hero_media_uuid),
                'video' => $this->hero($home->hero_video_uuid),
                'headline' => $home->headline,
                'subline' => $home->subline,
                'primary_cta_label' => $home->primary_cta_label,
                'secondary_cta_label' => $home->secondary_cta_label,
            ],
            'sections' => $sections,
            'marquee_position' => $home->marquee_position->value,
            'trust_figures' => $home->trust_figures,
            'content' => $page ? PageResource::make($page)->resolve()['content'] : null,
            'featured' => $shows('featured_fleet') ? CarCardResource::collection($this->featured($home->featured_car_count)) : [],
            'car_type_rows' => $shows('car_types') ? $this->carTypeRows() : [],
            'services' => $shows('services')
                ? ServiceResource::collection(Service::query()->active()->ordered()->with('image')->get())
                : [],
            'policies' => $shows('policy_highlights')
                ? PolicyResource::collection(Policy::query()->where('show_on_home', true)->whereHas('published')->with('published')->ordered()->get())
                : [],
            'brands' => BrandResource::collection(Brand::query()->inMarquee()->with('logo')->get()),
        ]);
    }

    private function hero(?string $uuid): ?MediaResource
    {
        $media = $uuid === null ? null : Media::query()->where('uuid', $uuid)->first();

        return $media ? MediaResource::make($media) : null;
    }

    /**
     * Featured cars first; when too few are featured, other published cars fill the row.
     *
     * @return Collection<int, Car>
     */
    private function featured(int $count): Collection
    {
        return Car::query()
            ->published()
            ->with(self::CARD_RELATIONS)
            ->orderByDesc('is_featured')
            ->ordered()
            ->limit($count)
            ->get();
    }

    /**
     * One row per active car type with published cars, in car type order.
     *
     * @return list<array{type: CarTypeResource, cars: AnonymousResourceCollection}>
     */
    private function carTypeRows(): array
    {
        return array_values(CarType::query()
            ->active()
            ->ordered()
            ->hasPublishedCars()
            ->with(['cars' => fn (HasMany $query) => $query->published()->with(self::CARD_RELATIONS)->ordered()->limit(self::ROW_SIZE)])
            ->get()
            ->map(fn (CarType $type) => [
                'type' => CarTypeResource::make($type),
                'cars' => CarCardResource::collection($type->cars),
            ])
            ->all());
    }
}
