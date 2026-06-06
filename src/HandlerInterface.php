<?php

declare(strict_types=1);

namespace Switon\Cli;

/**
 * Contract for resolving and executing CLI commands.
 *
 * Guidance: Keep transport parsing in Router/Options; this contract only handles resolution and execution.
 *
 * Road-signs:
 * - handle(argv) returns a process exit code
 * - concrete Handler wires router, options, and invocation
 *
 * @see \Switon\Cli\Handler
 */
interface HandlerInterface
{
    /**
     * Handles argv and returns process exit code.
     *
     * @param list<string> $args
     */
    public function handle(array $args): int;
}
