<?php

declare(strict_types=1);

use PvpIndex\BattleValidator\Elo\BattleOutcome;
use PvpIndex\BattleValidator\Elo\EloRatingService;
use PvpIndex\BattleValidator\Elo\ServerTrust;

it('returns 0 for unverified servers regardless of inputs', function (): void {
    $service = new EloRatingService();
    $server  = new ServerTrust(isVerified: false, trustScore: 100);

    expect($service->calculate(1000, 1000, BattleOutcome::WIN, $server))->toBe(0);
});

it('awards more elo for upsetting a higher-rated opponent', function (): void {
    $service = new EloRatingService();
    $server  = new ServerTrust(isVerified: true, trustScore: 100);

    $upset = $service->calculate(1000, 1400, BattleOutcome::WIN, $server);
    $even  = $service->calculate(1000, 1000, BattleOutcome::WIN, $server);

    expect($upset)->toBeGreaterThan($even);
});

it('produces symmetric magnitudes for win vs loss between equal players', function (): void {
    $service = new EloRatingService();
    $server  = new ServerTrust(isVerified: true, trustScore: 100);

    $win  = $service->calculate(1500, 1500, BattleOutcome::WIN, $server);
    $loss = $service->calculate(1500, 1500, BattleOutcome::LOSS, $server);

    expect($win)->toBe(16);
    expect($loss)->toBe(-16);
});

it('returns 0 for a draw between equal players', function (): void {
    $service = new EloRatingService();
    $server  = new ServerTrust(isVerified: true, trustScore: 100);

    expect($service->calculate(1500, 1500, BattleOutcome::DRAW, $server))->toBe(0);
});

it('scales elo deltas down on low-trust servers but never below 10%', function (): void {
    $service     = new EloRatingService();
    $highTrust   = new ServerTrust(isVerified: true, trustScore: 100);
    $lowTrust    = new ServerTrust(isVerified: true, trustScore: 10);
    $zeroTrust   = new ServerTrust(isVerified: true, trustScore: 0);

    $high = $service->calculate(1000, 1200, BattleOutcome::WIN, $highTrust);
    $low  = $service->calculate(1000, 1200, BattleOutcome::WIN, $lowTrust);
    $zero = $service->calculate(1000, 1200, BattleOutcome::WIN, $zeroTrust);

    expect($low)->toBeLessThan($high);
    expect($zero)->toBeGreaterThan(0); // floor at 10% so even 0-trust verified earns a tiny amount
});
