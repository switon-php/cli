<?php

declare(strict_types=1);

namespace Switon\Cli\Event;

use Switon\Eventing\Attribute\EventLevel;
use Switon\Eventing\Severity;

/**
 * Debug-level console output event.
 *
 * Log category: <code>switon.cli.console.debug</code>
 *
 * @see \Switon\Cli\Console Typical emitter
 */
#[EventLevel(Severity::DEBUG)]
class ConsoleDebug extends AbstractCommandEvent
{
}
