<?php

declare(strict_types=1);

namespace PvpIndex\BattleValidator\Crypto;

/**
 * Produces an HMAC-SHA256 signature over a canonical encoding of a battle
 * payload. The Minecraft plugin and any first-party SDK use this exact
 * algorithm so the signature can be verified by {@see BattleSignatureVerifier}.
 */
final readonly class BattleSignatureSigner
{
    public function __construct(private string $secret)
    {
        if ($secret === '') {
            throw new \InvalidArgumentException('Signing secret must not be empty.');
        }
    }

    /**
     * Returns the lower-case hex HMAC-SHA256 digest of the canonical payload.
     */
    public function sign(array $payload): string
    {
        $canonical = CanonicalPayload::encode($payload);

        return hash_hmac('sha256', $canonical, $this->secret);
    }
}
