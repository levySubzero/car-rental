<?php

use App\Models\Concerns\HasSlug;
use App\Models\Concerns\Sortable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class ConcernsTestItem extends Model
{
    use HasSlug, Sortable;

    protected $table = 'concerns_test_items';

    public $timestamps = false;

    protected $guarded = [];
}

beforeEach(function () {
    Schema::create('concerns_test_items', function (Blueprint $table) {
        $table->id();
        $table->string('name');
        $table->string('slug')->unique();
        $table->unsignedInteger('sort_order');
    });
});

it('generates a unique slug from the name', function () {
    $first = ConcernsTestItem::create(['name' => 'Toyota Land Cruiser V8']);
    $second = ConcernsTestItem::create(['name' => 'Toyota Land Cruiser V8']);

    expect($first->slug)->toBe('toyota-land-cruiser-v8')
        ->and($second->slug)->toBe('toyota-land-cruiser-v8-2');
});

it('keeps the slug when the name changes', function () {
    $item = ConcernsTestItem::create(['name' => 'Range Rover']);
    $item->update(['name' => 'Range Rover Sport']);

    expect($item->fresh()->slug)->toBe('range-rover');
});

it('normalises and de-duplicates an explicit slug', function () {
    ConcernsTestItem::create(['name' => 'A', 'slug' => 'taken']);
    $item = ConcernsTestItem::create(['name' => 'B']);

    $item->update(['slug' => 'Taken']);

    expect($item->fresh()->slug)->toBe('taken-2');
});

it('routes by slug', function () {
    expect((new ConcernsTestItem)->getRouteKeyName())->toBe('slug');
});

it('appends new rows to the end of the order', function () {
    $a = ConcernsTestItem::create(['name' => 'A']);
    $b = ConcernsTestItem::create(['name' => 'B']);

    expect([$a->sort_order, $b->sort_order])->toBe([1, 2]);
});

it('reorders by id list and keeps unlisted rows after', function () {
    [$a, $b, $c, $d] = collect(['A', 'B', 'C', 'D'])->map(fn ($name) => ConcernsTestItem::create(['name' => $name]))->all();

    ConcernsTestItem::reorder([$c->id, $a->id]);

    expect(ConcernsTestItem::query()->ordered()->pluck('name')->all())->toBe(['C', 'A', 'B', 'D']);
});
