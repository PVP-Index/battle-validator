<?php

declare(strict_types=1);

namespace PvpIndex\BattleValidator\TrustScore;

/**
 * Trust score calculator for server reliability metrics.
 *
 * Evaluates server trustworthiness based on uptime, validation accuracy,
 * player retention, response time, and report frequency.
 */
final class ServerTrustScoreCalculator extends AbstractTrustScoreCalculator
{
    private const WEIGHTS = [
        'uptime_ratio'             => 0.25,
        'validation_success_rate'  => 0.30,
        'player_retention'         => 0.20,
        'response_time'            => 0.15,
        'report_frequency'         => 0.10,
    ];

    private const METRICS = [
        'uptime_ratio',
        'validation_success_rate',
        'player_retention',
        'response_time',
        'report_frequency',
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
            'uptime_ratio' => $this->normaliseUptimeRatio(
                (float) $metrics['uptime_ratio']
            ),
            'validation_success_rate' => $this->normaliseValidationSuccessRate(
                (float) $metrics['validation_success_rate']
            ),
            'player_retention' => $this->normalisePlayerRetention(
                (float) $metrics['player_retention']
            ),
            'response_time' => $this->normaliseResponseTime(
                (float) $metrics['response_time']
            ),
            'report_frequency' => $this->normaliseReportFrequency(
                (float) $metrics['report_frequency']
            ),
        ];
    }

    /**
     * Uptime ratio is already in [0, 1], just clamp it.
     */
    private function normaliseUptimeRatio(float $value): float
    {
        return $this->clampScore($value);
    }

    /**
     * Validation success rate as percentage, sigmoid around 90%.
     */
    private function normaliseValidationSuccessRate(float $value): float
    {
        return $this->normaliseSigmoid($value, 90.0, 0.1);
    }

    /**
     * Player retention as percentage, sigmoid around 60%.
     */
    private function normalisePlayerRetention(float $value): float
    {
        return $this->normaliseSigmoid($value, 60.0, 0.08);
    }

    /**
     * Response time in milliseconds, logarithmic then invert (lower is better).
     */
    private function normaliseResponseTime(float $value): float
    {
        if ($value <= 0) {
            return 1.0;
        }

        $logValue = $this->normaliseLogarithmic($value, 10.0);
        $normalised = $this->normaliseMinMax($logValue, 0.0, 4.0);

        return 1.0 - $normalised;
    }

    /**
     * Report frequency as count, min-max normalisation 0-50 reports.
     */
    private function normaliseReportFrequency(float $value): float
    {
        return $this->normaliseMinMax($value, 0.0, 50.0);
    }
}
