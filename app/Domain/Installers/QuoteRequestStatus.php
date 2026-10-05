<?php

namespace App\Domain\Installers;

/**
 * How a quote request between a project and an installer is going (ADR-0021).
 *
 * Only SENT is written today: the request is the lead of ADR-0005, and the installer does not enter
 * the platform yet. The other states are the vocabulary of the next stage (an installer marks the
 * lead as contacted, won or lost), so the column already knows them.
 */
final class QuoteRequestStatus
{
    public const SENT = 'sent';

    public const CONTACTED = 'contacted';

    public const WON = 'won';

    public const LOST = 'lost';

    public const ALL = [self::SENT, self::CONTACTED, self::WON, self::LOST];

    public static function normalize(?string $status): string
    {
        return in_array($status, self::ALL, true) ? $status : self::SENT;
    }

    public static function label(?string $status): string
    {
        return match (self::normalize($status)) {
            self::CONTACTED => 'Te contactaron',
            self::WON => 'Negocio cerrado',
            self::LOST => 'No continuó',
            default => 'Solicitud enviada',
        };
    }

    /** An open request still waits for an answer; a closed one is history. */
    public static function isOpen(?string $status): bool
    {
        return in_array(self::normalize($status), [self::SENT, self::CONTACTED], true);
    }
}
