<?php

declare(strict_types=1);

namespace PvpIndex\BattleValidator\TrustScore;

use Psr\SimpleCache\CacheInterface;

/**
 * Caching decorator for trust score calculators.
 *
 * Wraps any TrustScoreCalculatorInterface and caches calculation results
 * using PSR-16 SimpleCache with xxHash-based cache keys.
 */
final class CachedTrustScoreCalculator implements TrustScoreCalculatorInterface
{
    private const CACHE_KEY_PREFIX = 'trust_score_';

    /**
     * @param TrustScoreCalculatorInterface $calculator The wrapped calculator
     * @param CacheInterface $cache PSR-16 cache implementation
     * @param int $ttl Cache time-to-live in seconds (default 3600)
     */
    public function __construct(
        private readonly TrustScoreCalculatorInterface $calculator,
        private readonly CacheInterface $cache,
        private readonly int $ttl = 3600,
    ) {}

    public function calculate(array $metrics): float
    {
        $cacheKey = $this->generateCacheKey($metrics);

        $cached = $this->cache->get($cacheKey);
        if (is_float($cached)) {
            return $cached;
        }

        $score = $this->calculator->calculate($metrics);

        $this->cache->set($cacheKey, $score, $this->ttl);

        return $score;
    }

    public function getWeights(): array
    {
        return $this->calculator->getWeights();
    }

    public function validateMetrics(array $metrics): void
    {
        $this->calculator->validateMetrics($metrics);
    }

    public function getRequiredMetrics(): array
    {
        return $this->calculator->getRequiredMetrics();
    }

    /**
     * Generate a cache key using xxHash of serialised metrics.
     *
     * @param array<string, float|int> $metrics
     * @return string
     */
    private function generateCacheKey(array $metrics): string
    {
        ksort($metrics);
        $serialised = json_encode($metrics, JSON_THROW_ON_ERROR);

        $hash = hash('xxh128', $serialised);

        return self::CACHE_KEY_PREFIX . $hash;
    }
}
