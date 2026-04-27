<?php

declare(strict_types=1);

namespace PvpIndex\BattleValidator\Elo;

use PvpIndex\BattleValidator\TrustScore\TrustScoreCalculatorFactory;

/**
 * Trust signal carried per server.
 *
 * Trust scores can be computed via the TrustScore module or provided directly.
 * The ELO formula consumes this DTO to weight rating changes.
 */
class ServerTrust
{
    /**
     * @param int $trustScore Integer in [0, 100]. Values are clamped at use.
     */
    public function __construct(
        public bool $isVerified,
        public int $trustScore,
    ) {}

    /**
     * Calculate server trust from metrics using the open-source calculator.
     *
     * @param array<string, float|int> $metrics Server metrics
     * @return self
     */
    public static function calculate(array $metrics, bool $isVerified = true): self
    {
        $calculator = TrustScoreCalculatorFactory::createServer();
        $score = $calculator->calculate($metrics);
        
        return new self(
            isVerified: $isVerified,
            trustScore: (int) round($score * 100),
        );
    }
}
