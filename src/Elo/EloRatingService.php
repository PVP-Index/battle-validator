<?php

declare(strict_types=1);

namespace PvpIndex\BattleValidator\Elo;

/**
 * Trust-weighted K=32 ELO formula.
 *
 * Pure function — no globals, no I/O, no time, no randomness. Given the
 * same arguments it will always return the same integer delta.
 *
 *     expected   = 1 / (1 + 10 ^ ((opponentElo - playerElo) / 400))
 *     actual     = 1.0 (win) | 0.5 (draw) | 0.0 (loss)
 *     trustMul   = clamp(trustScore / 100, 0.1, 1.0)
 *     delta      = round((actual - expected) * 32 * trustMul)
 *
 * Unverified servers always produce 0 — no leakage from unaudited sources.
 */
final class EloRatingService
{
    public const K_FACTOR        = 32;
    public const MIN_TRUST_MULTI = 0.1;
    public const MAX_TRUST_MULTI = 1.0;

    public function calculate(
        int $playerElo,
        int $opponentElo,
        BattleOutcome $outcome,
        ServerTrust $server,
    ): int {
        if (! $server->isVerified) {
            return 0;
        }

        $expected = 1 / (1 + 10 ** (($opponentElo - $playerElo) / 400));

        $actual = match ($outcome) {
            BattleOutcome::WIN  => 1.0,
            BattleOutcome::DRAW => 0.5,
            BattleOutcome::LOSS => 0.0,
        };

        $trustMultiplier = max(
            self::MIN_TRUST_MULTI,
            min(self::MAX_TRUST_MULTI, $server->trustScore / 100),
        );

        return (int) round(($actual - $expected) * self::K_FACTOR * $trustMultiplier);
    }
}
