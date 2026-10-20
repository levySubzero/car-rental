<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\Cms\SetupChecklistService;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Open to every staff member. Each checklist item is filtered by the viewer's permissions.
 */
class DashboardController extends Controller
{
    public function __invoke(Request $request, SetupChecklistService $checklist): Response
    {
        return Inertia::render('admin/dashboard', [
            'setupChecklist' => $checklist->outstanding($request->user()),
        ]);
    }
}
