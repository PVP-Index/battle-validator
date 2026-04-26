<?php

declare(strict_types=1);

use PvpIndex\BattleValidator\AntiCheat\AntiCheatScanner;
use PvpIndex\BattleValidator\AntiCheat\BattleSnapshot;
use PvpIndex\BattleValidator\AntiCheat\ParticipantSnapshot;
use PvpIndex\BattleValidator\AntiCheat\RankingChange;
use PvpIndex\BattleValidator\Elo\BattleOutcome;

function makeParticipant(
    int $id,
    BattleOutcome $outcome = BattleOutcome::WIN,
    array $metadata = [],
    array $history = [],
    array $battleIds = [],
    array $historicalMetadata = [],
): ParticipantSnapshot {
    return new ParticipantSnapshot(
        playerProfileId:      $id,
        outcome:              $outcome,
        metadata:             $metadata,
        recentRankingHistory: $history,
        recentBattleIds:      $battleIds,
        historicalMetadata:   $historicalMetadata,
    );
}

it('returns no flags for a clean battle', function (): void {
    $snapshot = new BattleSnapshot(
        battleId:     1,
        gameModeId:   1,
        participantA: makeParticipant(101, BattleOutcome::WIN),
        participantB: makeParticipant(102, BattleOutcome::LOSS),
    );

    expect((new AntiCheatScanner())->scan($snapshot))->toBe([]);
});

it('flags loss farming when ≥ 3 of last 10 ranked battles dropped > 25 elo', function (): void {
    $heavyLosses = [
        new RankingChange(oldElo: 1500, newElo: 1470), // -30
        new RankingChange(oldElo: 1470, newElo: 1440), // -30
        new RankingChange(oldElo: 1440, newElo: 1400), // -40
        new RankingChange(oldElo: 1400, newElo: 1395), // -5  (ignored)
    ];

    $snapshot = new BattleSnapshot(
        battleId:     1,
        gameModeId:   1,
        participantA: makeParticipant(101, BattleOutcome::WIN),
        participantB: makeParticipant(102, BattleOutcome::LOSS, history: $heavyLosses),
    );

    $flags = (new AntiCheatScanner())->scan($snapshot);

    expect($flags)->toHaveCount(1);
    expect($flags[0]->rule)->toBe('loss_farming');
    expect($flags[0]->playerProfileIds)->toBe([102]);
});

it('flags elo ping-pong when the same two players share ≥ 3 recent battles', function (): void {
    $shared = [501, 502, 503, 504];

    $snapshot = new BattleSnapshot(
        battleId:     510,
        gameModeId:   1,
        participantA: makeParticipant(101, battleIds: $shared),
        participantB: makeParticipant(102, BattleOutcome::LOSS, battleIds: $shared),
    );

    $flags = (new AntiCheatScanner())->scan($snapshot);

    $rules = array_map(fn ($f) => $f->rule, $flags);
    expect($rules)->toContain('elo_ping_pong');
});

it('does not flag elo ping-pong below the threshold', function (): void {
    $snapshot = new BattleSnapshot(
        battleId:     510,
        gameModeId:   1,
        participantA: makeParticipant(101, battleIds: [501, 502]),
        participantB: makeParticipant(102, BattleOutcome::LOSS, battleIds: [501, 502]),
    );

    expect((new AntiCheatScanner())->scan($snapshot))->toBe([]);
});

it('flags numeric metadata that is more than 3× the median of last samples', function (): void {
    $history = [
        ['kills' => 5],
        ['kills' => 6],
        ['kills' => 4],
        ['kills' => 5],
        ['kills' => 7],
    ];

    $snapshot = new BattleSnapshot(
        battleId:     1,
        gameModeId:   1,
        participantA: makeParticipant(
            101,
            metadata: ['kills' => 50],
            historicalMetadata: $history,
        ),
        participantB: makeParticipant(102, BattleOutcome::LOSS),
    );

    $flags = (new AntiCheatScanner())->scan($snapshot);
    $rules = array_map(fn ($f) => $f->rule, $flags);

    expect($rules)->toContain('metadata_outlier');
});

it('skips metadata outlier detection when there are fewer than 5 samples', function (): void {
    $snapshot = new BattleSnapshot(
        battleId:     1,
        gameModeId:   1,
        participantA: makeParticipant(
            101,
            metadata: ['kills' => 99],
            historicalMetadata: [['kills' => 1], ['kills' => 1]],
        ),
        participantB: makeParticipant(102, BattleOutcome::LOSS),
    );

    expect((new AntiCheatScanner())->scan($snapshot))->toBe([]);
});
