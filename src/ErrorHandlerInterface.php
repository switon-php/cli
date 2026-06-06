<?php

declare(strict_types=1);

namespace Switon\Cli;

use Throwable;

/**
 * Contract for handling uncaught CLI exceptions.
 *
 * Guidance: Keep rendering and process termination separate; this contract only formats the throwable for CLI output.
 *
 * @see \Switon\Cli\ErrorHandler
 * @see \Switon\Cli\ErrorHandlerInterface::handle()
 * @see \Switon\Cli\Server
 */
interface ErrorHandlerInterface
{
    /**
     * Logs and renders a throwable for CLI users.
     */
    public function handle(Throwable $throwable): void;
}
