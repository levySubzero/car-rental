<?php

namespace App\Http\Requests\Admin\Settings;

use App\Enums\SettingsGroup;
use Illuminate\Contracts\Validation\ValidationRule;

class UpdateNotificationSettingsRequest extends UpdateSettingsRequest
{
    public const EVENTS = ['booking_received', 'staff_new_booking', 'payment_submitted', 'booking_confirmed', 'payment_rejected'];

    public function group(): SettingsGroup
    {
        return SettingsGroup::Notification;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'staff_emails' => ['present', 'array', 'max:10'],
            'staff_emails.*' => ['required', 'email', 'max:255', 'distinct'],
            'staff_whatsapp_number' => ['nullable', 'string', 'max:30', 'regex:/^\+?[0-9 ()-]{7,30}$/'],
            'channels' => ['required', 'array:'.implode(',', self::EVENTS)],
            'channels.*.mail' => ['required', 'boolean'],
            'channels.*.whatsapp' => ['required', 'boolean'],
            'voucher_email_subject' => ['required', 'string', 'max:200'],
            'voucher_email_intro' => ['required', 'string', 'max:2000'],
            'voucher_whatsapp_text' => ['required', 'string', 'max:1000'],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function settingsData(): array
    {
        $data = $this->validated();
        $data['channels'] = array_map(
            fn (array $channel) => ['mail' => (bool) $channel['mail'], 'whatsapp' => (bool) $channel['whatsapp']],
            $data['channels'],
        );

        return $data;
    }
}
