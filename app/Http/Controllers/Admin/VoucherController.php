<?php

namespace App\Http\Controllers\Admin;

use App\Enums\VoucherBucket;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Booking\IndexVoucherRequest;
use App\Http\Resources\Admin\VoucherListResource;
use App\Models\Car;
use App\Services\Booking\VoucherDeskService;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Every voucher by where it stands. Each one opens on its booking's voucher page.
 */
class VoucherController extends Controller
{
    public function index(IndexVoucherRequest $request, VoucherDeskService $desk): Response
    {
        $filters = $request->validated();
        $filters['bucket'] ??= VoucherBucket::NotSent->value;

        return Inertia::render('admin/vouchers/index', [
            'vouchers' => VoucherListResource::collection($desk->paginate($filters)),
            'filters' => $filters,
            'counts' => $desk->counts(),
            'buckets' => array_map(fn (VoucherBucket $bucket) => ['value' => $bucket->value, 'label' => $bucket->label()], VoucherBucket::cases()),
            'cars' => Car::query()->orderBy('name')->get(['id', 'name'])->map(fn (Car $car) => ['value' => (string) $car->id, 'label' => $car->name]),
        ]);
    }
}
