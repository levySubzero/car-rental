<?php

namespace App\Services\Integrations;

use App\Enums\Integration;
use App\Services\Media\MediaDisks;
use App\Settings\IntegrationSettings;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Lays saved integration keys over config() (M12B.15, D46), so every reader of config keeps
 * working and env stays the fallback.
 *
 * Run from a global middleware and the CommandStarting and JobProcessing events, never while
 * booting: config:cache boots the providers and dumps the result, which would write decrypted
 * keys to bootstrap/cache/config.php. JobProcessing is what brings long-running workers up to
 * date without a restart.
 */
class IntegrationConfig
{
    // A hash of what was last applied, so an unchanged request or job does nothing.
    private ?string $applied = null;

    /** @var array<string, mixed> The value under each path now overridden, restored when it is cleared. */
    private array $baseline = [];

    private bool $unreadable = false;

    public function apply(): void
    {
        $saved = $this->read();

        if ($saved === null) {
            return;
        }

        $hash = hash('sha256', serialize($saved));

        if ($hash === $this->applied) {
            return;
        }

        $first = $this->applied === null;
        $this->applied = $hash;
        $overlay = $this->overlay($saved);

        // Nothing saved at start-up: config is already right and the mail and disk caches stand.
        if ($first && $overlay === []) {
            return;
        }

        foreach (array_diff_key($this->baseline, $overlay) as $path => $value) {
            config([$path => $value]);
            unset($this->baseline[$path]);
        }

        foreach ($overlay as $path => $value) {
            if (! array_key_exists($path, $this->baseline)) {
                $this->baseline[$path] = config($path);
            }

            config([$path => $value]);
        }

        // Both keep the keys they were built with for the life of the process.
        app('mail.manager')->forgetMailers();
        app(MediaDisks::class)->forget();
    }

    /**
     * What a path holds without any saved value: the env value.
     */
    public function fallback(string $path): mixed
    {
        return array_key_exists($path, $this->baseline) ? $this->baseline[$path] : config($path);
    }

    /**
     * The saved values could not be decrypted, so env is in use and nothing can be saved.
     */
    public function isUnreadable(): bool
    {
        return $this->unreadable;
    }

    /**
     * @return array<string, array<string, mixed>>|null null when they cannot be read
     */
    private function read(): ?array
    {
        try {
            $saved = app(IntegrationSettings::class)->toArray();
            $this->unreadable = false;

            return $saved;
        } catch (DecryptException) {
            $this->unreadable = true;
            Log::error('Saved integration keys could not be decrypted, so env values are in use. Was APP_KEY changed without APP_PREVIOUS_KEYS?');
        } catch (Throwable $exception) {
            // No table or settings yet (a fresh install, during migrate, an image build).
            Log::debug('Saved integration keys not read: '.$exception::class);
        }

        return null;
    }

    /**
     * @param  array<string, array<string, mixed>>  $saved
     * @return array<string, mixed> config path => saved value
     */
    private function overlay(array $saved): array
    {
        $overlay = [];

        foreach (Integration::cases() as $integration) {
            foreach ($integration->fields() as $field) {
                if (array_key_exists($field->key, $saved[$integration->value] ?? [])) {
                    foreach ($field->paths as $path) {
                        $overlay[$path] = $saved[$integration->value][$field->key];
                    }
                }
            }
        }

        return $overlay;
    }
}
