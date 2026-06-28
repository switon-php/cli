<?php

declare(strict_types=1);

namespace Switon\Cli\Event;

use Switon\Cli\HandlerInterface;
use Switon\Cli\RouterInterface;
use Switon\Core\SenderAwareInterface;
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
class CliInvoking implements SenderAwareInterface
{
    /** @param object $command Command instance being invoked. */
    public function __construct(
        public HandlerInterface $sender,
        public RouterInterface $router,
        public object          $command,
        public string          $method,
        public string          $action,
    ) {
    }

    public function getSender(): HandlerInterface
    {
        return $this->sender;
    }
}
