<?php

namespace Database\Factories;

use App\Enums\PolicyGroup;
use App\Models\Policy;
use App\Models\PolicyVersion;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Policy>
 */
class PolicyFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'title' => fake()->unique()->words(3, true),
            'group' => PolicyGroup::General,
            'summary' => fake()->sentence(),
            'show_on_home' => false,
        ];
    }

    /**
     * Give the policy a published version 1 with this text.
     */
    public function published(string $body = 'Published policy text.'): static
    {
        return $this->afterCreating(function (Policy $policy) use ($body) {
            $policy->versions()->create(['version' => 1, 'body' => $body, 'published_at' => now()]);
        });
    }

    /**
     * Give the policy an unpublished draft with this text, numbered after any existing version.
     */
    public function withDraft(string $body = 'Draft policy text.'): static
    {
        return $this->afterCreating(function (Policy $policy) use ($body) {
            $next = (int) PolicyVersion::query()->where('policy_id', $policy->id)->max('version') + 1;
            $policy->versions()->create(['version' => $next, 'body' => $body]);
        });
    }
}
