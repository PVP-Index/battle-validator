<?php

declare(strict_types=1);

namespace PvpIndex\BattleValidator\Crypto;

/**
 * Deterministic JSON serializer used by both signer and verifier.
 *
 * Two clients that disagree on byte order will fail signature verification,
 * so this routine is intentionally tiny and well-specified:
 *
 *   1. Recursively sort associative-array keys ascending. Lists keep order.
 *   2. Encode with strict JSON flags (no escaped slashes, no escaped unicode,
 *      preserve trailing zero fractions, throw on errors).
 *
 * The output is a UTF-8 byte string suitable for HMAC.
 */
final class CanonicalPayload
{
    private const JSON_FLAGS = JSON_UNESCAPED_SLASHES
        | JSON_UNESCAPED_UNICODE
        | JSON_PRESERVE_ZERO_FRACTION
        | JSON_THROW_ON_ERROR;

    public static function encode(array $payload): string
    {
        return json_encode(self::sortRecursive($payload), self::JSON_FLAGS);
    }

    /**
     * Recursively ksort associative arrays. Numerically-keyed arrays
     * (lists) keep their order so that participant ordering is preserved.
     */
    private static function sortRecursive(mixed $value): mixed
    {
        if (! is_array($value)) {
            return $value;
        }

        $isList = array_is_list($value);

        $value = array_map(self::sortRecursive(...), $value);

        if (! $isList) {
            ksort($value, SORT_STRING);
        }

        return $value;
    }
}
