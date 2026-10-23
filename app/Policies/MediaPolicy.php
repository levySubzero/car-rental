<?php

namespace App\Policies;

use App\Enums\Permission;
use App\Models\Media;
use App\Models\User;
use App\Services\Media\Contracts\TokenUploaderResolver;
use App\Services\Media\CurrentUploader;
use App\Services\Media\MediaCollectionRegistry;
use App\Support\Media\MediaCollection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Gate;

/**
 * Media access (TECHNICAL_PLAN §6.4). Checked through CurrentUploader::allows(), so $user
 * is a qualified staff member or null; a renter is the token owner in CurrentUploader.
 *
 * Attached files defer to their owner's policy: `update` to manage them, and `viewMedia`
 * (falling back to `view`, and given the file) to open private ones. BookingPolicy::viewMedia requires
 * bookings.view-documents, or payments.view for payment proofs.
 */
class MediaPolicy
{
    public function __construct(
        private readonly CurrentUploader $uploader,
        private readonly MediaCollectionRegistry $collections,
        private readonly TokenUploaderResolver $tokens,
    ) {}

    public function upload(?User $user, MediaCollection $collection): bool
    {
        if ($collection->systemUpload) {
            return false;
        }

        $tokenOwner = $this->uploader->tokenOwner();

        if ($tokenOwner !== null) {
            return $collection->tokenUpload
                && $tokenOwner->getMorphClass() === $collection->owner
                && $this->tokens->canUpload($tokenOwner, $collection->key);
        }

        return $user !== null
            && $user->can(Permission::MediaUpload)
            && $collection->uploadPermission !== null
            && $user->can(Permission::from($collection->uploadPermission));
    }

    public function view(?User $user, Media $media): bool
    {
        if ($media->isPublic()) {
            return true;
        }

        $tokenOwner = $this->uploader->tokenOwner();

        if ($tokenOwner !== null) {
            return $media->wasUploadedBy($tokenOwner) || $media->belongsToOwner($tokenOwner);
        }

        if ($user === null) {
            return false;
        }

        if ($media->wasUploadedBy($user) || $user->can(Permission::MediaViewPrivate)) {
            return true;
        }

        $owner = $media->mediable;

        // The file goes along, so an owner can open some collections more widely than others.
        return $owner !== null && Gate::forUser($user)->allows($this->viewAbilityFor($owner), [$owner, $media]);
    }

    public function download(?User $user, Media $media): bool
    {
        return $this->view($user, $media);
    }

    /**
     * Alt text and caption. Staff only.
     */
    public function update(?User $user, Media $media): bool
    {
        if ($user === null) {
            return false;
        }

        if ($media->isPending()) {
            return $media->wasUploadedBy($user);
        }

        return $this->canManage($user, $media->collection, $media->mediable);
    }

    public function delete(?User $user, Media $media): bool
    {
        if ($media->isPending()) {
            return $media->wasUploadedBy($this->uploader->actor());
        }

        return $user !== null && $this->canManage($user, $media->collection, $media->mediable);
    }

    public function reorder(?User $user, MediaCollection $collection, ?Model $owner): bool
    {
        return $user !== null && $this->canManage($user, $collection->key, $owner);
    }

    /**
     * Attached files: the owner's update ability, or the collection's upload permission
     * for standalone collections.
     */
    private function canManage(User $user, string $collection, ?Model $owner): bool
    {
        if ($owner !== null) {
            return Gate::forUser($user)->allows('update', $owner);
        }

        if (! $this->collections->has($collection)) {
            return false;
        }

        $permission = $this->collections->get($collection)->uploadPermission;

        return $permission !== null && $user->can(Permission::from($permission));
    }

    private function viewAbilityFor(Model $owner): string
    {
        $policy = Gate::getPolicyFor($owner);

        return $policy !== null && method_exists($policy, 'viewMedia') ? 'viewMedia' : 'view';
    }
}
