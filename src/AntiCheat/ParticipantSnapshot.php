<?php

declare(strict_types=1);

namespace PvpIndex\BattleValidator\AntiCheat;

use PvpIndex\BattleValidator\Elo\BattleOutcome;

/**
 * One participant's view of the battle being scanned plus the recent
 * history the scanner needs. All values are plain primitives so the
 * scanner is trivially reproducible.
 */
final readonly class ParticipantSnapshot
{
    /**
     * @param int                       $playerProfileId
     * @param BattleOutcome             $outcome             Outcome of the battle being scanned.
     * @param array<string, int|float>  $metadata            Numeric metadata for this battle.
     * @param list<RankingChange>       $recentRankingHistory Latest-first, capped to 10.
     * @param list<int>                 $recentBattleIds      Latest-first, capped to 10. Used for collusion detection.
     * @param list<array<string, int|float>> $historicalMetadata For metadata-outlier rule. Latest-first.
     */
    public function __construct(
        public int $playerProfileId,
        public BattleOutcome $outcome,
        public array $metadata,
        public array $recentRankingHistory,
        public array $recentBattleIds,
        public array $historicalMetadata,
    ) {}
}
