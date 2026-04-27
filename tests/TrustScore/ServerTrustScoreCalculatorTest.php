<?php

declare(strict_types=1);

use PvpIndex\BattleValidator\TrustScore\ServerTrustScoreCalculator;

it('calculates server trust score from valid metrics', function (): void {
    $calculator = new ServerTrustScoreCalculator();

    $score = $calculator->calculate([
        'uptime_ratio'             => 0.98,
        'validation_success_rate'  => 95.5,
        'player_retention'         => 72.0,
        'response_time'            => 45.0,
        'report_frequency'         => 12.0,
    ]);

    expect($score)->toBeFloat()
        ->and($score)->toBeGreaterThanOrEqual(0.0)
        ->and($score)->toBeLessThanOrEqual(1.0);
});

it('throws when required server metrics are missing', function (): void {
    $calculator = new ServerTrustScoreCalculator();

    $calculator->calculate(['uptime_ratio' => 0.5]);
})->throws(InvalidArgumentException::class, 'Missing required metric');

it('produces consistent server scores', function (): void {
    $calculator = new ServerTrustScoreCalculator();

    $metrics = [
        'uptime_ratio'             => 0.95,
        'validation_success_rate'  => 92.0,
        'player_retention'         => 68.0,
        'response_time'            => 50.0,
        'report_frequency'         => 12.0,
    ];

    $score1 = $calculator->calculate($metrics);
    $score2 = $calculator->calculate($metrics);

    expect($score1)->toBe($score2);
});
