<?php

use App\Enums\Integration;
use App\Enums\Permission;
use App\Models\Role;
use App\Models\User;
use App\Services\Integrations\IntegrationConfig;
use App\Settings\GatewaySettings;
use App\Settings\IntegrationSettings;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Contracts\Queue\Job;
use Illuminate\Encryption\Encrypter;
use Illuminate\Mail\Transport\ResendTransport;
use Illuminate\Queue\Events\JobProcessing;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Facade;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Illuminate\Testing\TestResponse;
use Inertia\Testing\AssertableInertia as Assert;
use Spatie\Activitylog\Models\Activity;

const SECRET = 'sk_live_a-very-recognisable-secret';

function integrationAdmin(): User
{
    return superAdmin();
}

function saveIntegration(string $integration, array $credentials, array $clear = []): TestResponse
{
    return test()->actingAs(integrationAdmin())
        ->withSession(['auth.password_confirmed_at' => time()])
        ->from(route('admin.integrations.edit', $integration))
        ->put(route('admin.integrations.update', $integration), ['credentials' => $credentials, 'clear' => $clear]);
}

function processingJob(): Job
{
    return Mockery::mock(Job::class)->shouldReceive('payload')->andReturn([])->getMock();
}

function fieldProp(TestResponse $response, string $key): array
{
    return collect($response->viewData('page')['props']['fields'])->firstWhere('key', $key);
}

it('is only for people with the permission, and asks for the password again', function () {
    $this->actingAs(staffWith())->get(route('admin.integrations.edit', 'daraja'))->assertForbidden();
    $this->actingAs(staffWith())->put(route('admin.integrations.update', 'daraja'), ['credentials' => []])->assertForbidden();

    $this->actingAs(superAdmin())->get(route('admin.integrations.edit', 'daraja'))->assertRedirect(route('password.confirm'));

    $this->actingAs(staffWith([Permission::IntegrationsManage]))
        ->withSession(['auth.password_confirmed_at' => time()])
        ->get(route('admin.integrations.edit', 'daraja'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('admin/integrations/edit')->has('integrations', 6));
});

it('gives the permission to no seeded role', function () {
    $this->seed();

    expect(Role::query()->where('name', '!=', config('baines.super_admin_role'))->get()
        ->filter(fn ($role) => $role->hasPermissionTo(Permission::IntegrationsManage->value)))->toBeEmpty();
});

it('stores secrets encrypted and never lets them out', function () {
    saveIntegration('paystack', ['public_key' => 'pk_live_1', 'secret_key' => SECRET])->assertSessionHasNoErrors();

    $stored = DB::table('settings')->where('group', 'integrations')->where('name', 'paystack')->value('payload');
    expect($stored)->not->toContain(SECRET);

    $response = $this->actingAs(integrationAdmin())->withSession(['auth.password_confirmed_at' => time()])->get(route('admin.integrations.edit', 'paystack'));
    expect(json_encode($response->viewData('page')))->not->toContain(SECRET)
        ->and(fieldProp($response, 'secret_key'))->toMatchArray(['source' => 'saved', 'value' => null])
        ->and(fieldProp($response, 'public_key'))->toMatchArray(['source' => 'saved', 'value' => 'pk_live_1']);

    $activity = Activity::query()->where('log_name', 'integrations')->sole();
    expect(json_encode($activity->toArray()))->not->toContain(SECRET)
        ->and($activity->properties['changed'])->toBe(['public_key', 'secret_key']);
});

it('does not flash credentials back after a validation error', function () {
    saveIntegration('paystack', ['public_key' => 'pk_live_1', 'secret_key' => 'sk_test_'.SECRET])
        ->assertSessionHasErrors('credentials.secret_key')
        ->assertSessionMissing('_old_input.credentials');
});

it('prefers a saved value, keeps it on a blank input and falls back to env when cleared', function () {
    config(['services.fx.api_key' => 'from-env']);

    saveIntegration('fx', ['api_key' => 'saved-key'])->assertSessionHasNoErrors();
    expect(config('services.fx.api_key'))->toBe('saved-key');

    saveIntegration('fx', ['api_key' => ''])->assertSessionHasNoErrors();
    expect(config('services.fx.api_key'))->toBe('saved-key')
        ->and(app(IntegrationSettings::class)->fx)->toBe(['api_key' => 'saved-key']);

    saveIntegration('fx', ['api_key' => ''], ['api_key'])->assertSessionHasNoErrors();
    expect(config('services.fx.api_key'))->toBe('from-env')
        ->and(app(IntegrationSettings::class)->fx)->toBe([]);
});

it('does not pin env values when the form sends them back unchanged', function () {
    config(['services.daraja.env' => 'sandbox', 'services.daraja.type' => 'paybill']);

    saveIntegration('daraja', ['env' => 'sandbox', 'type' => 'paybill', 'shortcode' => null, 'allowed_ips' => ''])->assertSessionHasNoErrors();

    expect(app(IntegrationSettings::class)->daraja)->toBe([])
        ->and(Activity::query()->where('log_name', 'integrations')->exists())->toBeFalse();
});

it('accepts an env choice outside the listed options', function () {
    config(['mail.default' => 'smtp']);

    saveIntegration('resend', ['mailer' => 'smtp'])->assertSessionHasNoErrors();

    expect(app(IntegrationSettings::class)->resend)->toBe([]);
});

it('leaves config set after boot alone while nothing is saved', function () {
    config(['services.paystack.secret_key' => 'set-by-a-test']);

    $this->get(route('health'));

    expect(config('services.paystack.secret_key'))->toBe('set-by-a-test');
});

it('stores allowed IPs as a list and rejects bad ones', function () {
    saveIntegration('daraja', ['allowed_ips' => '196.201.214.200, 196.201.214.0/24'])->assertSessionHasNoErrors();
    expect(config('services.daraja.allowed_ips'))->toBe(['196.201.214.200', '196.201.214.0/24']);

    saveIntegration('daraja', ['allowed_ips' => 'not-an-ip'])->assertSessionHasErrors('credentials.allowed_ips');
    saveIntegration('daraja', ['allowed_ips' => '10.0.0.1/99'])->assertSessionHasErrors('credentials.allowed_ips');
});

it('refuses values that would not work', function (string $integration, array $credentials, string $error) {
    config([
        'services.daraja.allowed_ips' => [],
        'services.paystack' => ['public_key' => null, 'secret_key' => null],
        'services.resend.key' => null,
        'filesystems.disks.media-public-s3.key' => null,
        'filesystems.disks.media-public-s3.bucket' => null,
        'filesystems.disks.media-private-s3.bucket' => null,
    ]);

    saveIntegration($integration, $credentials)->assertSessionHasErrors("credentials.{$error}");
})->with([
    'production without allowed IPs' => ['daraja', ['env' => 'production'], 'allowed_ips'],
    'test public key with a live secret' => ['paystack', ['public_key' => 'pk_test_1', 'secret_key' => 'sk_live_1'], 'secret_key'],
    'keys that are neither test nor live' => ['paystack', ['public_key' => 'nope', 'secret_key' => 'nope'], 'secret_key'],
    'M-Pesa through Paystack without keys' => ['paystack', ['mpesa_provider' => 'paystack'], 'mpesa_provider'],
    'Resend without a key' => ['resend', ['mailer' => 'resend'], 'key'],
    'an S3 disk without keys' => ['s3', ['public_disk' => 'media-public-s3'], 'key'],
    'an S3 disk without its bucket' => ['s3', ['private_disk' => 'media-private-s3', 'key' => 'k', 'secret' => 's', 'region' => 'r'], 'private_bucket'],
    'a choice that is not offered' => ['daraja', ['env' => 'staging'], 'env'],
    'a bad from address' => ['resend', ['from_address' => 'nope'], 'from_address'],
]);

it('counts a key already in env when checking', function () {
    config(['services.resend.key' => 're_from_env']);

    saveIntegration('resend', ['mailer' => 'resend'])->assertSessionHasNoErrors();

    expect(config('mail.default'))->toBe('resend');
});

it('asks for C2B registration again after a new callback secret', function () {
    $gateway = app(GatewaySettings::class);
    $gateway->c2b_registered_at = now()->toIso8601String();
    $gateway->c2b_registered_shortcode = '174379';
    $gateway->save();

    saveIntegration('daraja', ['shortcode' => '600000'])->assertSessionHasNoErrors();
    expect(app(GatewaySettings::class)->c2b_registered_at)->not->toBeNull();

    saveIntegration('daraja', ['callback_secret' => 'a-new-secret'])->assertSessionHasNoErrors();
    expect(app(GatewaySettings::class)->c2b_registered_at)->toBeNull();
});

it('reaches a long-running worker on the next job and rebuilds the mail transport', function () {
    config(['mail.default' => 'array', 'services.resend.key' => null]);
    app(IntegrationConfig::class)->apply();
    Mail::mailer('array');

    // Saved by another process: this one only sees it through the settings store.
    $settings = app(IntegrationSettings::class);
    $settings->resend = ['mailer' => 'resend', 'key' => 're_new'];
    $settings->save();

    expect(config('mail.default'))->toBe('array');

    event(new JobProcessing('redis', processingJob()));

    expect(config('mail.default'))->toBe('resend')
        ->and(config('services.resend.key'))->toBe('re_new')
        ->and(Mail::mailer()->getSymfonyTransport())->toBeInstanceOf(ResendTransport::class);

    $settings->resend = ['mailer' => 'resend', 'key' => 're_rotated'];
    $settings->save();
    event(new JobProcessing('redis', processingJob()));

    expect(config('services.resend.key'))->toBe('re_rotated');
});

it('points both S3 disks at saved keys and rebuilds them', function () {
    Storage::forgetDisk(['media-public-s3', 'media-private-s3']);

    saveIntegration('s3', [
        'public_disk' => 'media-public-s3', 'private_disk' => 'media-private-s3',
        'key' => 'AKIA1', 'secret' => 's3-secret', 'region' => 'eu-west-1',
        'public_bucket' => 'pub', 'private_bucket' => 'priv', 'path_style' => true,
    ])->assertSessionHasNoErrors();

    expect(config('media.disks'))->toMatchArray(['public' => 'media-public-s3', 'private' => 'media-private-s3'])
        ->and(config('filesystems.disks.media-public-s3'))->toMatchArray(['key' => 'AKIA1', 'bucket' => 'pub', 'use_path_style_endpoint' => true])
        ->and(config('filesystems.disks.media-private-s3'))->toMatchArray(['key' => 'AKIA1', 'bucket' => 'priv', 'use_path_style_endpoint' => true]);
});

it('falls back to env when saved keys cannot be decrypted', function () {
    config(['services.fx.api_key' => 'from-env']);
    DB::table('settings')->where('group', 'integrations')->where('name', 'fx')->update([
        'payload' => json_encode((new Encrypter(random_bytes(32), 'aes-256-cbc'))->encrypt(['api_key' => 'other-key'])),
    ]);
    Log::spy();

    $this->get(route('health'));

    expect(config('services.fx.api_key'))->toBe('from-env')
        ->and(app(IntegrationConfig::class)->isUnreadable())->toBeTrue();
    Log::shouldHaveReceived('error')->once();

    $response = $this->actingAs(integrationAdmin())->withSession(['auth.password_confirmed_at' => time()])->get(route('admin.integrations.edit', 'fx'));
    $response->assertInertia(fn (Assert $page) => $page->where('unreadable', true));

    saveIntegration('fx', ['api_key' => 'new'])->assertSessionHasErrors('credentials');
});

it('falls back to env when the settings cannot be read at all', function () {
    config(['services.fx.api_key' => 'from-env']);
    DB::table('settings')->where('group', 'integrations')->delete();

    app()->forgetScopedInstances();
    app(IntegrationConfig::class)->apply();

    expect(config('services.fx.api_key'))->toBe('from-env')
        ->and(app(IntegrationConfig::class)->isUnreadable())->toBeFalse();
});

it('never applies saved keys while booting, which config:cache would write to disk', function () {
    $resolved = false;

    // What config:cache does: boot a fresh app and dump its config.
    $fresh = require base_path('bootstrap/app.php');
    $fresh->resolving(IntegrationConfig::class, function () use (&$resolved) {
        $resolved = true;
    });
    $fresh->make(Kernel::class)->bootstrap();

    // bootstrap() repoints the facades at the fresh app.
    Facade::setFacadeApplication(app());

    expect($resolved)->toBeFalse();
});

it('works while mail is faked', function () {
    Mail::fake();

    saveIntegration('resend', ['from_name' => 'Baines'])->assertSessionHasNoErrors();

    expect(config('mail.from.name'))->toBe('Baines');
});

it('maps every field to config paths that exist', function () {
    foreach (Integration::cases() as $integration) {
        foreach ($integration->fields() as $field) {
            foreach ($field->paths as $path) {
                expect(config()->has($path))->toBeTrue("{$integration->value}.{$field->key}: {$path}");
            }
        }
    }
});
