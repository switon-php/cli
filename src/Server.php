<?php

declare(strict_types=1);

namespace Switon\Cli;

use JetBrains\PhpStorm\NoReturn;
use Psr\EventDispatcher\EventDispatcherInterface;
use Switon\Cli\Event\CommandExecuted;
use Switon\Cli\Event\CommandExecuting;
use Switon\Cli\Exception\OptionsException;
use Switon\Core\Attribute\Autowired;
use Switon\Core\Runtime;
use Switon\Core\StopFlow;
use Swoole\Coroutine;
use Swoole\Event;
use Swoole\Runtime as SwooleRuntime;
use Throwable;

use function array_slice;
use function basename;
use function implode;

/**
 * Runs CLI commands and exits with framework-defined status codes.
 *
 * Road-signs:
 * - start: co? Coroutine+handle+Event::wait : handle
 * - CommandExecuting|CommandExecuted wrap Handler
 * - StopFlow→0; OptionsException|Throwable→ErrorHandler+codes
 *
 * @see \Switon\Cli\ServerInterface
 * @see \Switon\Cli\HandlerInterface
 * @see \Switon\Cli\HandlerInterface::handle()
 * @see \Switon\Cli\Event\CliInvoking
 * @see \Switon\Cli\Event\CliInvoked
 * @see \Switon\Cli\ErrorHandlerInterface
 * @see \Switon\Cli\ErrorHandlerInterface::handle()
 */
class Server implements ServerInterface
{
    #[Autowired] protected EventDispatcherInterface $eventDispatcher;
    #[Autowired] protected ErrorHandlerInterface $errorHandler;
    #[Autowired] protected HandlerInterface $handler;

    /** Exit code returned by <code>start()</code>. */
    protected int $exit_code = 0;

    /**
     * Executes one command cycle and sets <code>$exit_code</code>.
     */
    public function handle(): void
    {
        $args = implode(' ', array_slice($GLOBALS['argv'], 1));
        $command = basename($GLOBALS['argv'][0]);
        $start = microtime(true);

        $this->eventDispatcher->dispatch(new CommandExecuting($command, $args));

        try {
            $this->exit_code = $this->handler->handle($GLOBALS['argv']);
        } catch (StopFlow $exception) {
            $this->exit_code = self::EXIT_SUCCESS;
        } catch (OptionsException $exception) {
            $this->exit_code = self::EXIT_OPTIONS_ERROR;
            $this->errorHandler->handle($exception);
        } catch (Throwable $throwable) {
            $this->exit_code = self::EXIT_SYSTEM_ERROR;
            $this->errorHandler->handle($throwable);
        } finally {
            $elapsed = round(microtime(true) - $start, 3);
            $this->eventDispatcher->dispatch(new CommandExecuted($command, $args, $elapsed));
        }
    }

    /** {@inheritDoc} */
    #[NoReturn] public function start(): void
    {
        if (Runtime::isCoroutineEnabled()) {
            SwooleRuntime::enableCoroutine();

            Coroutine::create([$this, 'handle']);
            Event::wait();
        } else {
            $this->handle();
        }

        $this->terminateProcess($this->exit_code);
    }

    /** Terminate process with final exit code. */
    #[NoReturn] protected function terminateProcess(int $code): void
    {
        exit($code);
    }
}
