# pvpindex/battle-validator

> The open-source proof behind PvPIndex's tamper-resistant battle pipeline.

The PvPIndex API and job-processor are closed source, but every piece of code
that decides whether a battle is **authentic**, what its **ELO impact** is, and
whether it looks **suspicious** lives here, in this MIT-licensed package. No
hidden weighting, no secret formulas — if you can read PHP, you can audit
exactly how a result becomes a number on the leaderboard.

## What's inside

| Module | Class | Purpose |
|--------|-------|---------|
| Crypto | `BattleSignatureSigner` | Produces an HMAC-SHA256 signature over a canonical JSON-encoding of a battle payload. Used by the Minecraft plugin / SDKs. |
| Crypto | `BattleSignatureVerifier` | The exact code the API runs to accept or reject an incoming battle. Constant-time comparison, replay protection via the `submitted_at` window. |
| Crypto | `CanonicalPayload` | Deterministic serializer (recursive key-sort + strict JSON flags) so that signer and verifier always agree on the byte sequence. |
| ELO    | `EloRatingService` | The trust-weighted K=32 ELO formula. Pure function of `(playerElo, opponentElo, outcome, ServerTrust)`. |
| ELO    | `ServerTrust` | Tiny DTO carrying `isVerified` + `trustScore` (0–100). |
| ELO    | `BattleOutcome` | Enum: `WIN`, `LOSS`, `DRAW`. |
| AntiCheat | `AntiCheatScanner` | Stateless rule engine. Takes a `BattleSnapshot` of recent player history and returns zero or more `Flag`s. |
| AntiCheat | `BattleSnapshot`, `ParticipantSnapshot`, `RankingChange` | Plain DTOs the scanner consumes. |
| AntiCheat | `Flag` | A single anti-cheat finding (`rule`, `playerProfileIds`, `details`). |

## Why a separate package?

The PvPIndex marketing page says:

> **Tamper-resistant.** Battle payloads are signed client-side before
> submission. Any modification invalidates the signature — forged results are
> rejected automatically.

That's a load-bearing claim. We can't ask players to trust the closed-source
API on it, so the validation pipeline lives in this open repo, with tests
that double as a specification. The proprietary code only orchestrates.

## Install

```bash
composer require pvpindex/battle-validator
```

PHP 8.3+. No runtime dependencies. Framework-agnostic; we use it from
Laravel but nothing in `src/` imports Laravel.

## Quick examples

### Sign and verify a battle payload

```php
use PvpIndex\BattleValidator\Crypto\BattleSignatureSigner;
use PvpIndex\BattleValidator\Crypto\BattleSignatureVerifier;

$secret = 'the-server-api-key-shared-secret';

$payload = [
    'uuid'         => '8b8c4a4e-1a2b-4c3d-9e8f-7a6b5c4d3e2f',
    'server_id'    => 42,
    'game_mode_id' => 3,
    'started_at'   => '2026-04-26T19:14:00Z',
    'ended_at'     => '2026-04-26T19:17:31Z',
    'submitted_at' => '2026-04-26T19:17:32Z',
    'participants' => [
        ['player_profile_id' => 101, 'result' => 'win'],
        ['player_profile_id' => 102, 'result' => 'loss'],
    ],
];

$signature = (new BattleSignatureSigner($secret))->sign($payload);

$verifier = new BattleSignatureVerifier($secret);
$verifier->verify($payload, $signature); // true / throws on mismatch
```

Mutating any field — even reordering participants — changes the canonical
byte sequence and therefore the signature.

### Calculate an ELO delta

```php
use PvpIndex\BattleValidator\Elo\EloRatingService;
use PvpIndex\BattleValidator\Elo\ServerTrust;
use PvpIndex\BattleValidator\Elo\BattleOutcome;

$delta = (new EloRatingService())->calculate(
    playerElo:   1000,
    opponentElo: 1400,
    outcome:     BattleOutcome::WIN,
    server:      new ServerTrust(isVerified: true, trustScore: 100),
);
// $delta === +29
```

Unverified servers always return `0` — no ELO leakage from unaudited sources.

### Scan for anti-cheat patterns

```php
use PvpIndex\BattleValidator\AntiCheat\AntiCheatScanner;
use PvpIndex\BattleValidator\AntiCheat\BattleSnapshot;

$flags = (new AntiCheatScanner())->scan($snapshot);

foreach ($flags as $flag) {
    echo "$flag->rule: $flag->message\n";
}
```

The scanner is a pure function of its input — no DB access, no
side effects, fully unit-testable. The closed-source job processor only
loads the historical data and hands the resulting DTO to the scanner.

## Algorithm reference

### Signature canonicalization

1. Recursively sort all associative-array keys ascending.
2. JSON encode with `JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRESERVE_ZERO_FRACTION`.
3. HMAC-SHA256 with the shared secret.
4. Hex-encode the digest.

The verifier additionally:

- Rejects payloads whose `submitted_at` is more than 5 minutes in the past
  (replay protection).
- Uses `hash_equals` for constant-time comparison.

### ELO formula

```
expected         = 1 / (1 + 10 ^ ((opponentElo - playerElo) / 400))
actual           = 1.0 (win) | 0.5 (draw) | 0.0 (loss)
trustMultiplier  = clamp(trustScore / 100, 0.1, 1.0)
delta            = round((actual - expected) * 32 * trustMultiplier)
```

If `isVerified === false`, `delta = 0`.

### Anti-cheat rules

| # | Rule | Trigger |
|---|------|---------|
| 1 | `elo_ping_pong` | Same two players appear together in ≥ 3 of either player's last 10 battles. |
| 2 | `loss_farming` | The losing player has had `Δ < -25` in ≥ 3 of their last 10 ranked battles. |
| 3 | `metadata_outlier` | A numeric metadata value is > 3× the median of the player's last 10 same-mode battles (≥ 5 samples required). |

Adding or changing a rule requires a PR here — there is no place else for
that code to live.

## Tests

```bash
composer install
composer test
```

The test suite is the contract. If any of these assertions fail, the
production pipeline is broken.
