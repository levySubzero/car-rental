<?php

namespace App\Models\Concerns;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Illuminate\Support\Str;

/**
 * Generates a unique `slug` from a source attribute when a model is created.
 *
 * The slug is never regenerated on update, so public URLs stay stable. To
 * change it, set `slug` explicitly; it is then re-normalised and de-duplicated.
 *
 * Models may override slugSource() (default "name") and slugColumn() (default "slug").
 *
 * @mixin Model
 */
trait HasSlug
{
    public static function bootHasSlug(): void
    {
        static::saving(function (Model $model): void {
            /** @var Model&self $model */
            $column = $model->slugColumn();
            $current = $model->getAttribute($column);

            if (blank($current)) {
                if ($model->exists) {
                    return;
                }

                $current = (string) $model->getAttribute($model->slugSource());
            } elseif (! $model->isDirty($column)) {
                return;
            }

            $model->setAttribute($column, $model->uniqueSlug((string) $current));
        });
    }

    public function slugSource(): string
    {
        return 'name';
    }

    public function slugColumn(): string
    {
        return 'slug';
    }

    public function getRouteKeyName(): string
    {
        return $this->slugColumn();
    }

    protected function uniqueSlug(string $value): string
    {
        $base = Str::slug($value) ?: Str::lower(Str::random(8));
        $slug = $base;
        $suffix = 2;

        while ($this->slugExists($slug)) {
            $slug = "{$base}-{$suffix}";
            $suffix++;
        }

        return $slug;
    }

    protected function slugExists(string $slug): bool
    {
        // Soft-deleted rows still hold their slug in the unique index.
        return static::query()
            ->withoutGlobalScope(SoftDeletingScope::class)
            ->where($this->slugColumn(), $slug)
            ->when($this->exists, fn ($query) => $query->whereKeyNot($this->getKey()))
            ->exists();
    }
}
