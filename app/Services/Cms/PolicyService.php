<?php

namespace App\Services\Cms;

use App\Exceptions\Domain\CmsException;
use App\Models\Policy;
use App\Models\PolicyVersion;
use App\Models\User;
use App\Support\Markdown;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;

/**
 * The rental policy (PRD §11, A-2). Text changes go through versions (D24): staff edit a draft,
 * publishing it makes it the live text and snapshots a new policy bundle.
 *
 * @phpstan-type PolicyDetails array{title: string, slug?: string|null, group: string, summary?: string|null, show_on_home?: bool}
 */
class PolicyService
{
    public function __construct(private readonly PolicyBundleService $bundles) {}

    /**
     * Every live policy in display order, with its published version and draft.
     *
     * @return Collection<int, Policy>
     */
    public function ordered(): Collection
    {
        return Policy::query()->with(['published', 'draft'])->ordered()->get();
    }

    /**
     * A new policy starts as a draft: nothing changes for renters until it is published.
     *
     * @param  PolicyDetails  $details
     */
    public function create(array $details, string $body, User $actor): Policy
    {
        return DB::transaction(function () use ($details, $body, $actor) {
            $policy = Policy::create($this->attributes($details));
            $policy->versions()->create(['version' => 1, 'body' => $body, 'created_by' => $actor->id]);

            return $policy;
        });
    }

    /**
     * Title, group, summary and the home page flag. These are not versioned: they label the
     * policy but are not part of the text renters accept.
     *
     * @param  PolicyDetails  $details
     */
    public function updateDetails(Policy $policy, array $details): Policy
    {
        $policy->update($this->attributes($details));

        return $policy;
    }

    /**
     * Save new text as the draft: the existing draft is overwritten, or a new version is
     * started after the latest one. Text identical to the published version discards the draft.
     */
    public function saveDraft(Policy $policy, string $body, User $actor): ?PolicyVersion
    {
        return DB::transaction(function () use ($policy, $body, $actor) {
            $policy = Policy::query()->with(['published', 'draft'])->lockForUpdate()->findOrFail($policy->id);

            if ($policy->published !== null && $this->sameText($policy->published->body, $body)) {
                $policy->draft?->delete();

                return null;
            }

            if ($policy->draft !== null) {
                $policy->draft->update(['body' => $body, 'created_by' => $actor->id]);

                return $policy->draft;
            }

            return $policy->versions()->create([
                'version' => $this->nextVersion($policy),
                'body' => $body,
                'created_by' => $actor->id,
            ]);
        });
    }

    public function discardDraft(Policy $policy): void
    {
        $policy->draft()->first()?->delete();
    }

    /**
     * Make the draft the live text and publish a new bundle. A draft that still has a
     * [CONFIRM: …] note is refused.
     */
    public function publish(Policy $policy, User $actor): PolicyVersion
    {
        return DB::transaction(function () use ($policy, $actor) {
            $draft = PolicyVersion::query()
                ->where('policy_id', $policy->id)
                ->whereNull('published_at')
                ->lockForUpdate()
                ->first();

            if ($draft === null) {
                throw CmsException::nothingToPublish();
            }

            $this->publishVersion($draft, $actor);
            $this->bundles->snapshot($actor);

            return $draft;
        });
    }

    /**
     * Publish one version without snapshotting a bundle. The caller snapshots once at the end,
     * e.g. the seeder after publishing many policies.
     */
    public function publishVersion(PolicyVersion $version, ?User $actor = null): void
    {
        if (Markdown::hasConfirm($version->body)) {
            throw CmsException::unresolvedConfirm();
        }

        $version->update(['published_at' => now()]);

        activity('policies')->causedBy($actor)
            ->performedOn($version->policy)
            ->withProperties(['version' => $version->version])
            ->event('published')
            ->log("version {$version->version} published");
    }

    public function setShowOnHome(Policy $policy, bool $show): Policy
    {
        $policy->update(['show_on_home' => $show]);

        return $policy;
    }

    /**
     * @param  list<int>  $ids  every policy id, in the new order
     */
    public function reorder(array $ids, User $actor): void
    {
        Policy::reorder($ids);

        activity('policies')->causedBy($actor)
            ->withProperties(['order' => $ids])
            ->event('reordered')
            ->log('policies reordered');
    }

    /**
     * Soft-delete: bundles renters accepted keep pointing at its versions. A live policy
     * leaves the rental terms, so a new bundle is published without it.
     */
    public function delete(Policy $policy, User $actor): void
    {
        DB::transaction(function () use ($policy, $actor) {
            $wasLive = $policy->published()->exists();

            $policy->draft()->first()?->delete();
            $policy->delete();

            if ($wasLive) {
                $this->bundles->snapshot($actor);
            }
        });
    }

    public function nextVersion(Policy $policy): int
    {
        return (int) PolicyVersion::query()->where('policy_id', $policy->id)->max('version') + 1;
    }

    /**
     * Line endings and trailing whitespace are not changes.
     */
    public function sameText(string $a, string $b): bool
    {
        $normalise = fn (string $text) => trim(preg_replace('/[ \t]+$/m', '', str_replace("\r\n", "\n", $text)) ?? $text);

        return $normalise($a) === $normalise($b);
    }

    /**
     * A blank slug means "keep it" on update and "generate from the title" on create.
     *
     * @param  array<string, mixed>  $details
     * @return array<string, mixed>
     */
    private function attributes(array $details): array
    {
        return filled($details['slug'] ?? null) ? $details : Arr::except($details, ['slug']);
    }
}
