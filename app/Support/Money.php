<?php

namespace App\Support;

use App\Enums\RoundingMode;
use InvalidArgumentException;
use JsonSerializable;

/**
 * An exact amount of money in integer minor units (cents).
 *
 * All arithmetic is integer-only, so no float rounding errors creep into
 * quotes. Money in the database lives in `*_minor` bigint columns.
 */
final readonly class Money implements JsonSerializable
{
    public function __construct(
        public int $minor,
        public string $currency = 'KES',
        public int $decimals = 2,
    ) {
        if ($decimals < 0 || $decimals > 4) {
            throw new InvalidArgumentException("Unsupported number of decimals [{$decimals}].");
        }
    }

    public static function zero(string $currency = 'KES', int $decimals = 2): self
    {
        return new self(0, $currency, $decimals);
    }

    /**
     * Build from a major-unit amount such as "16587.5" or 2000, as typed into a form.
     */
    public static function fromMajor(int|float|string $major, string $currency = 'KES', int $decimals = 2): self
    {
        if (is_float($major)) {
            $major = number_format($major, $decimals, '.', '');
        }

        $value = trim((string) $major);

        if (preg_match('/^(-)?(\d+)(?:\.(\d+))?$/', $value, $parts) !== 1) {
            throw new InvalidArgumentException("Invalid money amount [{$value}].");
        }

        $fraction = $parts[3] ?? '';

        if (strlen(rtrim($fraction, '0')) > $decimals) {
            throw new InvalidArgumentException("Amount [{$value}] has more than {$decimals} decimal places.");
        }

        $minor = (int) $parts[2] * (10 ** $decimals) + (int) str_pad(substr($fraction, 0, $decimals), $decimals, '0');

        return new self($parts[1] === '-' ? -$minor : $minor, $currency, $decimals);
    }

    public function unit(): int
    {
        return 10 ** $this->decimals;
    }

    /**
     * The amount in major units as an exact decimal string, e.g. "16588.00".
     */
    public function toMajorString(): string
    {
        $absolute = abs($this->minor);
        $whole = intdiv($absolute, $this->unit());
        $fraction = $absolute % $this->unit();

        $string = $this->decimals === 0
            ? (string) $whole
            : $whole.'.'.str_pad((string) $fraction, $this->decimals, '0', STR_PAD_LEFT);

        return ($this->minor < 0 ? '-' : '').$string;
    }

    /**
     * For display: "KES 15,000" or "KES 16,587.50". Cents are shown only when there are any.
     */
    public function format(): string
    {
        $absolute = abs($this->minor);
        $decimals = $absolute % $this->unit() === 0 ? 0 : $this->decimals;
        $number = number_format($absolute / $this->unit(), $decimals, '.', ',');

        return $this->currency.' '.($this->minor < 0 ? '-' : '').$number;
    }

    public function add(self $other): self
    {
        $this->assertSameCurrency($other);

        return $this->withMinor($this->minor + $other->minor);
    }

    public function subtract(self $other): self
    {
        $this->assertSameCurrency($other);

        return $this->withMinor($this->minor - $other->minor);
    }

    public function multiply(int $times): self
    {
        return $this->withMinor($this->minor * $times);
    }

    /**
     * Take a percentage given in basis points (2500 = 25%) and round it.
     */
    public function percentBp(int $basisPoints, RoundingMode $rounding): self
    {
        if ($this->minor < 0 || $basisPoints < 0) {
            throw new InvalidArgumentException('Percentages are only taken of non-negative amounts.');
        }

        $numerator = $this->minor * $basisPoints;
        $denominator = 10_000;

        $minor = match ($rounding) {
            RoundingMode::Floor => intdiv($numerator, $denominator),
            RoundingMode::Round => intdiv($numerator * 2 + $denominator, $denominator * 2),
            RoundingMode::CeilMajor => intdiv($numerator + $denominator * $this->unit() - 1, $denominator * $this->unit()) * $this->unit(),
        };

        return $this->withMinor($minor);
    }

    public function isZero(): bool
    {
        return $this->minor === 0;
    }

    public function equals(self $other): bool
    {
        return $this->currency === $other->currency && $this->minor === $other->minor;
    }

    /**
     * @return array{minor: int, currency: string, major: string, display: string}
     */
    public function jsonSerialize(): array
    {
        return [
            'minor' => $this->minor,
            'currency' => $this->currency,
            'major' => $this->toMajorString(),
            'display' => $this->format(),
        ];
    }

    private function withMinor(int $minor): self
    {
        return new self($minor, $this->currency, $this->decimals);
    }

    private function assertSameCurrency(self $other): void
    {
        if ($this->currency !== $other->currency) {
            throw new InvalidArgumentException("Cannot combine {$this->currency} with {$other->currency}.");
        }
    }
}
