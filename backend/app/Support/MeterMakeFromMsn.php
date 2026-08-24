<?php

namespace App\Support;

/**
 * Consumer New MSN prefix → meter make (case-insensitive, first 2 letters).
 *
 * Known: PS → (L&T) Schneider, PH → HPL, PL → (Linkwell) Visiontek.
 * Anything else → client shows Other + free-text (saved as-is to meter_make).
 */
class MeterMakeFromMsn
{
    public const MAKE_LT_SCHNEIDER = '(L&T) Schneider';

    public const MAKE_HPL = 'HPL';

    public const MAKE_LINKWELL_VISIONTEK = '(Linkwell) Visiontek';

    public const MAKE_OTHER = 'Other';

    /** @return list<string> Canonical auto-detect makes (not including Other). */
    public static function knownMakes(): array
    {
        return [
            self::MAKE_LT_SCHNEIDER,
            self::MAKE_HPL,
            self::MAKE_LINKWELL_VISIONTEK,
        ];
    }

    /**
     * Client option list: known makes + Other (free-text), plus legacy aliases still accepted.
     *
     * @return list<string>
     */
    public static function allowedMakes(): array
    {
        return [
            self::MAKE_LT_SCHNEIDER,
            self::MAKE_HPL,
            self::MAKE_LINKWELL_VISIONTEK,
            self::MAKE_OTHER,
            // Legacy / DTR-adjacent values still accepted on consumer API.
            'L&T Schneider',
            'LNT',
            'Visiontek',
            'L&T',
        ];
    }

    public static function isKnownMake(?string $make): bool
    {
        $make = trim((string) $make);
        if ($make === '') {
            return false;
        }

        foreach (self::knownMakes() as $known) {
            if (strcasecmp($make, $known) === 0) {
                return true;
            }
        }

        return false;
    }

    /**
     * Accept known makes or any free-text custom make (Other).
     * Rejects empty / literal "Other".
     */
    public static function isAcceptableMake(?string $make): bool
    {
        $make = trim((string) $make);
        if ($make === '' || strcasecmp($make, self::MAKE_OTHER) === 0) {
            return false;
        }

        return mb_strlen($make) <= 80;
    }

    public static function fromMsn(?string $msn): ?string
    {
        $msn = strtoupper(trim((string) $msn));
        if (strlen($msn) < 2) {
            return null;
        }

        $prefix = substr($msn, 0, 2);

        return match ($prefix) {
            'PS' => self::MAKE_LT_SCHNEIDER,
            'PH' => self::MAKE_HPL,
            'PL' => self::MAKE_LINKWELL_VISIONTEK,
            default => null,
        };
    }
}
