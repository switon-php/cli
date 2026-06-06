<?php

declare(strict_types=1);

namespace Switon\Cli;

/**
 * Contract for running the CLI server lifecycle.
 *
 * Guidance: Use the provided exit-code constants for framework-level failure classification.
 *
 * Road-signs:
 * - Kernel→Server.start→Handler
 * - exit codes below are framework-owned
 *
 * @see \Switon\Cli\Server
 * @see \Switon\Cli\ServerInterface::start()
 * @see \Switon\Cli\Kernel
 * @see \Switon\Cli\HandlerInterface
 * @see \Switon\Cli\HandlerInterface::handle()
 * @see \Switon\Beacon\Command\ToolCommand Typical consumer
 * @see \Switon\Testing\Container Typical consumer
 */
interface ServerInterface
{
    /** Successful execution exit code */
    public const int EXIT_SUCCESS = 0;

    /**
     * Application-level exit code (e.g. command returns 1 for business error).
     * Codes 1–199 are for application use; framework uses 2xx.
     */
    public const int EXIT_GENERAL_ERROR = 1;

    /** Framework: command not found */
    public const int EXIT_COMMAND_NOT_FOUND = 251;

    /** Framework: action not found */
    public const int EXIT_ACTION_NOT_FOUND = 252;

    /** Framework: argument parsing/validation error (e.g. OptionsException from action) */
    public const int EXIT_OPTIONS_ERROR = 253;

    /** Framework: command action threw an exception (invoke failed) */
    public const int EXIT_COMMAND_EXECUTION_FAILED = 254;

    /** Framework: system/framework failure (e.g. container, before action runs) */
    public const int EXIT_SYSTEM_ERROR = 255;

    /**
     * Starts command execution and terminates process with an exit code.
     */
    public function start(): void;
}
