<?php

declare(strict_types=1);

namespace PvpIndex\BattleValidator\TrustScore;

/**
 * Trust score calculator for battle-specific metrics.
 *
 * Evaluates battle integrity based on combat log accuracy, anti-cheat
 * detection, matchmaking fairness, report resolution, and server age.
 */
final class BattleTrustScoreCalculator extends AbstractTrustScoreCalculator
{
    private const WEIGHTS = [
        'combat_log_accuracy'       => 0.35,
        'anti_cheat_detection_rate' => 0.25,
        'fair_matchmaking_score'    => 0.20,
        'player_report_resolution'  => 0.15,
        'server_age_days'           => 0.05,
    ];

    private const METRICS = [
        'combat_log_accuracy',
        'anti_cheat_detection_rate',
        'fair_matchmaking_score',
        'player_report_resolution',
        'server_age_days',
    ];

    /**
     * @param array<string, float>|null $customWeights Optional weight overrides
     * @throws \InvalidArgumentException If custom weights are invalid
     */
    public function __construct(?array $customWeights = null)
    {
        $weights = $customWeights ?? self::WEIGHTS;

        parent::__construct($weights, self::METRICS);
    }

    protected function normaliseMetrics(array $metrics): array
    {
        return [
            'combat_log_accuracy' => $this->normaliseCombatLogAccuracy(
                (float) $metrics['combat_log_accuracy']
            ),
            'anti_cheat_detection_rate' => $this->normaliseAntiCheatDetectionRate(
                (float) $metrics['anti_cheat_detection_rate']
            ),
            'fair_matchmaking_score' => $this->normaliseFairMatchmakingScore(
                (float) $metrics['fair_matchmaking_score']
            ),
            'player_report_resolution' => $this->normalisePlayerReportResolution(
                (float) $metrics['player_report_resolution']
            ),
            'server_age_days' => $this->normaliseServerAgeDays(
                (float) $metrics['server_age_days']
            ),
        ];
    }

    /**
     * Combat log accuracy as percentage, sigmoid around 85%.
     */
    private function normaliseCombatLogAccuracy(float $value): float
    {
        return $this->normaliseSigmoid($value, 85.0, 0.15);
    }

    /**
     * Anti-cheat detection rate as percentage, linear 0-100.
     */
    private function normaliseAntiCheatDetectionRate(float $value): float
    {
        return $this->normaliseMinMax($value, 0.0, 100.0);
    }

    /**
     * Fair matchmaking score as percentage, linear 0-100.
     */
    private function normaliseFairMatchmakingScore(float $value): float
    {
        return $this->normaliseMinMax($value, 0.0, 100.0);
    }

    /**
     * Player report resolution as percentage, sigmoid around 70%.
     */
    private function normalisePlayerReportResolution(float $value): float
    {
        return $this->normaliseSigmoid($value, 70.0, 0.1);
    }

    /**
     * Server age in days, min-max 30-365 days.
     */
    private function normaliseServerAgeDays(float $value): float
    {
        return $this->normaliseMinMax($value, 30.0, 365.0);
    }
}
