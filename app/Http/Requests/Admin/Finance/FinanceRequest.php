<?php

namespace App\Http\Requests\Admin\Finance;

use App\Enums\Permission;
use App\Services\Finance\FinancePeriod;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

/**
 * The Revenue page and its export: a range of local dates, at most a year long. Without one,
 * this month so far.
 */
class FinanceRequest extends FormRequest
{
    public const MAX_DAYS = 366;

    public function authorize(): bool
    {
        $permission = $this->routeIs('admin.finance.export') ? Permission::FinanceExport : Permission::FinanceView;

        return $this->user()?->can($permission) ?? false;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'from' => ['nullable', 'required_with:to', 'date_format:Y-m-d'],
            'to' => ['nullable', 'required_with:from', 'date_format:Y-m-d', 'after_or_equal:from'],
            'page' => ['nullable', 'integer', 'min:1'],
        ];
    }

    /**
     * @return array<int, callable(Validator): void>
     */
    public function after(): array
    {
        return [function (Validator $validator) {
            if ($validator->errors()->isNotEmpty() || ! $this->filled(['from', 'to'])) {
                return;
            }

            $days = CarbonImmutable::parse($this->string('from'))->diffInDays(CarbonImmutable::parse($this->string('to'))) + 1;

            if ($days > self::MAX_DAYS) {
                $validator->errors()->add('to', 'Choose a range of at most a year.');
            }
        }];
    }

    public function period(): FinancePeriod
    {
        return FinancePeriod::fromDates($this->validated('from'), $this->validated('to'));
    }
}
