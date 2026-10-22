<?php

namespace App\Http\Requests\Public\Fleet;

use App\Enums\Transmission;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Fleet filters from the query string (M7.9). Public links get shared and edited by hand,
 * so an unknown value is ignored rather than rejected: the page always renders.
 */
class FleetRequest extends FormRequest
{
    public const SORTS = ['recommended', 'price_asc', 'price_desc'];

    // "At least n seats"
    public const SEATS = [2, 4, 5, 7];

    public const PER_PAGE = 12;

    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [];
    }

    /**
     * @return array{brand: string|null, type: string|null, transmission: string|null, seats: int|null, sort: string}
     */
    public function filters(): array
    {
        $seats = filter_var($this->query('seats'), FILTER_VALIDATE_INT);
        $transmission = $this->string('transmission')->toString();
        $sort = $this->string('sort')->toString();

        return [
            'brand' => $this->slug('brand'),
            'type' => $this->slug('type'),
            'transmission' => Transmission::tryFrom($transmission)?->value,
            'seats' => in_array($seats, self::SEATS, true) ? $seats : null,
            'sort' => in_array($sort, self::SORTS, true) ? $sort : 'recommended',
        ];
    }

    private function slug(string $key): ?string
    {
        $value = $this->query($key);

        return is_string($value) && preg_match('/^[a-z0-9]+(?:-[a-z0-9]+)*$/', $value) ? $value : null;
    }
}
