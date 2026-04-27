<?php

declare(strict_types=1);

namespace PvpIndex\BattleValidator\TrustScore;

/**
 * Base implementation for trust score calculators.
 *
 * Provides normalisation utilities and enforces the calculation pipeline:
 * validate → normalise → weighted sum → clamp to [0.0, 1.0].
 */
abstract class AbstractTrustScoreCalculator implements TrustScoreCalculatorInterface
{
    private const WEIGHT_SUM_TOLERANCE = 0.0001;

    /**
     * @param array<string, float> $weights Metric name => weight
     * @param array<int, string> $requiredMetrics List of required metric names
     * @throws \InvalidArgumentException If weights don't sum to 1.0
     */
    public function __construct(
        protected readonly array $weights,
        protected readonly array $requiredMetrics,
    ) {
        $this->validateWeightsSum();
    }

    public function calculate(array $metrics): float
    {
        $this->validateMetrics($metrics);
        $normalised = $this->normaliseMetrics($metrics);

        $score = 0.0;
        foreach ($this->weights as $metric => $weight) {
            $score += $normalised[$metric] * $weight;
        }

        return $this->clampScore($score);
    }

    public function getWeights(): array
    {
        return $this->weights;
    }

    public function validateMetrics(array $metrics): void
    {
        foreach ($this->requiredMetrics as $metric) {
            if (! array_key_exists($metric, $metrics)) {
                throw new \InvalidArgumentException(
                    "Missing required metric: {$metric}"
                );
            }

            if (! is_numeric($metrics[$metric])) {
                throw new \InvalidArgumentException(
                    "Metric '{$metric}' must be numeric, got " . gettype($metrics[$metric])
                );
            }
        }
    }

    public function getRequiredMetrics(): array
    {
        return $this->requiredMetrics;
    }

    /**
     * Normalise raw metrics to [0, 1] range.
     *
     * @param array<string, float|int> $metrics
     * @return array<string, float> Normalised metrics
     */
    abstract protected function normaliseMetrics(array $metrics): array;

    /**
     * Normalise a value using min-max scaling.
     *
     * @param float $value The value to normalise
     * @param float $min Minimum expected value
     * @param float $max Maximum expected value
     * @return float Normalised value in [0, 1]
     */
    protected function normaliseMinMax(float $value, float $min, float $max): float
    {
        if ($max <= $min) {
            return 0.0;
        }

        $normalised = ($value - $min) / ($max - $min);

        return max(0.0, min(1.0, $normalised));
    }

    /**
     * Normalise a value using logarithmic scaling.
     *
     * @param float $value The value to normalise
     * @param float $base Logarithm base (default 10)
     * @return float Normalised value
     */
    protected function normaliseLogarithmic(float $value, float $base = 10.0): float
    {
        if ($value <= 0 || $base <= 1) {
            return 0.0;
        }

        return log($value, $base);
    }

    /**
     * Normalise a value using sigmoid function.
     *
     * @param float $value The value to normalise
     * @param float $midpoint The inflection point (50% threshold)
     * @param float $steepness Controls curve steepness
     * @return float Normalised value in [0, 1]
     */
    protected function normaliseSigmoid(float $value, float $midpoint, float $steepness): float
    {
        $exponent = -$steepness * ($value - $midpoint);

        return 1.0 / (1.0 + exp($exponent));
    }

    /**
     * Clamp a score to [0.0, 1.0] range.
     *
     * @param float $score The score to clamp
     * @return float Clamped score
     */
    protected function clampScore(float $score): float
    {
        return max(0.0, min(1.0, $score));
    }

    /**
     * Validate that weights sum to 1.0 (within tolerance).
     *
     * @throws \InvalidArgumentException If weights don't sum to 1.0
     */
    private function validateWeightsSum(): void
    {
        $sum = array_sum($this->weights);

        if (abs($sum - 1.0) > self::WEIGHT_SUM_TOLERANCE) {
            throw new \InvalidArgumentException(
                sprintf(
                    'Weights must sum to 1.0, got %.4f',
                    $sum
                )
            );
        }
    }
}
