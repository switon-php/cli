<?php

declare(strict_types=1);

namespace Switon\Cli\Event;

use Switon\Eventing\Attribute\EventLevel;
use Switon\Eventing\Severity;

/**
 * Reserved event for duplicate CLI command name (kebab) detection.
 *
 * Command discovery merges <code>extra.switon.commands</code> via {@see \Switon\ComposerExtra\ComposerExtraInterface::collect()};
 * same-name collisions keep the last class in merge order without emitting this event.
 *
 * Log category: <code>switon.cli.command.duplicate</code>
 */
#[EventLevel(Severity::INFO)]
class CommandDuplicate extends AbstractCommandEvent
{
}
