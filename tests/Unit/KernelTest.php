<?php

declare(strict_types=1);

namespace Switon\Cli\Tests\Unit;

use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Switon\Cli\Kernel;
use Switon\Cli\OptionsInterface;
use Switon\Cli\ServerInterface;
use Switon\Core\InputInterface;
use Throwable;
use RuntimeException;

#[AllowMockObjectsWithoutExpectations]
class KernelTest extends TestCase
{
    public function testKernelServicesBindInputToOptions(): void
    {
        $kernel = new TestableCliKernel();

        $this->assertSame([InputInterface::class => OptionsInterface::class], $kernel->exposedServices());
    }

    public function testStartBootstrapsThenStartsServer(): void
    {
        $server = $this->createMock(ServerInterface::class);
        $server->expects($this->once())->method('start');

        $kernel = new TestableCliKernel();
        $kernel->setBootstrapThrowable(null);
        $kernel->setServer($server);

        $kernel->start();

        $this->assertNull($kernel->getHandledStartupException());
    }

    public function testStartHandlesBootstrapThrowableAndDoesNotStartServer(): void
    {
        $server = $this->createMock(ServerInterface::class);
        $server->expects($this->never())->method('start');

        $ex = new RuntimeException('bootstrap failed');

        $kernel = new TestableCliKernel();
        $kernel->setBootstrapThrowable($ex);
        $kernel->setServer($server);

        $kernel->start();

        $this->assertSame($ex, $kernel->getHandledStartupException());
    }

    public function testStartHandlesServerThrowable(): void
    {
        $ex = new RuntimeException('server failed');

        /** @var ServerInterface&MockObject $server */
        $server = $this->createMock(ServerInterface::class);
        $server->expects($this->once())->method('start')->willThrowException($ex);

        $kernel = new TestableCliKernel();
        $kernel->setBootstrapThrowable(null);
        $kernel->setServer($server);

        $kernel->start();

        $this->assertSame($ex, $kernel->getHandledStartupException());
    }
}

class TestableCliKernel extends Kernel
{
    protected ?Throwable $bootstrapThrowable = null;
    protected ?Throwable $handled = null;

    public function __construct()
    {
        // Avoid parent root auto-detection (which depends on included vendor/autoload.php).
        parent::__construct(__DIR__);
    }

    public function setBootstrapThrowable(?Throwable $e): void
    {
        $this->bootstrapThrowable = $e;
    }

    public function setServer(ServerInterface $server): void
    {
        $this->server = $server;
    }

    public function getHandledStartupException(): ?Throwable
    {
        return $this->handled;
    }

    /**
     * @return array<string, mixed>
     */
    public function exposedServices(): array
    {
        return $this->services;
    }

    protected function bootstrap(): void
    {
        if ($this->bootstrapThrowable !== null) {
            throw $this->bootstrapThrowable;
        }
    }

    protected function handleStartupException(Throwable $e): void
    {
        // Override to avoid exit(1) in parent kernel.
        $this->handled = $e;
    }
}
