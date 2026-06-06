<?php

declare(strict_types=1);

namespace Switon\Cli;

use Switon\Core\Attribute\Autowired;
use Switon\Core\Attribute\Scene;
use Switon\Core\InputInterface;
use Switon\Core\Lazy;
use Switon\Kernel\Kernel as BaseKernel;
use Throwable;

/**
 * Bootstraps the application and starts the CLI server.
 *
 * Road-signs:
 * - start() <code>bootstrap()</code>
 * - run <code>ServerInterface::start()</code>
 * - commands <code>CommandDiscovery</code>
 * - invoke <code>Handler</code>
 * - exit <code>ErrorHandler</code>
 *
 * @see \Switon\Kernel\Kernel
 * @see \Switon\Cli\ServerInterface
 * @see \Switon\Cli\Handler
 */
#[Scene('cli')]
class Kernel extends BaseKernel
{
    /** @var array<string, mixed> */
    protected array $services = [
        InputInterface::class => OptionsInterface::class,
    ];

    #[Autowired] protected ServerInterface|Lazy $server;

    /**
     * Bootstraps app and starts CLI server; startup throwables are handled.
     */
    public function start(): void
    {
        try {
            $this->bootstrap();

            $this->server->start();
        } catch (Throwable $e) {
            $this->handleStartupException($e);
        }
    }
}
