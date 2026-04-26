<?php

declare(strict_types=1);

namespace PvpIndex\BattleValidator\AntiCheat;

use PvpIndex\BattleValidator\Elo\BattleOutcome;

/**
 * Pure rule engine. Given a {@see BattleSnapshot} it returns a list of
 * {@see Flag}s — one per rule that matched. No I/O, no globals, no
 * randomness.
 *
 * Rules implemented:
 *
 *   1. elo_ping_pong     — same two players appeared together in ≥ 3 of
 *                          either player's last 10 battles (collusion).
 *   2. loss_farming      — the losing player has had Δ < -25 in ≥ 3 of
 *                          their last 10 ranked battles.
 *   3. metadata_outlier  — a numeric metadata field is > 3× the median of
 *                          the player's last 10 same-mode battles
 *                          (≥ 5 samples required).
 */
final class AntiCheatScanner
{
    public const HEAVY_LOSS_THRESHOLD          = -25;
    public const HEAVY_LOSS_COUNT_THRESHOLD    = 3;
    public const PING_PONG_SHARED_THRESHOLD    = 3;
    public const METADATA_OUTLIER_FACTOR       = 3;
    public const METADATA_OUTLIER_MIN_SAMPLES  = 5;

    /**
     * @return list<Flag>
     */
    public function scan(BattleSnapshot $snapshot): array
    {
        $flags = [];

        foreach ([$snapshot->participantA, $snapshot->participantB] as $participant) {
            if ($participant->outcome === BattleOutcome::LOSS) {
                $flag = $this->detectLossFarming($participant);
                if ($flag !== null) {
                    $flags[] = $flag;
                }
            }

            $flag = $this->detectMetadataOutlier($participant);
            if ($flag !== null) {
                $flags[] = $flag;
            }
        }

        $flag = $this->detectEloPingPong($snapshot->participantA, $snapshot->participantB);
        if ($flag !== null) {
            $flags[] = $flag;
        }

        return $flags;
    }

    private function detectLossFarming(ParticipantSnapshot $participant): ?Flag
    {
        $heavyLosses = 0;
        foreach ($participant->recentRankingHistory as $change) {
            if ($change->delta() < self::HEAVY_LOSS_THRESHOLD) {
                $heavyLosses++;
            }
        }

        if ($heavyLosses < self::HEAVY_LOSS_COUNT_THRESHOLD) {
            return null;
        }

        return new Flag(
            rule:             'loss_farming',
            message:          sprintf(
                'loss_farming: player_profile_id=%d heavy_losses_in_last_10=%d',
                $participant->playerProfileId,
                $heavyLosses,
            ),
            playerProfileIds: [$participant->playerProfileId],
            details:          ['heavy_losses_in_last_10' => $heavyLosses],
        );
    }

    private function detectEloPingPong(ParticipantSnapshot $a, ParticipantSnapshot $b): ?Flag
    {
        $shared = count(array_intersect($a->recentBattleIds, $b->recentBattleIds));

        if ($shared < self::PING_PONG_SHARED_THRESHOLD) {
            return null;
        }

        return new Flag(
            rule:             'elo_ping_pong',
            message:          sprintf(
                'elo_ping_pong: player_profile_ids=%d,%d shared_battles_in_last_10=%d',
                $a->playerProfileId,
                $b->playerProfileId,
                $shared,
            ),
            playerProfileIds: [$a->playerProfileId, $b->playerProfileId],
            details:          ['shared_battles_in_last_10' => $shared],
        );
    }

    private function detectMetadataOutlier(ParticipantSnapshot $participant): ?Flag
    {
        if ($participant->metadata === [] || count($participant->historicalMetadata) < self::METADATA_OUTLIER_MIN_SAMPLES) {
            return null;
        }

        $outlierFields = [];

        foreach ($participant->metadata as $field => $currentValue) {
            if (! is_numeric($currentValue)) {
                continue;
            }

            $samples = [];
            foreach ($participant->historicalMetadata as $entry) {
                if (isset($entry[$field]) && is_numeric($entry[$field])) {
                    $samples[] = (float) $entry[$field];
                }
            }

            if (count($samples) < self::METADATA_OUTLIER_MIN_SAMPLES) {
                continue;
            }

            $median = $this->median($samples);

            if ($median > 0 && $currentValue > $median * self::METADATA_OUTLIER_FACTOR) {
                $outlierFields[] = "{$field}={$currentValue}(median={$median})";
            }
        }

        if ($outlierFields === []) {
            return null;
        }

        return new Flag(
            rule:             'metadata_outlier',
            message:          sprintf(
                'metadata_outlier: player_profile_id=%d fields=%s',
                $participant->playerProfileId,
                implode(',', $outlierFields),
            ),
            playerProfileIds: [$participant->playerProfileId],
            details:          ['fields' => implode(',', $outlierFields)],
        );
    }

    /**
     * @param list<float> $samples
     */
    private function median(array $samples): float
    {
        sort($samples);
        $count  = count($samples);
        $middle = (int) floor($count / 2);

        return $count % 2 === 0
            ? ($samples[$middle - 1] + $samples[$middle]) / 2
            : $samples[$middle];
    }
}
