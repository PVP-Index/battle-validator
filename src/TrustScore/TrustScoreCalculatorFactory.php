<?php

declare(strict_types=1);

namespace PvpIndex\BattleValidator\TrustScore;

/**
 * Factory for creating trust score calculator instances.
 *
 * Provides convenient static methods for instantiating calculators with
 * default or custom weights.
 */
final class TrustScoreCalculatorFactory
{
    /**
     * Create a server trust score calculator.
     *
     * @param array<string, float>|null $customWeights Optional weight overrides
     * @return ServerTrustScoreCalculator
     * @throws \InvalidArgumentException If custom weights are invalid
     */
    public static function createServer(?array $customWeights = null): ServerTrustScoreCalculator
    {
        return new ServerTrustScoreCalculator($customWeights);
    }

    /**
     * Create a battle trust score calculator.
     *
     * @param array<string, float>|null $customWeights Optional weight overrides
     * @return BattleTrustScoreCalculator
     * @throws \InvalidArgumentException If custom weights are invalid
     */
    public static function createBattle(?array $customWeights = null): BattleTrustScoreCalculator
    {
        return new BattleTrustScoreCalculator($customWeights);
    }

    /**
     * Create a composite calculator combining multiple calculators.
     *
     * @param array<int, array{calculator: TrustScoreCalculatorInterface, weight: float}> $calculatorsWithWeights
     * @return CompositeTrustScoreCalculator
     * @throws \InvalidArgumentException If calculator weights are invalid
     */
    public static function createComposite(array $calculatorsWithWeights): CompositeTrustScoreCalculator
    {
        return new CompositeTrustScoreCalculator($calculatorsWithWeights);
    }
}
