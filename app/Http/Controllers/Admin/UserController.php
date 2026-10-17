<?php

namespace App\Http\Controllers\Admin;

use App\Enums\Permission;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\User\IndexUserRequest;
use App\Http\Requests\Admin\User\StoreUserRequest;
use App\Http\Requests\Admin\User\SyncUserAccessRequest;
use App\Http\Requests\Admin\User\ToggleUserActiveRequest;
use App\Http\Requests\Admin\User\UpdateUserRequest;
use App\Http\Resources\Admin\UserResource;
use App\Models\User;
use App\Services\Iam\RoleService;
use App\Services\Iam\UserService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class UserController extends Controller
{
    public function __construct(
        private readonly UserService $users,
        private readonly RoleService $roles,
    ) {}

    public function index(IndexUserRequest $request): Response
    {
        return Inertia::render('admin/users/index', [
            'users' => UserResource::collection($this->users->paginate($request->validated())),
            'filters' => $request->validated(),
            'roles' => $this->roles->names(),
        ]);
    }

    public function create(): Response
    {
        Gate::authorize('create', User::class);

        return Inertia::render('admin/users/create', $this->accessOptions());
    }

    public function store(StoreUserRequest $request): RedirectResponse
    {
        /** @var array{name: string, email: string, roles?: list<string>, permissions?: list<string>} $data */
        $data = $request->validated();
        $user = $this->users->invite($data, $request->user());

        Inertia::flash('toast', ['type' => 'success', 'message' => "Invitation sent to {$user->email}."]);

        return to_route('admin.users.edit', $user);
    }

    public function edit(User $user): Response
    {
        Gate::authorize('view', $user);

        return Inertia::render('admin/users/edit', [
            'user' => UserResource::make($user->load('roles', 'permissions')),
            ...$this->accessOptions(),
        ]);
    }

    public function update(UpdateUserRequest $request, User $user): RedirectResponse
    {
        /** @var array{name: string, email: string} $data */
        $data = $request->validated();
        $this->users->update($user, $data);

        Inertia::flash('toast', ['type' => 'success', 'message' => 'User updated.']);

        return back();
    }

    public function access(SyncUserAccessRequest $request, User $user): RedirectResponse
    {
        $data = $request->validated();

        $this->users->syncAccess(
            $user,
            $request->has('roles') ? ($data['roles'] ?? []) : null,
            $request->has('permissions') ? ($data['permissions'] ?? []) : null,
            $request->user(),
        );

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Access updated.']);

        return back();
    }

    public function toggleActive(ToggleUserActiveRequest $request, User $user): RedirectResponse
    {
        $active = $request->boolean('active');
        $this->users->setActive($user, $active, $request->user());

        Inertia::flash('toast', ['type' => 'success', 'message' => $active ? 'User activated.' : 'User deactivated.']);

        return back();
    }

    public function resendInvitation(Request $request, User $user): RedirectResponse
    {
        Gate::authorize('update', $user);

        $this->users->resendInvitation($user, $request->user());

        Inertia::flash('toast', ['type' => 'success', 'message' => "Invitation re-sent to {$user->email}."]);

        return back();
    }

    public function destroy(Request $request, User $user): RedirectResponse
    {
        Gate::authorize('delete', $user);

        $this->users->delete($user, $request->user());

        Inertia::flash('toast', ['type' => 'success', 'message' => 'User deleted.']);

        return to_route('admin.users.index');
    }

    /**
     * @return array{roleOptions: array<int, string>, permissionOptions: array<string, list<string>>}
     */
    private function accessOptions(): array
    {
        return [
            'roleOptions' => $this->roles->names(),
            'permissionOptions' => Permission::grouped(),
        ];
    }
}
