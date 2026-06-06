<?php

declare(strict_types=1);

namespace Switon\Cli\Command;

use Psr\Container\ContainerInterface;
use ReflectionMethod;
use Switon\Cli\CommandHelpRendererInterface;
use Switon\Cli\OptionsInterface;
use Switon\Command\CommandDiscoveryInterface;
use Switon\Command\CommandInspectorInterface;
use Switon\Console\AnsiWidthCalculator;
use Switon\Console\Colors;
use Switon\Core\ConsoleInterface;
use Switon\Core\Json;
use Switon\Core\Naming;
use ReflectionClass;
use ReflectionNamedType;

use function array_flip;
use function count;
use function method_exists;
use function preg_match;
use function preg_split;
use function str_contains;
use function str_pad;
use function str_repeat;
use function substr;
use function trim;

/**
 * Renders human-readable help for one command (and optionally one action).
 *
 * @see \Switon\Cli\CommandHelpRendererInterface
 * @see \Switon\Cli\Command\HelpCommand
 */
class CommandHelpRenderer implements CommandHelpRendererInterface
{
    use AnsiWidthCalculator;

    public function __construct(
        protected ConsoleInterface          $console,
        protected ContainerInterface        $container,
        protected CommandDiscoveryInterface $commandDiscovery,
        protected CommandInspectorInterface $commandInspector,
        protected OptionsInterface          $options,
    ) {
    }

    /** {@inheritDoc} */
    public function render(string $command, string $action = ''): int
    {
        if (($definition = $this->commandDiscovery->discover()[$command] ?? null) === null) {
            return $this->console->error('{command} Command not found', ['command' => $command]);
        }

        $actionMethod = $action === '' ? '' : Naming::camel($action);
        $description = $this->commandInspector->getCommandDescription($definition);

        if (!class_exists($definition)) {
            return $this->console->error(
                'Cannot reflect command {class}',
                ['class' => $definition]
            );
        }

        $rClass = new ReflectionClass($definition);

        $actionMethods = [];
        foreach ($rClass->getMethods(ReflectionMethod::IS_PUBLIC) as $rMethod) {
            $method = $rMethod->getName();
            if (!preg_match('#^([a-z].*)Action$#', $method, $match)) {
                continue;
            }
            if ($actionMethod !== '' && $match[1] !== $actionMethod) {
                continue;
            }
            $actionMethods[] = ['name' => $match[1], 'method' => $method, 'reflection' => $rMethod];
        }

        $commandLine = $this->console->colorize($command, Colors::FC_LIGHT_YELLOW | Colors::AT_BOLD);
        if ($description !== '') {
            $commandLine .= ' ' . $description;
        }
        $this->console->writeLn($commandLine);

        if (count($actionMethods) === 1 && $action === '') {
            $actionData = $actionMethods[0];
            $fullCommandName = $command . ':' . Naming::kebab($actionData['name']);
            $rMethod = $actionData['reflection'];
            $actionDescription = $this->commandInspector->getMethodDescription($rMethod);

            $actionLine = '  ' . $this->console->colorize($fullCommandName, Colors::FC_LIGHT_CYAN);
            if ($actionDescription !== '') {
                $actionLine .= ' ' . $actionDescription;
            }
            $this->console->writeLn($actionLine);

            $helpMethod = $actionData['name'] . 'Help';
            if (method_exists($definition, $helpMethod)) {
                $instance = $this->container->get($definition);
                $instance->$helpMethod();
            } elseif (!$this->commandInspector->hasOptions($rMethod)) {
                return 0;
            } else {
                $this->renderActionHelp($rMethod, $actionData['method'], true);
            }
            return 0;
        }

        $instance = null;
        foreach ($actionMethods as $actionData) {
            $fullCommandName = $command . ':' . Naming::kebab($actionData['name']);
            $rMethod = $actionData['reflection'];
            $actionDescription = $this->commandInspector->getMethodDescription($rMethod);

            $actionLine = '  ' . $this->console->colorize($fullCommandName, Colors::FC_LIGHT_CYAN);
            if ($actionDescription !== '') {
                $actionLine .= ' ' . $actionDescription;
            }
            $this->console->writeLn($actionLine);

            $helpMethod = $actionData['name'] . 'Help';
            if (method_exists($definition, $helpMethod)) {
                if ($instance === null) {
                    $instance = $this->container->get($definition);
                }
                $instance->$helpMethod();
            } else {
                $this->renderActionHelp($rMethod, $actionData['method'], true);
            }
        }

        return 0;
    }

    /** Renders help output for one action method. */
    protected function renderActionHelp(ReflectionMethod $rMethod, string $method, bool $skipActionName = false): void
    {
        $description = $this->commandInspector->getMethodDescription($rMethod);

        $lines = [];
        $docLines = preg_split('#[\r\n]+#', $rMethod->getDocComment() ?: '') ?: [];
        foreach ($docLines as $line) {
            $lines[] = trim($line, "\t /*\r\n");
        }

        if (!$skipActionName) {
            $method_name = basename($method, 'Action');
            $colored_method_name = $this->console->colorize($method_name, Colors::FC_YELLOW);
            $method_name_width = $this->getDisplayWidth($method_name);
            $padding_width = max(18, $method_name_width);
            $padding = str_repeat(' ', $padding_width - $method_name_width);

            if ($description !== '') {
                $action_line = '    ' . $colored_method_name . $padding . $description;
            } else {
                $action_line = '    ' . $colored_method_name;
            }
            $this->console->writeLn($action_line);
        }

        $options = [];
        $defaultValues = [];
        foreach ($rMethod->getParameters() as $rParameter) {
            $name = $rParameter->getName();
            if ($rParameter->isDefaultValueAvailable()) {
                $defaultValues[$name] = $rParameter->getDefaultValue();
            }

            if (($rType = $rParameter->getType()) === null) {
                $options[$name] = '';
            } elseif ($rType instanceof ReflectionNamedType && $rType->isBuiltin()) {
                $options[$name] = '';
            }
        }

        foreach ($lines as $line) {
            if (!str_contains($line, '@param')) {
                continue;
            }

            $parts = preg_split('#\s+#', $line, 4) ?: [];
            if (count($parts) < 3 || $parts[0] !== '@param') {
                continue;
            }
            $name = substr($parts[2], 1);
            $type = $parts[1];

            if (!isset($options[$name])) {
                continue;
            }

            if (isset($defaultValues[$name])) {
                if ($type === 'bool' || $type === 'boolean') {
                    $defaultValues[$name] = $defaultValues[$name] ? 'true' : 'false';
                } elseif ($type === 'int' || $type === 'integer') {
                    $defaultValues[$name] = (int)$defaultValues[$name];
                } elseif ($type === 'float' || $type === 'double') {
                    $defaultValues[$name] = (float)$defaultValues[$name];
                } elseif ($type === 'string') {
                    $defaultValues[$name] = Json::stringify($defaultValues[$name]);
                } elseif ($type === 'array') {
                    $defaultValues[$name] = Json::stringify($defaultValues[$name]);
                }
            }

            $options[$name] = isset($parts[3]) ? trim($parts[3]) : '';
        }

        if ($options) {
            $optionParams = [];
            foreach ($rMethod->getParameters() as $rParameter) {
                $paramName = $rParameter->getName();
                if (isset($options[$paramName])) {
                    $optionParams[$paramName] = $rParameter;
                }
            }
            $shortNames = array_flip($this->options->normalize($optionParams));

            $optionColumnWidth = 0;
            $descriptionColumnWidth = 0;
            $defaultColumnWidth = 0;

            $rows = [];
            foreach ($options as $name => $value) {
                $option = '--' . $name;
                if (isset($shortNames[$name])) {
                    $option .= ', -' . $shortNames[$name];
                }

                $default = isset($defaultValues[$name]) ? (string)$defaultValues[$name] : '';
                $description = $value ?: $name;
                $rows[] = [$option, $description, $default];

                $optionColumnWidth = max($optionColumnWidth, $this->getDisplayWidth($option));
                $descriptionColumnWidth = max($descriptionColumnWidth, $this->getDisplayWidth($value ?: ''));
                $defaultColumnWidth = max($defaultColumnWidth, $this->getDisplayWidth($default));
            }

            $optionColumnWidth = max($optionColumnWidth, $this->getDisplayWidth('Option'));
            $descriptionColumnWidth = max($descriptionColumnWidth, $this->getDisplayWidth('Description'));
            $defaultColumnWidth = max($defaultColumnWidth, $this->getDisplayWidth('Default'));

            $indent = '    ';
            $spacing = 2;
            $headerLine = $indent;
            $headerLine .= $this->console->colorize(str_pad('Option', $optionColumnWidth), Colors::FC_CYAN);
            $headerLine .= str_repeat(' ', $spacing);
            $headerLine .= str_pad('Description', $descriptionColumnWidth);
            $headerLine .= str_repeat(' ', $spacing);
            $headerLine .= $this->console->colorize(str_pad('Default', $defaultColumnWidth), Colors::FC_GREEN);
            $this->console->writeLn($headerLine);

            foreach ($rows as $row) {
                [$option, $description, $default] = $row;
                $rowLine = $indent;
                $rowLine .= $this->console->colorize(str_pad($option, $optionColumnWidth), Colors::FC_CYAN);
                $rowLine .= str_repeat(' ', $spacing);
                $rowLine .= str_pad($description, $descriptionColumnWidth);
                $rowLine .= str_repeat(' ', $spacing);
                $rowLine .= $this->console->colorize(str_pad($default, $defaultColumnWidth), Colors::FC_GREEN);
                $this->console->writeLn($rowLine);
            }
        }
    }
}
