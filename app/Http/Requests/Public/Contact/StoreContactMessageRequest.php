<?php

namespace App\Http\Requests\Public\Contact;

use App\Models\Car;
use App\Support\Rules\Turnstile;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * The contact form (M7.15). A renter leaves an email or a phone number, or both.
 */
class StoreContactMessageRequest extends FormRequest
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
        return [
            'name' => ['required', 'string', 'max:120'],
            'email' => ['nullable', 'required_without:phone', 'email:rfc', 'max:255'],
            // "Email or phone" is reported once, on the email field.
            'phone' => ['nullable', 'string', 'max:30', 'regex:/^\+?[0-9 ()-]{7,30}$/'],
            'car' => ['nullable', 'string', Rule::exists('cars', 'slug')->where('is_published', true)->whereNull('deleted_at')],
            'preferred_dates' => ['nullable', 'string', 'max:120'],
            'message' => ['required', 'string', 'max:2000'],
            'cf-turnstile-response' => [new Turnstile($this->ip())],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'email.required_without' => 'Leave an email address or a phone number so we can reply.',
            'phone.regex' => 'Enter a phone number with digits only, for example +254 724 422 051.',
            'car.exists' => 'That car is no longer available. Choose another or leave it blank.',
        ];
    }

    /**
     * @return array{name: string, email: string|null, phone: string|null, message: string, car_id: int|null, preferred_dates: string|null}
     */
    public function attributesForMessage(): array
    {
        $car = $this->filled('car') ? Car::query()->firstWhere('slug', $this->string('car')->toString()) : null;

        return [
            'name' => $this->string('name')->trim()->toString(),
            'email' => $this->filled('email') ? $this->string('email')->trim()->toString() : null,
            'phone' => $this->filled('phone') ? $this->string('phone')->trim()->toString() : null,
            'message' => $this->string('message')->trim()->toString(),
            'car_id' => $car?->id,
            'preferred_dates' => $this->filled('preferred_dates') ? $this->string('preferred_dates')->trim()->toString() : null,
        ];
    }
}
