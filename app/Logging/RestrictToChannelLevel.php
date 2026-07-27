<?php

declare(strict_types=1);

namespace App\Logging;

use Illuminate\Log\Logger;
use Monolog\Handler\FilterHandler;

/**
 * A log "tap" that narrows a channel to *only* the level it was configured
 * with, instead of Laravel's default "this level and everything above it".
 *
 * Used by the info/warning channels so each severity lands in its own file
 * without leaking into the others (e.g. a warning never shows up in info.log).
 */
class RestrictToChannelLevel
{
    public function __invoke(Logger $logger): void
    {
        $monolog = $logger->getLogger();

        $restricted = array_map(
            // Min and max set to the handler's own level => that level only.
            fn ($handler) => new FilterHandler($handler, $handler->getLevel(), $handler->getLevel()),
            $monolog->getHandlers(),
        );

        $monolog->setHandlers($restricted);
    }
}
