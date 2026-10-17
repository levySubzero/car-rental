<?php

namespace App\Models\Concerns;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

/**
 * Manual ordering through a `sort_order` integer column.
 *
 * New rows go to the end of the list. Admin drag-and-drop calls
 * Model::reorder($ids) with the ids in their new order.
 *
 * @mixin Model
 */
trait Sortable
{
    public static function bootSortable(): void
    {
        static::creating(function (Model $model): void {
            /** @var Model&self $model */
            $column = $model->sortColumn();

            if ($model->getAttribute($column) === null) {
                $model->setAttribute($column, (int) static::query()->max($column) + 1);
            }
        });
    }

    public function sortColumn(): string
    {
        return 'sort_order';
    }

    /**
     * @param  Builder<static>  $query
     * @return Builder<static>
     */
    public function scopeOrdered(Builder $query): Builder
    {
        return $query->orderBy($this->sortColumn())->orderBy($this->getKeyName());
    }

    /**
     * Persist a new order. Ids missing from the list keep their position after the listed ones.
     *
     * @param  list<int|string>  $ids
     */
    public static function reorder(array $ids): void
    {
        $instance = new static;
        $column = $instance->sortColumn();

        DB::transaction(function () use ($ids, $instance, $column): void {
            foreach (array_values($ids) as $position => $id) {
                static::query()->whereKey($id)->update([$column => $position + 1]);
            }

            $offset = count($ids);

            static::query()
                ->whereKeyNot($ids)
                ->orderBy($column)
                ->orderBy($instance->getKeyName())
                ->pluck($instance->getKeyName())
                ->each(function ($id, int $index) use ($column, $offset): void {
                    static::query()->whereKey($id)->update([$column => $offset + $index + 1]);
                });
        });
    }
}
