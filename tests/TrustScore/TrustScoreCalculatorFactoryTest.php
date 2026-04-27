<?php

declare(strict_types=1);

use PvpIndex\BattleValidator\TrustScore\TrustScoreCalculatorFactory;

it('creates server calculator via factory', function (): void {
    $calculator = TrustScoreCalculatorFactory::createServer();

    $score = $calculator->calculate([
        'uptime_ratio'             => 0.98,
        'validation_success_rate'  => 95.0,
        'player_retention'         => 72.0,
        'response_time'            => 45.0,
        'report_frequency'         => 10.0,
    ]);

    expect($score)->toBeFloat()
        ->and($score)->toBeGreaterThanOrEqual(0.0)
        ->and($score)->toBeLessThanOrEqual(1.0);
});

it('creates battle calculator via factory', function (): void {
    $calculator = TrustScoreCalculatorFactory::createBattle();

    $score = $calculator->calculate([
        'combat_log_accuracy'       => 88.0,
        'anti_cheat_detection_rate' => 76.0,
        'fair_matchmaking_score'    => 82.0,
        'player_report_resolution'  => 74.0,
        'server_age_days'           => 180.0,
    ]);

    expect($score)->toBeFloat()
        ->and($score)->toBeGreaterThanOrEqual(0.0)
        ->and($score)->toBeLessThanOrEqual(1.0);
});
