<?php

declare(strict_types=1);

namespace Switon\Cli;

use Psr\Container\ContainerInterface;
use Switon\Core\Runtime;
use Switon\Di\Container;
use Switon\Sync\TerminationInterface;
use Swoole\Coroutine;
use Swoole\Event;
use Swoole\Runtime as SwooleRuntime;
use Throwable;

/**
 * Runs one closure against a booted CLI container.
 *
 * Use when a PHP test or live script needs the same env, config, and providers as `bash bin/console`, without a command class.
 *
 * Guidance: the closure's only parameter is the booted container; configure overrides inside it with `set()`.
 *
 * Road-signs:
 * - {@see Kernel::bootstrap()} without starting a command
 * - coroutine plus {@see TerminationInterface::terminate()}
 * - return the closure result, do not exit
 *
 * @see \Switon\Cli\Kernel
 * @see \Switon\Cli\Server::start()
 * @see \Switon\Sync\TerminationInterface::terminate()
 */
class Runner
{
    /**
     * Boots the application, runs `$task` inside the CLI coroutine, then returns its result.
     *
     * `$root` defaults to {@see Runtime::getRoot()}. Termination runs before the scheduler stops.
     *
     * @param callable(ContainerInterface): mixed $task
     */
    public static function run(callable $task, ?string $root = null): mixed
    {
        $container = self::boot($root);

        if (!Runtime::isCoroutineEnabled()) {
            return self::invoke($task, $container);
        }

        SwooleRuntime::enableCoroutine();

        $result = null;
        $error = null;
        Coroutine::create(static function () use ($task, $container, &$result, &$error): void {
            try {
                $result = self::invoke($task, $container);
            } catch (Throwable $exception) {
                $error = $exception;
            }
        });
        Event::wait();

        if ($error !== null) {
            throw $error;
        }

        return $result;
    }

    protected static function boot(?string $root): Container
    {
        return (new Kernel($root))->bootstrap();
    }

    /**
     * @param callable(ContainerInterface): mixed $task
     */
    protected static function invoke(callable $task, ContainerInterface $container): mixed
    {
        try {
            return $task($container);
        } finally {
            if ($container->has(TerminationInterface::class)) {
                $container->get(TerminationInterface::class)->terminate();
            }
        }
    }
}
