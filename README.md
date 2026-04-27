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
| TrustScore | `ServerTrustScoreCalculator` | Evaluates server reliability using uptime, validation success rate, player retention, response time, and report frequency. Returns normalised score [0.0, 1.0]. |
| TrustScore | `BattleTrustScoreCalculator` | Evaluates battle integrity using combat log accuracy, anti-cheat detection, matchmaking fairness, report resolution, and server age. Returns normalised score [0.0, 1.0]. |
| TrustScore | `CompositeTrustScoreCalculator` | Combines multiple calculators with custom weights for holistic trust evaluation. |
| TrustScore | `CachedTrustScoreCalculator` | PSR-16 caching decorator with xxHash keys for performance optimisation. |
| TrustScore | `TrustScoreCalculatorFactory` | Factory for creating calculator instances with default or custom weights. |
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

PHP 8.3+. Minimal runtime dependencies (PSR-16 for caching). Framework-agnostic;
we use it from Laravel but nothing in `src/` imports Laravel.

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

### Calculate trust scores

The `TrustScore` module computes the trust scores that the `EloRatingService` consumes. 
While the ELO calculator uses trust scores to weight rating changes, the trust score 
calculators determine what those scores should be based on server and battle metrics.

```php
use PvpIndex\BattleValidator\TrustScore\ServerTrustScoreCalculator;
use PvpIndex\BattleValidator\TrustScore\TrustScoreCalculatorFactory;

// Calculate server trust score (returns 0.0-1.0)
$calculator = new ServerTrustScoreCalculator();
$serverScore = $calculator->calculate([
    'uptime_ratio'             => 0.98,
    'validation_success_rate'  => 95.5,
    'player_retention'         => 72.0,
    'response_time'            => 45.0,
    'report_frequency'         => 12.0,
]);

// Convert to 0-100 range for ServerTrust DTO
$trustScoreInt = (int) round($serverScore * 100);

// Use with ELO calculation
$serverTrust = new ServerTrust(isVerified: true, trustScore: $trustScoreInt);
$eloDelta = (new EloRatingService())->calculate(1000, 1400, BattleOutcome::WIN, $serverTrust);
```

Trust scores are normalised to [0.0, 1.0]. Multiply by 100 for the `ServerTrust` DTO.

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

### Trust score calculation

Trust scores quantify server reliability and battle integrity using weighted
combinations of normalised metrics. These scores feed into the `ServerTrust` DTO
consumed by `EloRatingService`.

**Purpose**: The trust score calculators determine HOW trust scores are computed.  
**Integration**: ELO calculations use these scores to weight rating changes.

#### Server trust metrics

| Metric | Weight | Range | Normalisation |
|--------|--------|-------|---------------|
| `uptime_ratio` | 0.25 | 0.0–1.0 | Clamp to [0, 1] |
| `validation_success_rate` | 0.30 | 0–100% | Sigmoid (midpoint 90%, steepness 0.1) |
| `player_retention` | 0.20 | 0–100% | Sigmoid (midpoint 60%, steepness 0.08) |
| `response_time` | 0.15 | milliseconds | Logarithmic, inverted (lower is better) |
| `report_frequency` | 0.10 | count | Min-max (0–50 reports) |

#### Battle trust metrics

| Metric | Weight | Range | Normalisation |
|--------|--------|-------|---------------|
| `combat_log_accuracy` | 0.35 | 0–100% | Sigmoid (midpoint 85%, steepness 0.15) |
| `anti_cheat_detection_rate` | 0.25 | 0–100% | Linear [0, 100] |
| `fair_matchmaking_score` | 0.20 | 0–100% | Linear [0, 100] |
| `player_report_resolution` | 0.15 | 0–100% | Sigmoid (midpoint 70%, steepness 0.1) |
| `server_age_days` | 0.05 | days | Min-max (30–365 days) |

#### Normalisation methods

- **Min-Max**: Linear scaling `(value - min) / (max - min)`, clamped to [0, 1]
- **Logarithmic**: `log(value, base)`, useful for metrics with exponential distributions
- **Sigmoid**: `1 / (1 + exp(-steepness * (value - midpoint)))`, smooth S-curve for thresholds

#### Custom weights

```php
$calculator = TrustScoreCalculatorFactory::createServer([
    'uptime_ratio'             => 0.40,  // Emphasise uptime
    'validation_success_rate'  => 0.35,
    'player_retention'         => 0.15,
    'response_time'            => 0.05,
    'report_frequency'         => 0.05,
]);
```

Weights must sum to 1.0, otherwise `InvalidArgumentException` is thrown.

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

## Credits

Trust score calculation system designed and implemented by [GitEpildev](https://github.com/GitEpildev).
