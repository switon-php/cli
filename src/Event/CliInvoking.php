<?php

declare(strict_types=1);

namespace Switon\Cli\Event;

use Switon\Cli\RouterInterface;
use Switon\Eventing\Attribute\EventLevel;
use Switon\Eventing\Severity;

/**
 * CLI command method invoking event.
 *
 * Log category: <code>switon.cli.invoking</code>
 * Payload: router, command instance, method name, and public action name.
 *
 * @see \Switon\Cli\Server
 * @see \Switon\Cli\Event\CliInvoked
 */
#[EventLevel(Severity::INFO)]
class CliInvoking
{
    /** @param object $command Command instance being invoked. */
    public function __construct(
        public RouterInterface $router,
        public object          $command,
        public string          $method,
        public string          $action,
    ) {
    }
}
