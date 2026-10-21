<?php

namespace App\Console\Commands;

use App\Services\Pricing\FxRateService;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('fx:refresh
    {--force : Fetch even if the last attempt was within refresh_minutes}
    {--accept-jumps : Store rates even if they moved more than max_jump_percent}')]
#[Description('Fetch exchange rates from the configured provider (FxSettings)')]
class FxRefreshCommand extends Command
{
    public function handle(FxRateService $fx): int
    {
        $result = $fx->refresh((bool) $this->option('force'), (bool) $this->option('accept-jumps'));

        foreach ($result->stored as $code => $rate) {
            $this->components->twoColumnDetail($code, "{$rate} stored");
        }

        foreach ($result->rejected as $code => $jump) {
            $this->components->twoColumnDetail($code, "<fg=yellow>{$jump['fetched']} rejected: {$jump['change']}% from {$jump['previous']}</>");
        }

        if ($result->failed()) {
            $this->components->error($result->message);

            return self::FAILURE;
        }

        $this->components->info($result->message);

        return self::SUCCESS;
    }
}
