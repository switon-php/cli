<?php

declare(strict_types=1);

namespace Switon\Cli;

use Psr\Log\LoggerInterface;
use Switon\Console\Colors;
use Switon\Core\Attribute\Autowired;
use Switon\Core\ConsoleInterface;
use Switon\Core\InputInterface;
use Throwable;

/**
 * Handles uncaught CLI throwables with logging and formatted console output.
 *
 * @see \Switon\Cli\ErrorHandlerInterface
 * @see \Switon\Cli\ErrorHandlerInterface::handle()
 * @see \Switon\Cli\Server
 */
class ErrorHandler implements ErrorHandlerInterface
{
    #[Autowired] protected LoggerInterface $logger;

    #[Autowired] protected ConsoleInterface $console;

    #[Autowired] protected InputInterface $input;

    /** {@inheritDoc} */
    public function handle(Throwable $throwable): void
    {
        // Log exception with message for better log readability
        $this->logger->error($throwable->getMessage(), ['exception' => $throwable]);

        // Display formatted error block
        $this->console->newLine();
        $this->console->block([
            'Error: ' . $throwable->getMessage(),
            'Type: ' . get_class($throwable),
            'File: ' . $throwable->getFile() . ':' . $throwable->getLine(),
        ], 'ERROR');

        // Show stack trace in verbose mode
        try {
            if ($this->input->has('verbose|v')) {
                $this->console->newLine();
                $this->console->writeLn(
                    $this->console->colorize('Stack trace:', Colors::FC_LIGHT_YELLOW | Colors::AT_BOLD)
                );
                $this->console->writeLn($throwable->getTraceAsString());
                $this->console->newLine();
            }
        } catch (Throwable $e) {
            // Silently ignore if verbose check fails (e.g., in tests)
        }
    }
}
