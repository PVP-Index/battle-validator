<?php

declare(strict_types=1);

namespace PvpIndex\BattleValidator\AntiCheat;

/**
 * Self-contained input to {@see AntiCheatScanner::scan()}.
 *
 * The closed-source job-processor is responsible only for loading data
 * out of the database and constructing this DTO; all decisions live in
 * the open scanner.
 */
final readonly class BattleSnapshot
{
    public function __construct(
        public int $battleId,
        public int $gameModeId,
        public ParticipantSnapshot $participantA,
        public ParticipantSnapshot $participantB,
    ) {}
}
