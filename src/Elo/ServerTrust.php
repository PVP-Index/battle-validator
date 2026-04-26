<?php

declare(strict_types=1);

namespace PvpIndex\BattleValidator\Elo;

/**
 * Trust signal carried per server. The closed-source platform decides how
 * `trustScore` is computed; the open-source ELO formula only consumes it.
 */
final readonly class ServerTrust
{
    /**
     * @param int $trustScore Integer in [0, 100]. Values are clamped at use.
     */
    public function __construct(
        public bool $isVerified,
        public int $trustScore,
    ) {}
}
