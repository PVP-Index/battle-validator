<?php

declare(strict_types=1);

use PvpIndex\BattleValidator\Crypto\BattleSignatureSigner;
use PvpIndex\BattleValidator\Crypto\BattleSignatureVerifier;
use PvpIndex\BattleValidator\Crypto\CanonicalPayload;
use PvpIndex\BattleValidator\Crypto\SignatureExpiredException;
use PvpIndex\BattleValidator\Crypto\SignatureMalformedException;
use PvpIndex\BattleValidator\Crypto\SignatureMismatchException;

const SECRET = 'super-secret-server-api-key';

function freshPayload(): array
{
    return [
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
}

it('canonicalizes payloads independent of associative-array key order', function (): void {
    $a = ['b' => 1, 'a' => ['z' => 2, 'y' => 3]];
    $b = ['a' => ['y' => 3, 'z' => 2], 'b' => 1];

    expect(CanonicalPayload::encode($a))->toBe(CanonicalPayload::encode($b));
});

it('preserves list (numerically-indexed) order', function (): void {
    $payload = freshPayload();
    $reordered = $payload;
    $reordered['participants'] = array_reverse($payload['participants']);

    expect(CanonicalPayload::encode($payload))
        ->not->toBe(CanonicalPayload::encode($reordered));
});

it('signs and verifies a payload round-trip', function (): void {
    $payload = freshPayload();
    $sig = (new BattleSignatureSigner(SECRET))->sign($payload);

    $verifier = new BattleSignatureVerifier(SECRET);
    $now      = new DateTimeImmutable('2026-04-26T19:18:00Z');

    expect($verifier->verify($payload, $sig, $now))->toBeTrue();
});

it('rejects a payload whose body has been mutated', function (): void {
    $payload = freshPayload();
    $sig = (new BattleSignatureSigner(SECRET))->sign($payload);

    $tampered = $payload;
    $tampered['participants'][0]['result'] = 'loss';
    $tampered['participants'][1]['result'] = 'win';

    $verifier = new BattleSignatureVerifier(SECRET);
    $now      = new DateTimeImmutable('2026-04-26T19:18:00Z');

    expect(fn () => $verifier->verify($tampered, $sig, $now))
        ->toThrow(SignatureMismatchException::class);
});

it('rejects a payload signed with the wrong secret', function (): void {
    $payload = freshPayload();
    $sig = (new BattleSignatureSigner('different-secret'))->sign($payload);

    $verifier = new BattleSignatureVerifier(SECRET);
    $now      = new DateTimeImmutable('2026-04-26T19:18:00Z');

    expect(fn () => $verifier->verify($payload, $sig, $now))
        ->toThrow(SignatureMismatchException::class);
});

it('rejects a malformed signature string', function (): void {
    $verifier = new BattleSignatureVerifier(SECRET);

    expect(fn () => $verifier->verify(freshPayload(), 'not-hex'))
        ->toThrow(SignatureMalformedException::class);
});

it('rejects a stale submission outside the skew window', function (): void {
    $payload = freshPayload();
    $sig = (new BattleSignatureSigner(SECRET))->sign($payload);

    $verifier = new BattleSignatureVerifier(SECRET, maxClockSkewSeconds: 60);
    $tooLate  = new DateTimeImmutable('2026-04-26T19:30:00Z');

    expect(fn () => $verifier->verify($payload, $sig, $tooLate))
        ->toThrow(SignatureExpiredException::class);
});

it('rejects payloads missing submitted_at', function (): void {
    $payload = freshPayload();
    unset($payload['submitted_at']);
    $sig = (new BattleSignatureSigner(SECRET))->sign($payload);

    $verifier = new BattleSignatureVerifier(SECRET);

    expect(fn () => $verifier->verify($payload, $sig))
        ->toThrow(SignatureMalformedException::class);
});

it('produces a stable hex digest of fixed length', function (): void {
    $sig = (new BattleSignatureSigner(SECRET))->sign(freshPayload());

    expect($sig)->toMatch('/^[0-9a-f]{64}$/');
});
