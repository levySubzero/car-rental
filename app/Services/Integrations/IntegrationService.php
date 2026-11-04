<?php

namespace App\Services\Integrations;

use App\Enums\Integration;
use App\Enums\IntegrationFieldType;
use App\Enums\MpesaProvider;
use App\Models\User;
use App\Settings\GatewaySettings;
use App\Settings\IntegrationSettings;

/**
 * Reads and saves the admin Integrations page (M12B.15, D46). Secrets go in and never come out:
 * the page only learns where each one comes from, and the activity log only learns which
 * fields changed.
 */
class IntegrationService
{
    public function __construct(private readonly IntegrationConfig $config) {}

    /**
     * The fields as the page shows them. A secret has no value here.
     *
     * @return list<array<string, mixed>>
     */
    public function fields(Integration $integration): array
    {
        $saved = $this->saved($integration);

        return array_map(function (IntegrationField $field) use ($saved) {
            $current = $this->effective($field, $saved);

            return [
                'key' => $field->key,
                'label' => $field->label,
                'type' => $field->type->value,
                'description' => $field->description,
                'options' => $this->options($field, $current),
                'source' => array_key_exists($field->key, $saved) ? 'saved' : (filled($current) ? 'env' : 'unset'),
                'value' => $field->isSecret() ? null : $this->display($field, $current),
            ];
        }, $integration->fields());
    }

    /**
     * Paystack's mode, which follows its keys: "test", "live" or null when no key says.
     */
    public function paystackMode(): ?string
    {
        return $this->mode((string) $this->effective($this->field(Integration::Paystack, 'public_key'), $this->saved(Integration::Paystack)));
    }

    /**
     * The choices a field accepts: its own, plus an env value outside them (MAIL_MAILER=smtp)
     * so saving the page does not refuse what the server already has.
     *
     * @return list<array{value: string, label: string}>
     */
    public function options(IntegrationField $field, mixed $current = null): array
    {
        $options = $field->options;

        if ($field->type === IntegrationFieldType::Choice && is_string($current) && $current !== '') {
            $options += [$current => $current];
        }

        return array_map(fn (string $value, string $label) => ['value' => $value, 'label' => $label], array_keys($options), $options);
    }

    /**
     * What an integration would have saved after this input. A blank secret keeps its value,
     * and a value equal to what is in force is not saved, so env values are not pinned.
     *
     * @param  array<string, mixed>  $input
     * @param  list<string>  $clear
     * @return array<string, mixed>
     */
    public function merged(Integration $integration, array $input, array $clear = []): array
    {
        $saved = $this->saved($integration);
        $next = $saved;

        foreach ($integration->fields() as $field) {
            if (in_array($field->key, $clear, true)) {
                unset($next[$field->key]);

                continue;
            }

            if (! array_key_exists($field->key, $input)) {
                continue;
            }

            $value = $this->normalised($field, $input[$field->key]);

            if ($value === null || $value === []) {
                if (! $field->isSecret()) {
                    unset($next[$field->key]);
                }

                continue;
            }

            if ($value !== $this->effective($field, $saved)) {
                $next[$field->key] = $value;
            }
        }

        return $next;
    }

    /**
     * Why these values would not work, by field key. Checked against what would be in force,
     * saved values over env, so a key already on the server counts.
     *
     * @param  array<string, mixed>  $next
     * @return array<string, string>
     */
    public function problems(Integration $integration, array $next): array
    {
        $value = fn (string $key) => $this->effective($this->field($integration, $key), $next);

        return match ($integration) {
            Integration::Daraja => array_filter([
                // Every callback is refused without it (VerifyDarajaCallback).
                'allowed_ips' => $value('env') === 'production' && blank($value('allowed_ips'))
                    ? "Production needs Safaricom's addresses here, or every callback is refused."
                    : null,
            ]),
            Integration::Paystack => array_filter([
                'secret_key' => filled($value('public_key')) && filled($value('secret_key')) && ($this->mode((string) $value('public_key')) === null || $this->mode((string) $value('public_key')) !== $this->mode((string) $value('secret_key')))
                    ? 'The public and secret keys must both be test keys or both be live keys.'
                    : null,
                'mpesa_provider' => $value('mpesa_provider') === MpesaProvider::Paystack->value && (blank($value('public_key')) || blank($value('secret_key')))
                    ? 'M-Pesa through Paystack needs the Paystack keys.'
                    : null,
            ]),
            Integration::Resend => array_filter([
                'key' => $value('mailer') === 'resend' && blank($value('key'))
                    ? 'Sending with Resend needs an API key.'
                    : null,
            ]),
            Integration::S3 => $this->s3Problems($value),
            default => [],
        };
    }

    /**
     * Save an integration, apply it to this process and log which fields changed.
     *
     * @param  array<string, mixed>  $input
     * @param  list<string>  $clear
     * @return list<string> the keys of the fields that changed
     */
    public function update(Integration $integration, array $input, array $clear, User $actor): array
    {
        $saved = $this->saved($integration);
        $next = $this->merged($integration, $input, $clear);
        $changed = array_values(array_filter(
            array_map(fn (IntegrationField $field) => $field->key, $integration->fields()),
            fn (string $key) => ($saved[$key] ?? null) !== ($next[$key] ?? null),
        ));

        if ($changed === []) {
            return [];
        }

        $settings = app(IntegrationSettings::class);
        $settings->{$integration->value} = $next;
        $settings->save();

        if ($integration === Integration::Daraja && in_array('callback_secret', $changed, true)) {
            $this->forgetC2bRegistration();
        }

        $this->config->apply();

        activity('integrations')
            ->causedBy($actor)
            ->withProperties(['integration' => $integration->value, 'changed' => $changed])
            ->event('updated')
            ->log("{$integration->label()} integration updated");

        return $changed;
    }

    public function field(Integration $integration, string $key): IntegrationField
    {
        foreach ($integration->fields() as $field) {
            if ($field->key === $key) {
                return $field;
            }
        }

        throw new \InvalidArgumentException("No field [{$key}] on integration [{$integration->value}].");
    }

    /**
     * What a field holds now, saved over env.
     */
    public function current(Integration $integration, IntegrationField $field): mixed
    {
        return $this->effective($field, $this->saved($integration));
    }

    /**
     * @param  callable(string): mixed  $value
     * @return array<string, string>
     */
    private function s3Problems(callable $value): array
    {
        $public = str_ends_with((string) $value('public_disk'), '-s3');
        $private = str_ends_with((string) $value('private_disk'), '-s3');
        $needed = $public || $private ? ['key', 'secret', 'region'] : [];

        if ($public) {
            $needed[] = 'public_bucket';
        }

        if ($private) {
            $needed[] = 'private_bucket';
        }

        $problems = [];

        foreach ($needed as $key) {
            if (blank($value($key))) {
                $problems[$key] = 'Storing files on S3 needs this.';
            }
        }

        return $problems;
    }

    /**
     * The URLs Safaricom holds carry the old secret, so the before-launch checklist asks for
     * registration again.
     */
    private function forgetC2bRegistration(): void
    {
        $gateway = app(GatewaySettings::class);
        $gateway->c2b_registered_at = null;
        $gateway->c2b_registered_shortcode = null;
        $gateway->save();
    }

    private function mode(string $key): ?string
    {
        return preg_match('/^[ps]k_(test|live)_/', $key, $match) === 1 ? $match[1] : null;
    }

    /**
     * @return array<string, mixed>
     */
    private function saved(Integration $integration): array
    {
        if ($this->config->isUnreadable()) {
            return [];
        }

        return app(IntegrationSettings::class)->{$integration->value};
    }

    /**
     * @param  array<string, mixed>  $saved
     */
    private function effective(IntegrationField $field, array $saved): mixed
    {
        return array_key_exists($field->key, $saved) ? $saved[$field->key] : $this->config->fallback($field->paths[0]);
    }

    private function normalised(IntegrationField $field, mixed $value): mixed
    {
        return match ($field->type) {
            IntegrationFieldType::List => array_values(array_filter(array_map('trim', explode(',', (string) $value)))),
            IntegrationFieldType::Toggle => filter_var($value, FILTER_VALIDATE_BOOL),
            default => blank($value) ? null : (string) $value,
        };
    }

    private function display(IntegrationField $field, mixed $value): mixed
    {
        return match ($field->type) {
            IntegrationFieldType::List => implode(', ', (array) $value),
            IntegrationFieldType::Toggle => (bool) $value,
            default => $value,
        };
    }
}
