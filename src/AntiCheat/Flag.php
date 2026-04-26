<?php

declare(strict_types=1);

namespace PvpIndex\BattleValidator\AntiCheat;

/**
 * A single anti-cheat finding emitted by {@see AntiCheatScanner}.
 */
final readonly class Flag
{
    public function __construct(
        public string $rule,
        public string $message,
        /** @var list<int> */
        public array $playerProfileIds,
        /** @var array<string, scalar> */
        public array $details = [],
    ) {}
}
