<?php

declare(strict_types=1);

namespace Switon\Cli\Event;

use Switon\Eventing\Attribute\EventLevel;
use Switon\Eventing\Severity;

/**
 * Error-level console output event.
 *
 * Log category: <code>switon.cli.console.error</code>
 *
 * @see \Switon\Cli\Console Typical emitter
 */
#[EventLevel(Severity::ERROR)]
class ConsoleError extends AbstractCommandEvent
{
}
