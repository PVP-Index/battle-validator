<?php

declare(strict_types=1);

namespace PvpIndex\BattleValidator\TrustScore;

/**
 * Contract for trust score calculators.
 *
 * Implementations take an array of metrics, normalise them to [0, 1], apply
 * weights, and return a final trust score clamped to [0.0, 1.0].
 */
interface TrustScoreCalculatorInterface
{
    /**
     * Calculate the trust score from the provided metrics.
     *
     * @param array<string, float|int> $metrics Raw metric values
     * @return float Trust score in [0.0, 1.0]
     * @throws \InvalidArgumentException If metrics are invalid or missing
     */
    public function calculate(array $metrics): float;

    /**
     * Get the weights applied to each metric.
     *
     * @return array<string, float> Metric name => weight
     */
    public function getWeights(): array;

    /**
     * Validate that the provided metrics array contains all required metrics.
     *
     * @param array<string, float|int> $metrics
     * @throws \InvalidArgumentException If validation fails
     */
    public function validateMetrics(array $metrics): void;

    /**
     * Get the list of required metric names.
     *
     * @return array<int, string> Array of metric names
     */
    public function getRequiredMetrics(): array;
}
