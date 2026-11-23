<?php

namespace App\Http\Requests\Public\Booking;

use App\Http\Middleware\ResolveBookingToken;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

/**
 * The renter asking for more days from their booking page (D50). The token in the URL is the
 * authorization; the same rules serve the live preview, which ignores the note.
 */
class ExtendBookingRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $booking = ResolveBookingToken::booking($this);

        return [
            'return_at' => ['required', 'date', 'after:'.$booking->chainEnd()->toIso8601String(), 'before:+1 year'],
            'note' => ['nullable', 'string', 'max:500'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'return_at.after' => 'The new return must be after your current one.',
            'return_at.before' => 'Please choose a return within a year.',
        ];
    }

    public function returnAt(): CarbonImmutable
    {
        return CarbonImmutable::parse((string) $this->validated('return_at'))->utc();
    }

    public function note(): ?string
    {
        $note = trim((string) $this->validated('note'));

        return $note === '' ? null : $note;
    }
}
