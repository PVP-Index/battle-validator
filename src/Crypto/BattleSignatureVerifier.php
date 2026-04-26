<?php

declare(strict_types=1);

namespace PvpIndex\BattleValidator\Crypto;

/**
 * Verifies an HMAC-SHA256 signature produced by {@see BattleSignatureSigner}.
 *
 * The verifier:
 *
 *   - Recomputes the canonical encoding of `$payload` and HMACs it.
 *   - Compares the result to `$signature` in constant time.
 *   - Optionally enforces a freshness window via the payload's
 *     `submitted_at` field (ISO-8601), defaulting to 5 minutes either side.
 *
 * Any failure throws a {@see SignatureException} subclass; callers should
 * map those to HTTP 4xx responses.
 */
final readonly class BattleSignatureVerifier
{
    public const DEFAULT_MAX_CLOCK_SKEW_SECONDS = 300;

    public function __construct(
        private string $secret,
        private int $maxClockSkewSeconds = self::DEFAULT_MAX_CLOCK_SKEW_SECONDS,
    ) {
        if ($secret === '') {
            throw new \InvalidArgumentException('Verifier secret must not be empty.');
        }
        if ($maxClockSkewSeconds < 0) {
            throw new \InvalidArgumentException('maxClockSkewSeconds must be non-negative.');
        }
    }

    /**
     * Returns true if the signature is valid; throws otherwise.
     *
     * @throws SignatureMalformedException If the payload or signature is unusable.
     * @throws SignatureExpiredException   If `submitted_at` is outside the skew window.
     * @throws SignatureMismatchException  If the digests do not match.
     */
    public function verify(array $payload, string $signature, ?\DateTimeImmutable $now = null): bool
    {
        if ($signature === '' || ! ctype_xdigit($signature) || strlen($signature) !== 64) {
            throw new SignatureMalformedException('Signature must be a 64-character hex string.');
        }

        $this->assertFreshness($payload, $now ?? new \DateTimeImmutable('now'));

        $expected = hash_hmac('sha256', CanonicalPayload::encode($payload), $this->secret);

        if (! hash_equals($expected, strtolower($signature))) {
            throw new SignatureMismatchException('Battle signature does not match payload.');
        }

        return true;
    }

    private function assertFreshness(array $payload, \DateTimeImmutable $now): void
    {
        if (! array_key_exists('submitted_at', $payload)) {
            throw new SignatureMalformedException('Payload is missing required field "submitted_at".');
        }

        $raw = $payload['submitted_at'];

        if (! is_string($raw) || $raw === '') {
            throw new SignatureMalformedException('Field "submitted_at" must be a non-empty ISO-8601 string.');
        }

        try {
            $submittedAt = new \DateTimeImmutable($raw);
        } catch (\Exception $e) {
            throw new SignatureMalformedException('Field "submitted_at" is not a valid ISO-8601 timestamp.', previous: $e);
        }

        $delta = abs($now->getTimestamp() - $submittedAt->getTimestamp());

        if ($delta > $this->maxClockSkewSeconds) {
            throw new SignatureExpiredException(sprintf(
                'Battle submission is outside the allowed window (%d seconds skew, max %d).',
                $delta,
                $this->maxClockSkewSeconds,
            ));
        }
    }
}
