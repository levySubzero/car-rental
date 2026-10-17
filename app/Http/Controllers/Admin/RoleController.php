<?php

namespace App\Http\Controllers\Admin;

use App\Enums\Permission;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Role\SaveRoleRequest;
use App\Http\Resources\Admin\RoleResource;
use App\Models\Role;
use App\Services\Iam\RoleService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class RoleController extends Controller
{
    public function __construct(private readonly RoleService $roles) {}

    public function index(): Response
    {
        Gate::authorize('viewAny', Role::class);

        return Inertia::render('admin/roles/index', [
            'roles' => RoleResource::collection($this->roles->all()),
        ]);
    }

    public function create(): Response
    {
        Gate::authorize('create', Role::class);

        return Inertia::render('admin/roles/form', [
            'role' => null,
            'permissionOptions' => Permission::grouped(),
        ]);
    }

    public function store(SaveRoleRequest $request): RedirectResponse
    {
        /** @var array{name: string, description?: string|null, permissions?: list<string>} $data */
        $data = $request->validated();
        $role = $this->roles->create($data, $request->user());

        Inertia::flash('toast', ['type' => 'success', 'message' => "Role {$role->name} created."]);

        return to_route('admin.roles.edit', $role);
    }

    public function edit(Role $role): Response
    {
        Gate::authorize('update', $role);

        return Inertia::render('admin/roles/form', [
            'role' => RoleResource::make($role->load('permissions')->loadCount('users')),
            'permissionOptions' => Permission::grouped(),
        ]);
    }

    public function update(SaveRoleRequest $request, Role $role): RedirectResponse
    {
        /** @var array{name: string, description?: string|null, permissions?: list<string>} $data */
        $data = $request->validated();
        $this->roles->update($role, $data, $request->user());

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Role saved.']);

        return back();
    }

    public function destroy(Request $request, Role $role): RedirectResponse
    {
        Gate::authorize('delete', $role);

        $this->roles->delete($role, $request->user());

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Role deleted.']);

        return to_route('admin.roles.index');
    }
}
