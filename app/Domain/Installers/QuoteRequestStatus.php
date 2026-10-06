<?php

namespace App\Domain\Installers;

/**
 * How a quote request between a project and an installer is going (ADR-0022).
 *
 * Only SENT is written today: the request is the lead of ADR-0005, and the installer does not enter
 * the platform yet. The other states are the vocabulary of the next stage (an installer marks the
 * lead as contacted, won or lost), so the column already knows them.
 */
final class QuoteRequestStatus
{
    public const SENT = 'sent';

    public const CONTACTED = 'contacted';

    /** The installer sent their price (ADR-0026). */
    public const QUOTED = 'quoted';

    public const WON = 'won';

    public const LOST = 'lost';

    public const ALL = [self::SENT, self::CONTACTED, self::QUOTED, self::WON, self::LOST];

    public static function normalize(?string $status): string
    {
        return in_array($status, self::ALL, true) ? $status : self::SENT;
    }

    public static function label(?string $status): string
    {
        return match (self::normalize($status)) {
            self::CONTACTED => 'Te contactaron',
            self::QUOTED => 'Te cotizaron',
            self::WON => 'Negocio cerrado',
            self::LOST => 'No continuó',
            default => 'Solicitud enviada',
        };
    }

    /** An open request still waits for an answer; a closed one is history. */
    public static function isOpen(?string $status): bool
    {
        return in_array(self::normalize($status), [self::SENT, self::CONTACTED, self::QUOTED], true);
    }

    /**
     * What the installer may set (ADR-0023). SENT is the app's own: it is how a request is born, and
     * going back to "nobody answered yet" would erase an answer that did happen.
     *
     * @return array<string, string>
     */
    public static function answers(): array
    {
        // In the installer's own voice: that screen is theirs, not the client's.
        return [
            self::CONTACTED => self::installerLabel(self::CONTACTED),
            self::WON => self::installerLabel(self::WON),
            self::LOST => self::installerLabel(self::LOST),
        ];
    }

    /**
     * Sending a price answers the request on its own (ADR-0026), but only while it is still open:
     * a deal already won or lost does not go back to waiting because someone edited the figure.
     */
    public static function afterQuoting(?string $status): string
    {
        return self::isOpen($status) ? self::QUOTED : self::normalize($status);
    }

    /**
     * A won deal without its value is the one number the commission of ADR-0005 will need, lost.
     */
    public static function requiresContractValue(?string $status): bool
    {
        return self::normalize($status) === self::WON;
    }

    /** How the installer reads it, in the first person: it is their inbox. */
    public static function installerLabel(?string $status): string
    {
        return match (self::normalize($status)) {
            self::CONTACTED => 'Ya lo contacté',
            self::QUOTED => 'Ya le coticé',
            self::WON => 'Negocio cerrado',
            self::LOST => 'No continuó',
            default => 'Sin responder',
        };
    }
}
