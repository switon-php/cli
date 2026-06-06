<?php

declare(strict_types=1);

namespace Switon\Cli\Event;

use Switon\Cli\RouterInterface;
use Switon\Eventing\Attribute\EventLevel;
use Switon\Eventing\Severity;

/**
 * CLI command method invoked event.
 *
 * Log category: <code>switon.cli.invoked</code>
 * Payload: router, command instance, method name, public action name, and return value.
 *
 * @see \Switon\Cli\Server
 * @see \Switon\Cli\Event\CliInvoking
 */
#[EventLevel(Severity::INFO)]
class CliInvoked
{
    /** @param object $command Executed command instance. */
    public function __construct(
        public RouterInterface $router,
        public object          $command,
        public string          $method,
        public string          $action,
        public mixed           $return,
    ) {
    }
}
