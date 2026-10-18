<?php

namespace App\Http\Resources\Admin;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Spatie\Activitylog\Models\Activity;

/**
 * @mixin Activity
 */
class ActivityResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'log' => $this->log_name,
            'event' => $this->event,
            'description' => $this->description,
            'subject' => $this->subject_type ? [
                'type' => class_basename(Relation::getMorphedModel($this->subject_type) ?? $this->subject_type),
                'id' => $this->subject_id,
                'label' => $this->labelFor($this->subject),
            ] : null,
            'causer' => $this->causer ? [
                'id' => $this->causer_id,
                'label' => $this->labelFor($this->causer),
            ] : null,
            'changes' => $this->attribute_changes ?? (object) [],
            'properties' => $this->properties ?? (object) [],
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }

    private function labelFor(?Model $model): ?string
    {
        if ($model === null) {
            return null;
        }

        foreach (['name', 'title', 'reference', 'email'] as $attribute) {
            if (filled($model->getAttribute($attribute))) {
                return (string) $model->getAttribute($attribute);
            }
        }

        return '#'.$model->getKey();
    }
}
