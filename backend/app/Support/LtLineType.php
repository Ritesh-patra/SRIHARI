<?php

namespace App\Support;

/**
 * DTR survey LT Line Type — stored in dtr_surveys.lt_line_type.
 * Canonical values: Under Ground / Over Ground.
 * Legacy OH Line / OG Line still accepted on read/write.
 */
class LtLineType
{
    public const UNDER_GROUND = 'Under Ground';

    public const OVER_GROUND = 'Over Ground';

    /** @return list<string> */
    public static function options(): array
    {
        return [self::UNDER_GROUND, self::OVER_GROUND];
    }

    /**
     * Normalize client / legacy values to canonical labels.
     * Accepts: Under Ground / Over Ground, OH / OH Line, OG / OG Line, UG, Overhead, etc.
     * Returns null for empty input; unknown strings returned trimmed (validation may reject).
     */
    public static function normalize(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $trimmed = trim((string) $value);
        if ($trimmed === '') {
            return null;
        }

        if (in_array($trimmed, self::options(), true)) {
            return $trimmed;
        }

        $raw = strtoupper(preg_replace('/\s+/', ' ', $trimmed) ?? $trimmed);

        return match (true) {
            in_array($raw, [
                'UNDER GROUND', 'UNDERGROUND', 'UNDER-GROUND',
                'UG', 'UG LINE', 'U.G.', 'U.G. LINE',
                'OG', 'OG LINE', 'O.G.', 'O.G. LINE',
            ], true) => self::UNDER_GROUND,
            in_array($raw, [
                'OVER GROUND', 'OVERGROUND', 'OVER-GROUND',
                'OH', 'OH LINE', 'O.H.', 'O.H. LINE',
                'OVERHEAD', 'OVERHEAD LINE',
            ], true) => self::OVER_GROUND,
            default => $trimmed,
        };
    }

    /** Excel / UI display — maps legacy rows to new labels when possible. */
    public static function display(mixed $value): string
    {
        $n = self::normalize($value);

        return $n ?? '';
    }
}
