<?php

declare(strict_types=1);

namespace PvpIndex\BattleValidator\Elo;

/**
 * The outcome of a single battle from one participant's perspective.
 *
 * Defined here (rather than re-using a domain enum) so the validator
 * package has zero coupling to the rest of the PvPIndex codebase.
 */
enum BattleOutcome: string
{
    case WIN  = 'win';
    case LOSS = 'loss';
    case DRAW = 'draw';
}
