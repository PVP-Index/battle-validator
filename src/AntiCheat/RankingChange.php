<?php

declare(strict_types=1);

namespace PvpIndex\BattleValidator\AntiCheat;

/**
 * A single ELO change drawn from a player's history.
 */
final readonly class RankingChange
{
    public function __construct(
        public int $oldElo,
        public int $newElo,
    ) {}

    public function delta(): int
    {
        return $this->newElo - $this->oldElo;
    }
}
