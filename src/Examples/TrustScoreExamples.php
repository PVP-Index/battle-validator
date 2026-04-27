<?php

declare(strict_types=1);

namespace PvpIndex\BattleValidator\Examples;

use PvpIndex\BattleValidator\TrustScore\BattleTrustScoreCalculator;
use PvpIndex\BattleValidator\TrustScore\CachedTrustScoreCalculator;
use PvpIndex\BattleValidator\TrustScore\CompositeTrustScoreCalculator;
use PvpIndex\BattleValidator\TrustScore\ServerTrustScoreCalculator;
use PvpIndex\BattleValidator\TrustScore\TrustScoreCalculatorFactory;
use Symfony\Component\Cache\Adapter\ArrayAdapter;
use Symfony\Component\Cache\Psr16Cache;

/**
 * Demonstration examples for trust score calculations.
 *
 * Run individual functions to see the trust score system in action.
 */
final class TrustScoreExamples
{
    /**
     * Example 1: Basic server trust score calculation.
     */
    public static function basicServerScore(): void
    {
        echo "=== Basic Server Score ===\n";

        $calculator = new ServerTrustScoreCalculator();

        $metrics = [
            'uptime_ratio'             => 0.98,
            'validation_success_rate'  => 95.5,
            'player_retention'         => 72.0,
            'response_time'            => 45.0,
            'report_frequency'         => 12.0,
        ];

        $score = $calculator->calculate($metrics);

        echo "Metrics:\n";
        foreach ($metrics as $name => $value) {
            echo "  {$name}: {$value}\n";
        }
        echo "Trust Score: " . number_format($score, 4) . "\n";
        echo "Percentage: " . number_format($score * 100, 2) . "%\n\n";
    }

    /**
     * Example 2: Battle trust score calculation.
     */
    public static function battleScore(): void
    {
        echo "=== Battle Score ===\n";

        $calculator = new BattleTrustScoreCalculator();

        $metrics = [
            'combat_log_accuracy'       => 88.5,
            'anti_cheat_detection_rate' => 76.0,
            'fair_matchmaking_score'    => 82.0,
            'player_report_resolution'  => 74.5,
            'server_age_days'           => 180.0,
        ];

        $score = $calculator->calculate($metrics);

        echo "Metrics:\n";
        foreach ($metrics as $name => $value) {
            echo "  {$name}: {$value}\n";
        }
        echo "Trust Score: " . number_format($score, 4) . "\n";
        echo "Percentage: " . number_format($score * 100, 2) . "%\n\n";
    }

    /**
     * Example 3: Composite score combining server and battle calculators.
     */
    public static function compositeScore(): void
    {
        echo "=== Composite Score ===\n";

        $serverCalculator = new ServerTrustScoreCalculator();
        $battleCalculator = new BattleTrustScoreCalculator();

        $composite = new CompositeTrustScoreCalculator([
            ['calculator' => $serverCalculator, 'weight' => 0.6],
            ['calculator' => $battleCalculator, 'weight' => 0.4],
        ]);

        $metrics = [
            'uptime_ratio'              => 0.97,
            'validation_success_rate'   => 93.0,
            'player_retention'          => 68.0,
            'response_time'             => 52.0,
            'report_frequency'          => 8.0,
            'combat_log_accuracy'       => 86.0,
            'anti_cheat_detection_rate' => 72.0,
            'fair_matchmaking_score'    => 78.0,
            'player_report_resolution'  => 71.0,
            'server_age_days'           => 210.0,
        ];

        $score = $composite->calculate($metrics);

        echo "Combined server (60%) and battle (40%) metrics\n";
        echo "Trust Score: " . number_format($score, 4) . "\n";
        echo "Percentage: " . number_format($score * 100, 2) . "%\n\n";
    }

    /**
     * Example 4: Using the factory pattern.
     */
    public static function factoryUsage(): void
    {
        echo "=== Factory Usage ===\n";

        $serverCalculator = TrustScoreCalculatorFactory::createServer();
        $battleCalculator = TrustScoreCalculatorFactory::createBattle();

        $serverMetrics = [
            'uptime_ratio'             => 0.99,
            'validation_success_rate'  => 97.0,
            'player_retention'         => 75.0,
            'response_time'            => 38.0,
            'report_frequency'         => 5.0,
        ];

        $battleMetrics = [
            'combat_log_accuracy'       => 92.0,
            'anti_cheat_detection_rate' => 85.0,
            'fair_matchmaking_score'    => 88.0,
            'player_report_resolution'  => 80.0,
            'server_age_days'           => 270.0,
        ];

        echo "Server Score: " . number_format($serverCalculator->calculate($serverMetrics), 4) . "\n";
        echo "Battle Score: " . number_format($battleCalculator->calculate($battleMetrics), 4) . "\n\n";
    }

    /**
     * Example 5: Custom weights overriding defaults.
     */
    public static function customWeights(): void
    {
        echo "=== Custom Weights ===\n";

        $customWeights = [
            'uptime_ratio'             => 0.40,
            'validation_success_rate'  => 0.35,
            'player_retention'         => 0.15,
            'response_time'            => 0.05,
            'report_frequency'         => 0.05,
        ];

        $calculator = TrustScoreCalculatorFactory::createServer($customWeights);

        $metrics = [
            'uptime_ratio'             => 0.95,
            'validation_success_rate'  => 91.0,
            'player_retention'         => 65.0,
            'response_time'            => 60.0,
            'report_frequency'         => 15.0,
        ];

        $score = $calculator->calculate($metrics);

        echo "Custom weights (uptime: 40%, validation: 35%, others: 15%, 5%, 5%)\n";
        echo "Trust Score: " . number_format($score, 4) . "\n";
        echo "Percentage: " . number_format($score * 100, 2) . "%\n\n";
    }

    /**
     * Example 6: Using cache decorator with Symfony Cache.
     */
    public static function cachedScore(): void
    {
        echo "=== Cached Score ===\n";

        $calculator = new ServerTrustScoreCalculator();
        $cache = new Psr16Cache(new ArrayAdapter());
        $cachedCalculator = new CachedTrustScoreCalculator($calculator, $cache, 3600);

        $metrics = [
            'uptime_ratio'             => 0.96,
            'validation_success_rate'  => 94.0,
            'player_retention'         => 70.0,
            'response_time'            => 48.0,
            'report_frequency'         => 10.0,
        ];

        echo "First calculation (cache miss):\n";
        $start = microtime(true);
        $score1 = $cachedCalculator->calculate($metrics);
        $time1 = (microtime(true) - $start) * 1000;

        echo "Second calculation (cache hit):\n";
        $start = microtime(true);
        $score2 = $cachedCalculator->calculate($metrics);
        $time2 = (microtime(true) - $start) * 1000;

        echo "First call: " . number_format($score1, 4) . " (" . number_format($time1, 3) . " ms)\n";
        echo "Second call: " . number_format($score2, 4) . " (" . number_format($time2, 3) . " ms)\n";
        echo "Speedup: " . number_format($time1 / max($time2, 0.001), 2) . "x faster\n\n";
    }

    /**
     * Run all examples.
     */
    public static function runAll(): void
    {
        self::basicServerScore();
        self::battleScore();
        self::compositeScore();
        self::factoryUsage();
        self::customWeights();
        self::cachedScore();
    }
}
