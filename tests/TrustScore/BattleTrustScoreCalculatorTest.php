<?php

declare(strict_types=1);

use PvpIndex\BattleValidator\TrustScore\BattleTrustScoreCalculator;

it('calculates battle trust score from valid metrics', function (): void {
    $calculator = new BattleTrustScoreCalculator();

    $score = $calculator->calculate([
        'combat_log_accuracy'       => 88.5,
        'anti_cheat_detection_rate' => 76.0,
        'fair_matchmaking_score'    => 82.0,
        'player_report_resolution'  => 74.5,
        'server_age_days'           => 180.0,
    ]);

    expect($score)->toBeFloat()
        ->and($score)->toBeGreaterThanOrEqual(0.0)
        ->and($score)->toBeLessThanOrEqual(1.0);
});

it('throws when required battle metrics are missing', function (): void {
    $calculator = new BattleTrustScoreCalculator();

    $calculator->calculate(['combat_log_accuracy' => 85.0]);
})->throws(InvalidArgumentException::class, 'Missing required metric');

it('produces consistent battle scores', function (): void {
    $calculator = new BattleTrustScoreCalculator();

    $metrics = [
        'combat_log_accuracy'       => 85.0,
        'anti_cheat_detection_rate' => 75.0,
        'fair_matchmaking_score'    => 80.0,
        'player_report_resolution'  => 70.0,
        'server_age_days'           => 180.0,
    ];

    $score1 = $calculator->calculate($metrics);
    $score2 = $calculator->calculate($metrics);

    expect($score1)->toBe($score2);
});
