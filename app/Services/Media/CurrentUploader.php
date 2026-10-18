<?php

namespace App\Services\Media;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Gate;

/**
 * Who is using the media endpoints in this request: a staff member, or the model a
 * renter's booking token resolved to. Filled by the ResolveMediaUploader middleware;
 * a scoped binding, so it never leaks between requests or queued jobs.
 */
class CurrentUploader
{
    private ?User $staff = null;

    private ?Model $tokenOwner = null;

    public function setStaff(?User $staff): void
    {
        $this->staff = $staff;
    }

    public function setTokenOwner(?Model $owner): void
    {
        $this->tokenOwner = $owner;
    }

    public function staff(): ?User
    {
        return $this->staff;
    }

    public function tokenOwner(): ?Model
    {
        return $this->tokenOwner;
    }

    /**
     * Recorded as uploaded_by and as the activity causer.
     */
    public function actor(): ?Model
    {
        return $this->tokenOwner ?? $this->staff;
    }

    /**
     * Check a MediaPolicy ability as the qualified staff member (or as a guest), never as
     * the raw session user, so the super admin bypass also needs a qualifying session.
     */
    public function allows(string $ability, mixed $arguments = []): bool
    {
        return Gate::forUser($this->staff)->allows($ability, $arguments);
    }
}
