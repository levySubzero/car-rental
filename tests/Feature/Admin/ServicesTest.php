<?php

use App\Enums\Permission;
use App\Enums\ServiceCtaType;
use App\Http\Resources\Public\ServiceResource;
use App\Models\Service;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Support\CatalogFixtures;

beforeEach(fn () => CatalogFixtures::setUp());

function servicePayload(array $overrides = []): array
{
    return array_replace([
        'title' => 'Airport transfers',
        'slug' => '',
        'summary' => 'Meet and greet at JKIA.',
        'body' => 'We **meet you** at arrivals.',
        'cta_type' => 'whatsapp',
        'cta_value' => '',
        'is_active' => true,
        'image' => null,
    ], $overrides);
}

it('lists services in display order', function () {
    Service::factory()->create(['title' => 'Safaris']);
    Service::factory()->create(['title' => 'Chauffeur']);

    $this->actingAs(staffWith([Permission::ServicesView]))
        ->get(route('admin.services.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('admin/services/index')
            ->has('services', 2)
            ->where('services.0.title', 'Safaris'));
});

it('creates a service with an image and logs it', function () {
    $editor = staffWith([Permission::ServicesCreate, Permission::ServicesUpdate, Permission::MediaUpload]);
    $image = CatalogFixtures::photo($editor, 'service.image');

    $this->actingAs($editor)
        ->post(route('admin.services.store'), servicePayload(['image' => $image->uuid]))
        ->assertSessionHasNoErrors()
        ->assertRedirect(route('admin.services.edit', 'airport-transfers'));

    $service = Service::firstWhere('slug', 'airport-transfers');

    expect($service->cta_type)->toBe(ServiceCtaType::WhatsApp)
        ->and($service->cta_value)->toBeNull()
        ->and($service->image->uuid)->toBe($image->uuid);

    $this->assertDatabaseHas('activity_log', ['log_name' => 'services', 'subject_id' => $service->id, 'event' => 'created']);
});

it('needs a URL for a link button and drops the value for a book button', function () {
    $editor = staffWith([Permission::ServicesCreate]);

    $this->actingAs($editor)
        ->post(route('admin.services.store'), servicePayload(['cta_type' => 'link', 'cta_value' => '']))
        ->assertSessionHasErrors('cta_value');

    $this->actingAs($editor)
        ->post(route('admin.services.store'), servicePayload(['cta_type' => 'link', 'cta_value' => 'not a url']))
        ->assertSessionHasErrors('cta_value');

    $this->actingAs($editor)
        ->post(route('admin.services.store'), servicePayload(['cta_type' => 'book', 'cta_value' => 'leftover']))
        ->assertSessionHasNoErrors();

    expect(Service::sole()->cta_value)->toBeNull();
});

it('validates service input', function () {
    Service::factory()->create(['title' => 'Safaris', 'slug' => 'safaris']);

    $this->actingAs(staffWith([Permission::ServicesCreate]))
        ->post(route('admin.services.store'), servicePayload([
            'title' => '',
            'slug' => 'safaris',
            'summary' => str_repeat('x', 301),
            'cta_type' => 'phone',
        ]))
        ->assertSessionHasErrors(['title', 'slug', 'summary', 'cta_type']);
});

it('reorders, toggles and deletes services', function () {
    [$a, $b] = Service::factory()->count(2)->create();
    $editor = staffWith([Permission::ServicesUpdate, Permission::ServicesDelete]);

    $this->actingAs($editor)->put(route('admin.services.reorder'), ['ids' => [$b->id, $a->id]])->assertRedirect();
    $this->actingAs($editor)->put(route('admin.services.active', $a), ['value' => false])->assertRedirect();

    expect(Service::query()->ordered()->pluck('id')->all())->toBe([$b->id, $a->id])
        ->and(Service::query()->active()->pluck('id')->all())->toBe([$b->id]);

    $this->actingAs($editor)->delete(route('admin.services.destroy', $a))->assertRedirect(route('admin.services.index'));

    expect(Service::find($a->id))->toBeNull();
});

it('renders the body as safe HTML for the public site', function () {
    $service = Service::factory()->create(['body' => 'Hi <script>x</script> **there**']);

    $data = ServiceResource::make($service)->resolve();

    expect($data['body_html'])->toContain('<strong>there</strong>')->not->toContain('<script')
        ->and($data)->not->toHaveKey('body');
});

it('needs the matching permission for each service action', function () {
    $service = Service::factory()->create();
    $viewer = staffWith([Permission::ServicesView]);

    $this->actingAs($viewer)->get(route('admin.services.edit', $service))->assertOk();
    $this->actingAs($viewer)->get(route('admin.services.create'))->assertForbidden();
    $this->actingAs($viewer)->put(route('admin.services.update', $service), servicePayload())->assertForbidden();
    $this->actingAs($viewer)->put(route('admin.services.reorder'), ['ids' => [$service->id]])->assertForbidden();
    $this->actingAs($viewer)->put(route('admin.services.active', $service), ['value' => false])->assertForbidden();
    $this->actingAs($viewer)->delete(route('admin.services.destroy', $service))->assertForbidden();
});
