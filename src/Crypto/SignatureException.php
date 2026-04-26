<?php

declare(strict_types=1);

namespace PvpIndex\BattleValidator\Crypto;

/**
 * Thrown when a battle signature fails to verify, the payload is missing
 * required fields, or the submission is outside the allowed time window.
 *
 * Distinct subclasses let callers map to specific HTTP responses:
 *
 *   - {@see SignatureMismatchException}  → 401 Unauthorized
 *   - {@see SignatureExpiredException}   → 408 Request Timeout / 401
 *   - {@see SignatureMalformedException} → 400 Bad Request
 */
abstract class SignatureException extends \RuntimeException {}
