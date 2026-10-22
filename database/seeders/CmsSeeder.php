<?php

namespace Database\Seeders;

use App\Enums\PolicyGroup;
use App\Enums\ServiceCtaType;
use App\Models\Brand;
use App\Models\CarType;
use App\Models\Page;
use App\Models\PaymentMethod;
use App\Models\Policy;
use App\Models\PolicyVersion;
use App\Models\Service;
use App\Services\Cms\PageService;
use App\Services\Cms\PolicyBundleService;
use App\Services\Cms\PolicyService;
use App\Settings\GeneralSettings;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use JsonException;
use RuntimeException;

/**
 * Loads the client's seed pack from database/seeders/data (M5.9–M5.10). Runs in every
 * environment and is safe to re-run: rows are matched by slug or code, missing rows are
 * inserted, and nothing an admin edited is overwritten. See data/README.md.
 */
class CmsSeeder extends Seeder
{
    /**
     * The WhatsApp templates the settings migration starts with. A template still equal to its
     * default is replaced by the client's wording; one an admin changed is left alone.
     */
    private const DEFAULT_TEMPLATES = [
        'car_enquiry' => 'Hello Baines Executives, I would like to rent the {car} for {dates}.',
        'general_enquiry' => 'Hello Baines Executives, I have an enquiry.',
    ];

    public function run(PolicyService $policies, PolicyBundleService $bundles, PageService $pages): void
    {
        DB::transaction(function () use ($policies, $bundles, $pages) {
            $this->settings($this->load('site_settings'));

            $catalog = $this->load('catalog');
            $this->brands($catalog['brands']);
            $this->carTypes($catalog['car_types']);
            $this->services($catalog['services']);

            $created = $pages->ensureAll();
            $this->report("Pages: {$created} created");
            $this->pageCopy($this->load('pages'), $pages);

            $this->policies($this->load('policies')['policies'], $policies, $bundles);
            $this->paymentMethods($this->load('pricing')['payment_methods']);
        });
    }

    /**
     * General settings are filled only where still empty (D26). TODO values stay empty.
     *
     * @param  array<string, mixed>  $data
     */
    private function settings(array $data): void
    {
        $settings = app(GeneralSettings::class);
        $contacts = $data['contacts'];
        $location = $data['location'];

        if ($settings->phones === []) {
            $settings->phones = [$contacts['phone_primary']['display']];
        }

        $settings->whatsapp_number ??= $contacts['whatsapp']['e164'];
        $settings->email ??= $contacts['email'];
        $settings->address ??= $location['display'];
        $settings->map_embed_url ??= 'https://www.google.com/maps?q='.rawurlencode($location['map_query']).'&output=embed';
        $settings->latitude ??= $location['latitude'];
        $settings->longitude ??= $location['longitude'];

        if ($settings->socials === []) {
            /** @var list<array{platform: string, url: string, sort_order: int}> $socials */
            $socials = $data['socials'];
            usort($socials, fn (array $a, array $b) => $a['sort_order'] <=> $b['sort_order']);
            $settings->socials = array_column($socials, 'url', 'platform');
        }

        $templates = [
            'car_enquiry' => str_replace('{car_name}', '{car}', $contacts['whatsapp']['car_message_template']),
            'general_enquiry' => $contacts['whatsapp']['default_message'],
        ];

        foreach ($templates as $key => $text) {
            if (($settings->whatsapp_templates[$key] ?? null) === self::DEFAULT_TEMPLATES[$key]) {
                $settings->whatsapp_templates = [...$settings->whatsapp_templates, $key => $text];
            }
        }

        $settings->save();
        $this->report('General settings: filled where empty');
    }

    /**
     * @param  list<array{name: string, slug: string, sort_order: int, show_in_marquee: bool}>  $rows
     */
    private function brands(array $rows): void
    {
        $existing = Brand::query()->pluck('slug')->all();
        $created = 0;

        foreach ($rows as $row) {
            if (! in_array($row['slug'], $existing, true)) {
                $brand = new Brand(['name' => $row['name'], 'slug' => $row['slug'], 'show_in_marquee' => $row['show_in_marquee']]);
                $brand->sort_order = $row['sort_order'];
                $brand->save();
                $created++;
            }
        }

        $this->report("Brands: {$created} created");
    }

    /**
     * @param  list<array{name: string, slug: string, description: string, sort_order: int}>  $rows
     */
    private function carTypes(array $rows): void
    {
        $existing = CarType::query()->pluck('slug')->all();
        $created = 0;

        foreach ($rows as $row) {
            if (! in_array($row['slug'], $existing, true)) {
                $type = new CarType(['name' => $row['name'], 'slug' => $row['slug'], 'description' => $row['description']]);
                $type->sort_order = $row['sort_order'];
                $type->save();
                $created++;
            }
        }

        $this->report("Car types: {$created} created");
    }

    /**
     * @param  list<array{title: string, slug: string, summary: string, cta_type: string, sort_order: int}>  $rows
     */
    private function services(array $rows): void
    {
        $existing = Service::query()->pluck('slug')->all();
        $created = 0;

        foreach ($rows as $row) {
            if (! in_array($row['slug'], $existing, true)) {
                $service = new Service([
                    'title' => $row['title'],
                    'slug' => $row['slug'],
                    'summary' => $row['summary'],
                    'cta_type' => ServiceCtaType::from($row['cta_type']),
                ]);
                $service->sort_order = $row['sort_order'];
                $service->save();
                $created++;
            }
        }

        $this->report("Services: {$created} created");
    }

    /**
     * Starting copy for the preset pages (M7). A field is filled only while it is still
     * completely empty, so nothing staff wrote is replaced.
     *
     * @param  array<string, mixed>  $data  page slug => field key => value
     */
    private function pageCopy(array $data, PageService $pages): void
    {
        $filled = 0;

        foreach (Page::query()->whereIn('slug', array_keys($data))->get() as $page) {
            $definition = $pages->definitionFor($page);
            $content = $definition->fill($page->content);

            foreach ($definition->fields as $field) {
                $value = $data[$page->slug][$field->key] ?? null;
                $current = $content[$field->key];
                $empty = is_array($current)
                    ? collect($current)->flatten()->filter(fn ($text) => filled($text))->isEmpty()
                    : blank($current);

                if ($value !== null && $empty) {
                    $content[$field->key] = $value;
                    $filled++;
                }
            }

            $page->update(['content' => $definition->fill($content)]);
        }

        $this->report("Page copy: {$filled} fields filled");
    }

    /**
     * A policy gets a new version only when the pack's text differs from the last version the
     * seeder wrote (created_by null), so staff edits are never replaced by an unchanged pack.
     * New versions are published unless they carry a [CONFIRM] note or staff have a draft open.
     * One bundle is published for the whole run (D24).
     *
     * @param  list<array{slug: string, title: string, group: string, sort_order: int, show_on_home: bool, summary: string|null, body: string}>  $rows
     */
    private function policies(array $rows, PolicyService $policies, PolicyBundleService $bundles): void
    {
        $created = $versioned = $published = 0;

        foreach ($rows as $row) {
            $policy = Policy::withTrashed()->where('slug', $row['slug'])->first();

            if ($policy?->trashed()) {
                continue; // Deleted in the admin: leave it deleted.
            }

            if ($policy === null) {
                $policy = new Policy([
                    'slug' => $row['slug'],
                    'title' => $row['title'],
                    'group' => PolicyGroup::from($row['group']),
                    'summary' => $row['summary'],
                    'show_on_home' => $row['show_on_home'],
                ]);
                $policy->sort_order = $row['sort_order'];
                $policy->save();
                $created++;
            }

            $lastSeeded = $policy->versions()->whereNull('created_by')->first();
            $draft = $policy->draft()->first();

            if ($lastSeeded !== null && $policies->sameText($lastSeeded->body, $row['body'])) {
                continue;
            }

            if ($draft !== null && $draft->created_by !== null) {
                $this->report("Policy {$row['slug']}: skipped, a staff member has a draft open", 'warn');

                continue;
            }

            if ($policy->published()->first()?->created_by !== null) {
                $this->report("Policy {$row['slug']}: skipped, the live text was edited in the admin", 'warn');

                continue;
            }

            $version = $draft ?? new PolicyVersion(['version' => $policies->nextVersion($policy)]);
            $version->body = $row['body'];
            $policy->versions()->save($version);
            $versioned++;

            if (! str_contains($row['body'], '[CONFIRM')) {
                $policies->publishVersion($version);
                $published++;
            }
        }

        if ($published > 0) {
            $bundle = $bundles->snapshot();
            $this->report("Policies: {$created} created, {$versioned} new versions, {$published} published as bundle {$bundle->version}");
        } else {
            $this->report("Policies: {$created} created, {$versioned} new versions, none published");
        }
    }

    /**
     * @param  list<array{code: string, name: string, sort_order: int, is_active: bool, requires_transaction_message: bool, instructions: string}>  $rows
     */
    private function paymentMethods(array $rows): void
    {
        $existing = PaymentMethod::query()->pluck('code')->map(fn ($code) => $code->value)->all();
        $created = 0;

        foreach ($rows as $row) {
            if (! in_array($row['code'], $existing, true)) {
                $method = new PaymentMethod([
                    'code' => $row['code'],
                    'name' => $row['name'],
                    'instructions' => $row['instructions'],
                    'requires_transaction_message' => $row['requires_transaction_message'],
                    'is_active' => $row['is_active'],
                ]);
                $method->sort_order = $row['sort_order'];
                $method->save();
                $created++;
            }
        }

        $this->report("Payment methods: {$created} created");
    }

    /**
     * @return array<string, mixed>
     */
    private function load(string $name): array
    {
        $path = database_path("seeders/data/{$name}.json");

        try {
            $data = json_decode((string) file_get_contents($path), true, flags: JSON_THROW_ON_ERROR);
        } catch (JsonException $exception) {
            throw new RuntimeException("Seed pack file {$name}.json is not valid JSON: {$exception->getMessage()}", previous: $exception);
        }

        return (array) $data;
    }

    private function report(string $message, string $level = 'info'): void
    {
        $this->command->{$level}("  CMS seed pack: {$message}");
    }
}
