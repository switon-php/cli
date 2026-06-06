<?php

declare(strict_types=1);

namespace Switon\Cli\Tests\Unit;

use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\TestCase;
use Switon\Cli\Command\CommandHelpRenderer;
use Switon\Cli\CommandHelpRendererInterface;
use Switon\Cli\Console;
use Switon\Cli\ServiceProvider;
use Switon\Core\ConsoleInterface;
use Switon\Core\ContainerInterface;

#[AllowMockObjectsWithoutExpectations]
class ServiceProviderTest extends TestCase
{
    public function testRegisterBindsConsoleAndHelpRenderer(): void
    {
        $provider = new ServiceProvider();
        $container = $this->createMock(ContainerInterface::class);

        $calls = [];
        $container->expects($this->exactly(2))
            ->method('set')
            ->willReturnCallback(function (string $id, mixed $definition) use (&$calls, $container): ContainerInterface {
                $calls[] = [$id, $definition];
                return $container;
            });

        $provider->register($container);

        $this->assertSame([ConsoleInterface::class, Console::class], $calls[0]);
        $this->assertSame([CommandHelpRendererInterface::class, CommandHelpRenderer::class], $calls[1]);
    }

    public function testBootIsNoop(): void
    {
        $provider = new ServiceProvider();
        $provider->boot();
        $this->addToAssertionCount(1);
    }
}
