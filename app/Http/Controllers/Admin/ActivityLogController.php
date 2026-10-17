<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Activity\IndexActivityRequest;
use App\Http\Resources\Admin\ActivityResource;
use App\Services\Audit\ActivityLogService;
use Inertia\Inertia;
use Inertia\Response;

class ActivityLogController extends Controller
{
    public function index(IndexActivityRequest $request, ActivityLogService $activity): Response
    {
        return Inertia::render('admin/activity/index', [
            'activities' => ActivityResource::collection($activity->paginate($request->validated())),
            'filters' => $request->validated(),
            'options' => $activity->filterOptions(),
        ]);
    }
}
