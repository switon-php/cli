<?php

declare(strict_types=1);

namespace Switon\Cli\Event;

use Switon\Eventing\Attribute\EventLevel;
use Switon\Eventing\Severity;

/**
 * Warning-level console output event.
 *
 * Log category: <code>switon.cli.console.warning</code>
 *
 * @see \Switon\Cli\Console Typical emitter
 */
#[EventLevel(Severity::WARNING)]
class ConsoleWarning extends AbstractCommandEvent
{
}
