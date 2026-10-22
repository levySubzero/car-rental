<?php

use App\Enums\Permission;
use App\Http\Resources\Public\PageResource;
use App\Models\Page;
use App\Services\Cms\PageService;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Support\CatalogFixtures;

beforeEach(fn () => CatalogFixtures::setUp());

function aboutPayload(array $content = [], array $overrides = []): array
{
    return array_replace([
        'content' => array_replace([
            'heading' => 'About Baines',
            'intro' => 'Executive car hire in Nairobi.',
            'body' => 'Founded in **2015**.',
            'image' => null,
        ], $content),
        'meta_title' => 'About us',
        'meta_description' => null,
    ], $overrides);
}

it('lists every preset page and creates missing rows', function () {
    $this->actingAs(staffWith([Permission::PagesView]))
        ->get(route('admin.pages.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('admin/pages/index')
            ->has('pages', 7)
            ->where('pages.0.slug', 'home')
            ->where('pages.1.missing', ['Heading', 'Introduction', 'Story']));

    expect(Page::count())->toBe(7);
});

it('shows a page with its field definitions', function () {
    app(PageService::class)->ensureAll();

    $this->actingAs(staffWith([Permission::PagesView]))
        ->get(route('admin.pages.edit', 'home'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('admin/pages/edit')
            ->where('page.fields.0.key', 'about_heading')
            ->where('page.fields.3.type', 'list')
            ->has('page.content.requirements', 3)
            ->has('imageLimits'));
});

it('404s for a page that is not preset', function () {
    $this->actingAs(superAdmin())->get('/admin/pages/terms/edit')->assertNotFound();
});

it('saves content and an image, dropping unknown keys', function () {
    app(PageService::class)->ensureAll();
    $editor = staffWith([Permission::PagesUpdate, Permission::MediaUpload]);
    $image = CatalogFixtures::photo($editor, 'page.image');

    $this->actingAs($editor)
        ->put(route('admin.pages.update', 'about'), aboutPayload(['image' => $image->uuid, 'sneaky' => 'x']))
        ->assertSessionHasNoErrors()
        ->assertRedirect(route('admin.pages.edit', 'about'));

    $page = Page::firstWhere('slug', 'about');

    expect($page->content->getArrayCopy())->toBe([
        'heading' => 'About Baines',
        'intro' => 'Executive car hire in Nairobi.',
        'header_image' => null,
        'body' => 'Founded in **2015**.',
        'image' => $image->uuid,
    ])
        ->and($page->updated_by)->toBe($editor->id)
        ->and($page->mediaIn('page.image')->pluck('uuid')->all())->toBe([$image->uuid])
        ->and(app(PageService::class)->missing($page))->toBe([]);

    // Removing the image releases the file.
    $this->actingAs($editor)->put(route('admin.pages.update', 'about'), aboutPayload())->assertSessionHasNoErrors();

    expect($image->fresh()->trashed())->toBeTrue();
});

it('validates content against the page definition', function () {
    app(PageService::class)->ensureAll();
    $editor = staffWith([Permission::PagesUpdate]);

    $this->actingAs($editor)
        ->put(route('admin.pages.update', 'about'), aboutPayload(['heading' => str_repeat('x', 121)]))
        ->assertSessionHasErrors('content.heading');

    $this->actingAs($editor)
        ->put(route('admin.pages.update', 'home'), [
            'content' => ['requirements' => [['title' => 'Only one', 'body' => 'x']], 'why_choose_us' => []],
        ])
        ->assertSessionHasErrors(['content.requirements', 'content.why_choose_us']);
});

it('renders public content: Markdown as HTML, images as URLs', function () {
    app(PageService::class)->ensureAll();
    $editor = staffWith([Permission::PagesUpdate, Permission::MediaUpload]);
    $image = CatalogFixtures::photo($editor, 'page.image');
    app(PageService::class)->update(Page::firstWhere('slug', 'about'), aboutPayload(['image' => $image->uuid])['content'], [], $editor);

    $data = PageResource::make(Page::firstWhere('slug', 'about')->load('media'))->resolve();

    expect($data['content']['heading'])->toBe('About Baines')
        ->and($data['content']['body_html'])->toBe('<p>Founded in <strong>2015</strong>.</p>')
        ->and($data['content'])->not->toHaveKey('body')
        ->and($data['content']['image']['urls'])->toHaveKey('card');
});

it('needs the matching permission for each page action', function () {
    app(PageService::class)->ensureAll();
    $viewer = staffWith([Permission::PagesView]);

    $this->actingAs($viewer)->get(route('admin.pages.edit', 'about'))->assertOk();
    $this->actingAs($viewer)->put(route('admin.pages.update', 'about'), aboutPayload())->assertForbidden();
    $this->actingAs(staffWith())->get(route('admin.pages.index'))->assertForbidden();
});
