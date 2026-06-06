<?php

declare(strict_types=1);

namespace Switon\Cli\Event;

use Switon\Eventing\Attribute\EventLevel;
use Switon\Eventing\Severity;

/**
 * Info-level console output event.
 *
 * Log category: <code>switon.cli.console.info</code>
 *
 * @see \Switon\Cli\Console Typical emitter
 */
#[EventLevel(Severity::INFO)]
class ConsoleInfo extends AbstractCommandEvent
{
}
