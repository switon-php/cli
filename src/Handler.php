<?php

declare(strict_types=1);

namespace Switon\Cli;

use Psr\Container\ContainerInterface;
use Psr\EventDispatcher\EventDispatcherInterface;
use ReflectionClass;
use ReflectionMethod;
use ReflectionNamedType;
use Switon\Binding\ArgumentsBinderInterface;
use Switon\Cli\Event\CliInvoked;
use Switon\Cli\Event\CliInvoking;
use Switon\Cli\Event\ToolInvoked;
use Switon\Cli\Event\ToolInvoking;
use Switon\Cli\Exception\OptionsException;
use Switon\Command\Attribute\Tool;
use Switon\Command\CommandDiscoveryInterface;
use Switon\Core\Attribute\Autowired;
use Switon\Core\Attribute\Scene;
use Switon\Core\ConsoleInterface;
use Switon\Core\Naming;
use Switon\Core\SceneManagerInterface;
use Switon\Invoking\InvokerInterface;
use Throwable;

use function is_int;
use function method_exists;

/**
 * Executes routed CLI commands and maps results to process exit codes.
 *
 * Road-signs:
 * - parse router + options
 * - command discovery
 * - apply command scene from #[Scene]
 * - method `*Action`
 * - invoke <code>InvokerInterface</code>
 * - events CliInvoking/CliInvoked and ToolInvoking/ToolInvoked
 *
 * @see \Switon\Cli\HandlerInterface
 * @see \Switon\Cli\RouterInterface
 * @see \Switon\Command\Attribute\Tool
 * @see \Switon\Cli\Event\CliInvoking Outer lifecycle
 * @see \Switon\Cli\Event\CliInvoked Outer lifecycle
 * @see \Switon\Cli\Event\ToolInvoking Tool lifecycle
 * @see \Switon\Cli\Event\ToolInvoked Tool lifecycle
 * @see \Switon\Cli\Exception\OptionsException
 */
class Handler implements HandlerInterface
{
    #[Autowired] protected EventDispatcherInterface $eventDispatcher;
    #[Autowired] protected SceneManagerInterface $sceneManager;
    #[Autowired] protected ConsoleInterface $console;
    #[Autowired] protected ContainerInterface $container;
    #[Autowired] protected OptionsInterface $options;
    #[Autowired] protected ArgumentsBinderInterface $argumentsBinder;
    #[Autowired] protected InvokerInterface $invoker;
    #[Autowired] protected CommandDiscoveryInterface $commandDiscovery;
    #[Autowired] protected RouterInterface $router;
    #[Autowired] protected ErrorHandlerInterface $errorHandler;

    /**
     * Resolves command name to class from discovery registry.
     *
     * @return class-string|null
     */
    protected function getCommandClassName(string $command): ?string
    {
        $allCommands = $this->commandDiscovery->discover();
        return $allCommands[$command] ?? null;
    }

    /** Resolves action name to executable `*Action` method. */
    protected function getMethod(string $command, string $action): ?string
    {
        $method = $action . 'Action';
        if (method_exists($command, $method)) {
            return $method;
        }

        return null;
    }

    /**
     * Prepare short/long alias mapping after action is known.
     */
    protected function prepareOptionAliases(ReflectionMethod $rMethod): void
    {
        $scalarParameters = [];
        foreach ($rMethod->getParameters() as $parameter) {
            $type = $parameter->getType();
            if (!$type instanceof ReflectionNamedType || !$type->isBuiltin()) {
                continue;
            }
            $scalarParameters[$parameter->getName()] = $parameter;
        }

        $this->options->normalize($scalarParameters);
    }

    /**
     * Resolves command scene from class-level <code>#[Scene]</code>.
     *
     * @param class-string $commandClass
     */
    protected function resolveCommandScene(string $commandClass): ?string
    {
        $attributes = (new ReflectionClass($commandClass))->getAttributes(Scene::class);

        if ($attributes === []) {
            return null;
        }

        /** @var Scene $scene */
        $scene = $attributes[0]->newInstance();
        return $scene->name !== '' ? $scene->name : null;
    }

    /** @param list<string> $args */
    public function handle(array $args): int
    {
        $this->router->parse($args);
        $this->options->parse($this->router->getParams());

        $command = $this->router->getCommand();
        $action = Naming::camel($this->router->getAction());
        $displayAction = Naming::kebab($action);
        $cmd = $command . ':' . $displayAction;

        if (($class = $this->getCommandClassName($command)) === null) {
            return $this->console->error(
                '"{cmd}" command does not exist',
                ['cmd' => $cmd],
                ServerInterface::EXIT_COMMAND_NOT_FOUND
            );
        }

        if (($scene = $this->resolveCommandScene($class)) !== null) {
            $this->sceneManager->setScene($scene);
        }

        $instance = $this->container->get($class);

        if (($method = $this->getMethod($class, $action)) === null) {
            return $this->console->error(
                '"{cmd}" action does not exist',
                ['cmd' => $cmd],
                ServerInterface::EXIT_ACTION_NOT_FOUND
            );
        }

        $rMethod = new ReflectionMethod($instance, $method);
        $isTool = !empty($rMethod->getAttributes(Tool::class));
        $this->prepareOptionAliases($rMethod);

        // Outer layer: generic CLI invoking
        $this->eventDispatcher->dispatch(new CliInvoking($this->router, $instance, $method, $action));

        // Inner layer: only when method is marked with #[Tool]
        if ($isTool) {
            $this->eventDispatcher->dispatch(new ToolInvoking($args, $this->router, $instance, $method, $action));
        }

        try {
            $arguments = $this->argumentsBinder->resolve($rMethod);
            $return = $this->invoker->invoke([$instance, $method], $arguments);
        } catch (OptionsException $e) {
            throw $e;
        } catch (Throwable $e) {
            $this->errorHandler->handle($e);
            return ServerInterface::EXIT_COMMAND_EXECUTION_FAILED;
        }

        // Inner layer: tool completed
        if ($isTool) {
            $this->eventDispatcher->dispatch(new ToolInvoked($args, $this->router, $instance, $method, $action, $return));
        }

        // Outer layer: generic CLI completed
        $this->eventDispatcher->dispatch(new CliInvoked($this->router, $instance, $method, $action, $return));

        if ($return === null) {
            return 0;
        } elseif (is_int($return)) {
            return $return;
        } else {
            return $this->console->error($return);
        }
    }
}
