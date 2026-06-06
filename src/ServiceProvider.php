<?php

declare(strict_types=1);

namespace Switon\Cli;

use Switon\Cli\Command\CommandHelpRenderer;
use Switon\Core\ConsoleInterface;
use Switon\Core\ContainerInterface;
use Switon\Core\ServiceProviderInterface;

/**
 * Integrates CLI services during application startup.
 *
 * Registers the console implementation and the command help renderer.
 *
 * @see \Switon\Core\ServiceProviderInterface
 * @see \Switon\Cli\Console
 * @see \Switon\Cli\OptionsInterface
 * @see \Switon\Cli\Kernel
 */
class ServiceProvider implements ServiceProviderInterface
{
    /** {@inheritDoc} */
    public function register(ContainerInterface $container): void
    {
        $container->set(ConsoleInterface::class, Console::class);
        $container->set(CommandHelpRendererInterface::class, CommandHelpRenderer::class);
    }

    /** {@inheritDoc} */
    public function boot(): void
    {
    }
}
