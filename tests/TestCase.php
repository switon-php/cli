<?php

declare(strict_types=1);

namespace Switon\Cli\Tests;

use PHPUnit\Framework\TestCase as BaseTestCase;
use Psr\EventDispatcher\EventDispatcherInterface as PsrEventDispatcherInterface;
use Psr\Log\LoggerInterface;
use Switon\Cli\OptionsInterface;
use Switon\ComposerExtra\ComposerExtraInterface;
use Switon\Core\App;
use Switon\Core\ConsoleInterface;
use Switon\Core\ContextManagerInterface;
use Switon\Core\InputInterface;
use Switon\Core\InjectorInterface;
use Switon\Di\Container as DiContainer;
use Switon\Di\ServiceProvider as DiServiceProvider;
use Switon\Eventing\EventDispatcher as RealEventDispatcher;
use Switon\Eventing\EventDispatcherInterface;
use Switon\Testing\ComposerExtra;
use Switon\Testing\EventDispatcher as TestEventDispatcher;
use Switon\Testing\InMemoryContextManager;
use Switon\Testing\Mock\MockConsole;
use Switon\Testing\MockLogger;

abstract class TestCase extends BaseTestCase
{
    protected DiContainer $container;
    protected InjectorInterface $injector;

    protected function setUp(): void
    {
        parent::setUp();

        $this->container = new DiContainer();
        $diProvider = new DiServiceProvider();
        $diProvider->register($this->container);
        $diProvider->boot();

        App::setContainer($this->container);
        $this->container->set(ContextManagerInterface::class, InMemoryContextManager::class);
        $this->container->set(ComposerExtraInterface::class, new ComposerExtra());

        $realDispatcher = $this->container->make(RealEventDispatcher::class);
        $eventDispatcher = new TestEventDispatcher($realDispatcher);
        $eventDispatcher->setDispatchEvents(false);
        $this->container->set(EventDispatcherInterface::class, $eventDispatcher);
        $this->container->set(PsrEventDispatcherInterface::class, $eventDispatcher);
        $this->container->set(LoggerInterface::class, new MockLogger());

        $mockConsole = new MockConsole();
        $this->container->set(ConsoleInterface::class, $mockConsole);
        $this->container->set(MockConsole::class, $mockConsole);

        $this->injector = $this->container->get(InjectorInterface::class);
        $this->setUpContainer();
        $this->injector->inject($this);
    }

    protected function setUpContainer(): void
    {
        $this->container->set(InputInterface::class, OptionsInterface::class);
    }

    protected function make(string $name, array $parameters = []): mixed
    {
        return $this->container->make($name, $parameters);
    }
}
