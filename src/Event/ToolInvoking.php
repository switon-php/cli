<?php

declare(strict_types=1);

namespace Switon\Cli\Event;

use Switon\Cli\RouterInterface;
use Switon\Eventing\Attribute\EventLevel;
use Switon\Eventing\Severity;

/**
 * Tool method invoking event (method marked with #[Tool]).
 *
 * Log category: <code>switon.cli.tool.invoking</code>
 *
 * Guidance: Emit via Handler with raw argv; do not construct manually.
 *
 * @see \Switon\Command\Attribute\Tool
 * @see \Switon\Cli\Event\ToolInvoked
 */
#[EventLevel(Severity::INFO)]
class ToolInvoking
{
    /** @param object $command Tool command instance being invoked. */
    public function __construct(
        /** @var list<string> Raw argv (including entrypoint). */
        public array           $argv,
        public RouterInterface $router,
        public object          $command,
        public string          $method,
        public string          $action,
    ) {
    }
}
