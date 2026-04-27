<?php

declare(strict_types=1);

namespace PvpIndex\BattleValidator\TrustScore;

/**
 * Composite calculator combining multiple trust score calculators.
 *
 * Runs each wrapped calculator against the provided metrics and returns
 * a weighted average of their scores.
 */
final class CompositeTrustScoreCalculator extends AbstractTrustScoreCalculator
{
    private const WEIGHT_SUM_TOLERANCE = 0.0001;

    /**
     * @var array<int, array{calculator: TrustScoreCalculatorInterface, weight: float}>
     */
    private readonly array $calculators;

    /**
     * @param array<int, array{calculator: TrustScoreCalculatorInterface, weight: float}> $calculatorsWithWeights
     * @throws \InvalidArgumentException If calculator weights don't sum to 1.0
     */
    public function __construct(array $calculatorsWithWeights)
    {
        $this->calculators = $calculatorsWithWeights;
        $this->validateCalculatorWeights();

        $weights = $this->buildMergedWeights();
        $requiredMetrics = $this->buildMergedRequiredMetrics();

        parent::__construct($weights, $requiredMetrics);
    }

    public function calculate(array $metrics): float
    {
        $this->validateMetrics($metrics);

        $compositeScore = 0.0;

        foreach ($this->calculators as $entry) {
            $calculator = $entry['calculator'];
            $weight = $entry['weight'];

            $subMetrics = $this->filterMetricsForCalculator($metrics, $calculator);
            $score = $calculator->calculate($subMetrics);

            $compositeScore += $score * $weight;
        }

        return $this->clampScore($compositeScore);
    }

    protected function normaliseMetrics(array $metrics): array
    {
        return $metrics;
    }

    /**
     * Filter metrics to only those required by the given calculator.
     *
     * @param array<string, float|int> $metrics
     * @return array<string, float|int>
     */
    private function filterMetricsForCalculator(
        array $metrics,
        TrustScoreCalculatorInterface $calculator
    ): array {
        $filtered = [];
        foreach ($calculator->getRequiredMetrics() as $metric) {
            if (array_key_exists($metric, $metrics)) {
                $filtered[$metric] = $metrics[$metric];
            }
        }

        return $filtered;
    }

    /**
     * Build merged weights from all wrapped calculators.
     *
     * @return array<string, float>
     */
    private function buildMergedWeights(): array
    {
        $merged = [];

        foreach ($this->calculators as $entry) {
            $calculator = $entry['calculator'];
            $calculatorWeight = $entry['weight'];

            foreach ($calculator->getWeights() as $metric => $metricWeight) {
                $merged[$metric] = ($merged[$metric] ?? 0.0) + ($metricWeight * $calculatorWeight);
            }
        }

        return $merged;
    }

    /**
     * Build merged required metrics from all wrapped calculators.
     *
     * @return array<int, string>
     */
    private function buildMergedRequiredMetrics(): array
    {
        $merged = [];

        foreach ($this->calculators as $entry) {
            $calculator = $entry['calculator'];
            $merged = array_merge($merged, $calculator->getRequiredMetrics());
        }

        return array_values(array_unique($merged));
    }

    /**
     * Validate that calculator weights sum to 1.0.
     *
     * @throws \InvalidArgumentException If weights don't sum to 1.0
     */
    private function validateCalculatorWeights(): void
    {
        $sum = 0.0;

        foreach ($this->calculators as $entry) {
            $sum += $entry['weight'];
        }

        if (abs($sum - 1.0) > self::WEIGHT_SUM_TOLERANCE) {
            throw new \InvalidArgumentException(
                sprintf(
                    'Calculator weights must sum to 1.0, got %.4f',
                    $sum
                )
            );
        }
    }
}
