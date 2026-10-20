<?php

namespace App\Http\Controllers\Admin;

use App\Enums\Permission;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * The sidebar's Content entry: opens the first tab the user may see, like settings.
 * The tabs themselves are listed in resources/js/components/admin/content/content-tabs.tsx.
 */
class ContentController extends Controller
{
    /** Route name => the permission that shows the tab, in tab order. */
    private const TABS = [
        'admin.pages.index' => Permission::PagesView,
        'admin.services.index' => Permission::ServicesView,
        'admin.policies.index' => Permission::PoliciesView,
        'admin.payment-methods.index' => Permission::PaymentMethodsView,
    ];

    public function __invoke(Request $request): RedirectResponse
    {
        foreach (self::TABS as $route => $permission) {
            if ($request->user()?->can($permission)) {
                return to_route($route);
            }
        }

        abort(403);
    }
}
