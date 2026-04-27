<?php

declare(strict_types=1);

namespace PvpIndex\BattleValidator\Examples;

use PvpIndex\BattleValidator\TrustScore\BattleTrustScoreCalculator;
use PvpIndex\BattleValidator\TrustScore\ServerTrustScoreCalculator;
use PvpIndex\BattleValidator\TrustScore\TrustScoreCalculatorFactory;
use PvpIndex\BattleValidator\TrustScore\TrustScoreCalculatorInterface;

/**
 * Integration patterns for production use.
 *
 * Demonstrates batch processing, threshold categorisation, and battle
 * processor integration.
 */
final class IntegrationExample
{
    public const EXCELLENT  = 'EXCELLENT';
    public const GOOD       = 'GOOD';
    public const ACCEPTABLE = 'ACCEPTABLE';
    public const CONCERNING = 'CONCERNING';
    public const POOR       = 'POOR';

    private const THRESHOLD_EXCELLENT  = 0.85;
    private const THRESHOLD_GOOD       = 0.70;
    private const THRESHOLD_ACCEPTABLE = 0.55;
    private const THRESHOLD_CONCERNING = 0.40;

    /**
     * Process multiple metric sets efficiently.
     *
     * @param array<int, array<string, float|int>> $metricSets
     * @return array<int, float>
     */
    public static function batchCalculation(array $metricSets): array
    {
        echo "=== Batch Calculation ===\n";

        $calculator = new ServerTrustScoreCalculator();
        $scores = [];

        foreach ($metricSets as $index => $metrics) {
            $scores[$index] = $calculator->calculate($metrics);
        }

        echo "Processed " . count($metricSets) . " metric sets\n";
        echo "Average score: " . number_format(array_sum($scores) / count($scores), 4) . "\n";
        echo "Min score: " . number_format(min($scores), 4) . "\n";
        echo "Max score: " . number_format(max($scores), 4) . "\n\n";

        return $scores;
    }

    /**
     * Categorise a trust score into threshold labels.
     */
    public static function categoriseScore(float $score): string
    {
        return match (true) {
            $score >= self::THRESHOLD_EXCELLENT  => self::EXCELLENT,
            $score >= self::THRESHOLD_GOOD       => self::GOOD,
            $score >= self::THRESHOLD_ACCEPTABLE => self::ACCEPTABLE,
            $score >= self::THRESHOLD_CONCERNING => self::CONCERNING,
            default                              => self::POOR,
        };
    }

    /**
     * Get threshold ranges for each category.
     *
     * @return array<string, string>
     */
    public static function getThresholdRanges(): array
    {
        return [
            self::EXCELLENT  => '0.85 - 1.00',
            self::GOOD       => '0.70 - 0.84',
            self::ACCEPTABLE => '0.55 - 0.69',
            self::CONCERNING => '0.40 - 0.54',
            self::POOR       => '0.00 - 0.39',
        ];
    }

    /**
     * Demonstration of threshold categorisation.
     */
    public static function demonstrateThresholds(): void
    {
        echo "=== Threshold Categorisation ===\n";

        $testScores = [0.95, 0.78, 0.62, 0.48, 0.32];

        echo "Threshold ranges:\n";
        foreach (self::getThresholdRanges() as $category => $range) {
            echo "  {$category}: {$range}\n";
        }

        echo "\nTest scores:\n";
        foreach ($testScores as $score) {
            $category = self::categoriseScore($score);
            echo "  " . number_format($score, 2) . " => {$category}\n";
        }
        echo "\n";
    }

    /**
     * Example of integrating with a hypothetical battle processor.
     */
    public static function battleProcessorIntegration(): void
    {
        echo "=== Battle Processor Integration ===\n";

        $serverCalculator = TrustScoreCalculatorFactory::createServer();
        $battleCalculator = TrustScoreCalculatorFactory::createBattle();

        $battleData = [
            'battle_id'     => 'b8c4a4e-1a2b-4c3d',
            'server_id'     => 42,
            'server_metrics' => [
                'uptime_ratio'             => 0.98,
                'validation_success_rate'  => 96.0,
                'player_retention'         => 73.0,
                'response_time'            => 42.0,
                'report_frequency'         => 7.0,
            ],
            'battle_metrics' => [
                'combat_log_accuracy'       => 89.0,
                'anti_cheat_detection_rate' => 78.0,
                'fair_matchmaking_score'    => 81.0,
                'player_report_resolution'  => 75.0,
                'server_age_days'           => 195.0,
            ],
        ];

        $serverScore = $serverCalculator->calculate($battleData['server_metrics']);
        $battleScore = $battleCalculator->calculate($battleData['battle_metrics']);

        $combinedScore = ($serverScore * 0.6) + ($battleScore * 0.4);

        $serverCategory = self::categoriseScore($serverScore);
        $battleCategory = self::categoriseScore($battleScore);
        $combinedCategory = self::categoriseScore($combinedScore);

        echo "Battle ID: {$battleData['battle_id']}\n";
        echo "Server ID: {$battleData['server_id']}\n";
        echo "\nScores:\n";
        echo "  Server: " . number_format($serverScore, 4) . " ({$serverCategory})\n";
        echo "  Battle: " . number_format($battleScore, 4) . " ({$battleCategory})\n";
        echo "  Combined: " . number_format($combinedScore, 4) . " ({$combinedCategory})\n";

        echo "\nProcessing decision:\n";
        if ($combinedScore >= self::THRESHOLD_GOOD) {
            echo "  ✓ Accept battle and award full ELO\n";
        } elseif ($combinedScore >= self::THRESHOLD_ACCEPTABLE) {
            echo "  ⚠ Accept battle with reduced ELO multiplier\n";
        } else {
            echo "  ✗ Reject battle or flag for manual review\n";
        }
        echo "\n";
    }

    /**
     * Analyse trust score distribution across multiple servers.
     *
     * @param array<int, array{server_id: int, metrics: array<string, float|int>}> $serverData
     */
    public static function analyseDistribution(array $serverData): void
    {
        echo "=== Trust Score Distribution ===\n";

        $calculator = new ServerTrustScoreCalculator();
        $distribution = [
            self::EXCELLENT  => 0,
            self::GOOD       => 0,
            self::ACCEPTABLE => 0,
            self::CONCERNING => 0,
            self::POOR       => 0,
        ];

        foreach ($serverData as $data) {
            $score = $calculator->calculate($data['metrics']);
            $category = self::categoriseScore($score);
            $distribution[$category]++;
        }

        $total = count($serverData);

        echo "Analysed {$total} servers:\n";
        foreach ($distribution as $category => $count) {
            $percentage = $total > 0 ? ($count / $total) * 100 : 0;
            echo "  {$category}: {$count} (" . number_format($percentage, 1) . "%)\n";
        }
        echo "\n";
    }

    /**
     * Run all integration examples.
     */
    public static function runAll(): void
    {
        $sampleMetricSets = [
            [
                'uptime_ratio'             => 0.98,
                'validation_success_rate'  => 95.0,
                'player_retention'         => 72.0,
                'response_time'            => 45.0,
                'report_frequency'         => 10.0,
            ],
            [
                'uptime_ratio'             => 0.92,
                'validation_success_rate'  => 88.0,
                'player_retention'         => 65.0,
                'response_time'            => 65.0,
                'report_frequency'         => 18.0,
            ],
            [
                'uptime_ratio'             => 0.85,
                'validation_success_rate'  => 78.0,
                'player_retention'         => 52.0,
                'response_time'            => 95.0,
                'report_frequency'         => 28.0,
            ],
        ];

        self::batchCalculation($sampleMetricSets);
        self::demonstrateThresholds();
        self::battleProcessorIntegration();
    }
}
