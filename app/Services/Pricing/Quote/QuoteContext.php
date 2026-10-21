<?php

namespace App\Services\Pricing\Quote;

use App\Models\Charge;
use App\Models\DepositBand;
use App\Services\Pricing\Fx\FxTable;

/**
 * The pricing rules in force when the quote is made, loaded once by QuoteService.
 */
final readonly class QuoteContext
{
    /**
     * @param  list<Charge>  $charges  active charges in display order
     * @param  list<DepositBand>  $bands  active bands
     */
    public function __construct(
        public QuoteSettings $settings,
        public FxTable $fx,
        public array $charges,
        public array $bands,
    ) {}
}
