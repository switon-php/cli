<?php

declare(strict_types=1);

namespace Switon\Cli\Event;

use Switon\Cli\HandlerInterface;
use Switon\Cli\RouterInterface;
use Switon\Core\SenderAwareInterface;
use Switon\Eventing\Attribute\EventLevel;
use Switon\Eventing\Severity;

/**
 * Tool method invoked event (method marked with #[Tool]).
 *
 * Log category: <code>switon.cli.tool.invoked</code>
 *
 * @see \Switon\Command\Attribute\Tool
 * @see \Switon\Cli\Event\ToolInvoking
 */
#[EventLevel(Severity::INFO)]
class ToolInvoked implements SenderAwareInterface
{
    /** @param object $command Executed tool command instance. */
    public function __construct(
        public HandlerInterface $sender,
        /** @var list<string> Raw argv (including entrypoint). */
        public array           $argv,
        public RouterInterface $router,
        public object          $command,
        public string          $method,
        public string          $action,
        public mixed           $return,
    ) {
    }

    public function getSender(): HandlerInterface
    {
        return $this->sender;
    }
}
