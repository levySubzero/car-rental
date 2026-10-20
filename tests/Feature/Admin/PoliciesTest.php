<?php

use App\Enums\Permission;
use App\Exceptions\Domain\CmsException;
use App\Http\Resources\Public\PolicyResource;
use App\Models\Policy;
use App\Models\PolicyBundle;
use App\Models\PolicyVersion;
use App\Models\User;
use App\Services\Cms\PolicyBundleService;
use Inertia\Testing\AssertableInertia as Assert;

function editorWithPublish(): User
{
    return staffWith([Permission::PoliciesView, Permission::PoliciesCreate, Permission::PoliciesUpdate, Permission::PoliciesPublish, Permission::PoliciesDelete]);
}

it('lists policies with their status and the current bundle', function () {
    Policy::factory()->published()->create(['title' => 'Fuel policy']);
    Policy::factory()->withDraft()->create(['title' => 'Mileage']);
    app(PolicyBundleService::class)->snapshot();

    $this->actingAs(staffWith([Permission::PoliciesView]))
        ->get(route('admin.policies.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('admin/policies/index')
            ->has('policies', 2)
            ->where('policies.0.published.version', 1)
            ->where('policies.1.published', null)
            ->where('policies.1.draft.version', 1)
            ->where('bundle.version', 1)
            ->where('bundle.policies', 1));
});

it('creates a policy as an unpublished draft', function () {
    $this->actingAs(editorWithPublish())
        ->post(route('admin.policies.store'), [
            'title' => 'Fuel policy',
            'slug' => '',
            'group' => 'rental',
            'summary' => 'Full to full.',
            'show_on_home' => true,
            'body' => 'Return the car **full**.',
        ])
        ->assertSessionHasNoErrors()
        ->assertRedirect(route('admin.policies.edit', 'fuel-policy'));

    $policy = Policy::firstWhere('slug', 'fuel-policy')->load(['published', 'draft']);

    expect($policy->published)->toBeNull()
        ->and($policy->draft->version)->toBe(1)
        ->and(PolicyBundle::count())->toBe(0);
});

it('drafts, publishes a new version and snapshots a bundle', function () {
    $editor = editorWithPublish();
    $policy = Policy::factory()->published('Version one.')->create();
    $other = Policy::factory()->published('Other policy.')->create();

    $this->actingAs($editor)->put(route('admin.policies.draft.save', $policy), ['body' => 'Version two.'])->assertRedirect();
    $this->actingAs($editor)->put(route('admin.policies.draft.save', $policy), ['body' => 'Version two, edited.'])->assertRedirect();

    $policy->load(['published', 'draft']);
    expect($policy->published->body)->toBe('Version one.')
        ->and($policy->draft->version)->toBe(2)
        ->and($policy->draft->body)->toBe('Version two, edited.')
        ->and($policy->draft->created_by)->toBe($editor->id);

    $this->actingAs($editor)->post(route('admin.policies.publish', $policy))->assertRedirect();

    $policy->refresh()->load(['published', 'draft']);
    $bundle = PolicyBundle::sole();

    expect($policy->published->version)->toBe(2)
        ->and($policy->draft)->toBeNull()
        ->and($bundle->version)->toBe(1)
        ->and($bundle->versions->pluck('id')->sort()->values()->all())
        ->toBe(collect([$policy->published->id, $other->published()->first()->id])->sort()->values()->all());

    $this->assertDatabaseHas('activity_log', ['log_name' => 'policies', 'event' => 'published', 'subject_id' => $policy->id]);
});

it('discards a draft that matches the live text', function () {
    $policy = Policy::factory()->published("Same text.\n")->withDraft('Changed.')->create();

    $this->actingAs(editorWithPublish())->put(route('admin.policies.draft.save', $policy), ['body' => "Same text.\r\n"]);

    expect($policy->draft()->exists())->toBeFalse()
        ->and($policy->versions()->count())->toBe(1);
});

it('refuses to publish a draft with a [CONFIRM] note, or nothing', function () {
    $editor = editorWithPublish();
    $policy = Policy::factory()->withDraft('Minimum age 23 [CONFIRM: 24?]')->create();
    $clean = Policy::factory()->published()->create();

    $this->actingAs($editor)->post(route('admin.policies.publish', $policy))->assertRedirect();
    $this->actingAs($editor)->post(route('admin.policies.publish', $clean))->assertRedirect();

    expect($policy->published()->exists())->toBeFalse()
        ->and(PolicyBundle::count())->toBe(0);
});

it('never changes or deletes a published version', function () {
    $version = Policy::factory()->published('Accepted text.')->create()->versions()->sole();

    expect(fn () => $version->update(['body' => 'Rewritten.']))->toThrow(CmsException::class)
        ->and(fn () => $version->delete())->toThrow(CmsException::class)
        ->and($version->fresh()->body)->toBe('Accepted text.');
});

it('soft-deletes a live policy and publishes a bundle without it', function () {
    $editor = editorWithPublish();
    $policy = Policy::factory()->published()->create();
    $kept = Policy::factory()->published()->create();
    app(PolicyBundleService::class)->snapshot();

    $this->actingAs($editor)->delete(route('admin.policies.destroy', $policy))->assertRedirect(route('admin.policies.index'));

    $latest = app(PolicyBundleService::class)->current();

    expect($policy->fresh()->trashed())->toBeTrue()
        ->and($latest->version)->toBe(2)
        ->and($latest->versions->pluck('policy_id')->all())->toBe([$kept->id])
        // The first bundle still holds what renters accepted.
        ->and(PolicyBundle::firstWhere('version', 1)->versions()->count())->toBe(2)
        ->and(PolicyVersion::count())->toBe(2);
});

it('updates details without touching the text, toggles home and reorders', function () {
    $editor = editorWithPublish();
    [$a, $b] = Policy::factory()->published()->count(2)->create();

    $this->actingAs($editor)->put(route('admin.policies.update', $a), [
        'title' => 'Renamed', 'slug' => '', 'group' => 'cover', 'summary' => null, 'show_on_home' => false,
    ])->assertSessionHasNoErrors();
    $this->actingAs($editor)->put(route('admin.policies.update', $a), [
        'title' => 'Renamed', 'group' => 'cover', 'show_on_home' => false, 'body' => 'Sneaky',
    ])->assertSessionHasErrors('body');
    $this->actingAs($editor)->put(route('admin.policies.home', $b), ['value' => true])->assertRedirect();
    $this->actingAs($editor)->put(route('admin.policies.reorder'), ['ids' => [$b->id, $a->id]])->assertRedirect();

    expect($a->fresh()->title)->toBe('Renamed')
        ->and($a->versions()->count())->toBe(1)
        ->and($b->fresh()->show_on_home)->toBeTrue()
        ->and(Policy::query()->ordered()->pluck('id')->all())->toBe([$b->id, $a->id]);
});

it('shows only the live text publicly', function () {
    $policy = Policy::factory()->published('Live **text**.')->withDraft('Draft text.')->create()->load('published');

    expect(PolicyResource::make($policy)->resolve())
        ->toMatchArray(['version' => 1, 'body_html' => '<p>Live <strong>text</strong>.</p>']);
});

it('needs the matching permission for each policy action', function () {
    $policy = Policy::factory()->withDraft()->create();
    $editor = staffWith([Permission::PoliciesView, Permission::PoliciesUpdate]);
    $viewer = staffWith([Permission::PoliciesView]);

    $this->actingAs($viewer)->get(route('admin.policies.edit', $policy))->assertOk();
    $this->actingAs($viewer)->put(route('admin.policies.draft.save', $policy), ['body' => 'x'])->assertForbidden();
    $this->actingAs($viewer)->delete(route('admin.policies.draft.discard', $policy))->assertForbidden();
    $this->actingAs($viewer)->get(route('admin.policies.create'))->assertForbidden();
    $this->actingAs($editor)->post(route('admin.policies.publish', $policy))->assertForbidden();
    $this->actingAs($editor)->delete(route('admin.policies.destroy', $policy))->assertForbidden();
});
