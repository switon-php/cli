<?php

declare(strict_types=1);

namespace Switon\Cli\Command;

use Psr\Container\ContainerInterface;
use Switon\Command\Attribute\Hidden;
use Switon\Command\CommandDiscoveryInterface;
use Switon\Command\CommandInspectorInterface;
use Switon\Console\AnsiWidthCalculator;
use Switon\Console\Colors;
use Switon\Core\AppInterface;
use Switon\Core\Attribute\Autowired;
use Switon\Core\ConsoleInterface;
use Switon\Core\Json;
use Switon\Core\Naming;
use Switon\Kernel\VersionInterface;

use function class_exists;
use function is_string;
use function ksort;
use function sprintf;
use function str_repeat;
use function str_starts_with;
use function trim;

/**
 * List available commands and detailed action help
 *
 * Guidance: Use JSON mode for machine-readable discovery; use the default mode for human command browsing.
 *
 * @see \Switon\Command\CommandDiscoveryInterface Discovery boundary
 * @see \Switon\Command\CommandInspectorInterface Reflection boundary
 * @see \Switon\Core\ConsoleInterface Output boundary
 * @see \Switon\Command\Attribute\Tool AI tool entrypoint
 */
#[Hidden]
class ListCommand
{
    use AnsiWidthCalculator;

    #[Autowired] protected ConsoleInterface $console;
    #[Autowired] protected AppInterface $app;
    #[Autowired] protected VersionInterface $frameworkVersion;
    #[Autowired] protected ContainerInterface $container;
    #[Autowired] protected CommandDiscoveryInterface $commandDiscovery;
    #[Autowired] protected CommandInspectorInterface $commandInspector;

    /**
     * List registered commands
     *
     * @param bool $all Include hidden commands when true
     * @param bool $json Output machine-readable JSON (for AI/scripts)
     */
    public function defaultAction(bool $all = false, bool $json = false): int
    {
        $builtin_commands = [];
        $app_commands = [];
        foreach ($this->commandDiscovery->discover() as $name => $definition) {
            if (is_string($definition)) {
                if (str_starts_with($definition, 'App\\')) {
                    $app_commands[$name] = $definition;
                } else {
                    $builtin_commands[$name] = $definition;
                }
            }
        }

        if ($json) {
            $this->console->writeLn($this->buildCommandsJson($builtin_commands, $app_commands, $all));
            return 0;
        }

        $this->console->writeLn(
            sprintf(
                '%s %s (framework: %s, id: %s, env: %s, debug: %s)',
                $this->console->colorize(
                    trim($this->app->name()) !== '' ? $this->app->name() : $this->app->id(),
                    Colors::FC_LIGHT_GREEN | Colors::AT_BOLD
                ),
                $this->console->colorize($this->app->version(), Colors::FC_LIGHT_GREEN | Colors::AT_BOLD),
                $this->console->colorize($this->frameworkVersion->version(), Colors::FC_LIGHT_GREEN | Colors::AT_BOLD),
                $this->console->colorize($this->app->id(), Colors::FC_LIGHT_YELLOW | Colors::AT_BOLD),
                $this->console->colorize($this->app->env(), Colors::FC_LIGHT_YELLOW | Colors::AT_BOLD),
                $this->console->colorize(
                    $this->app->isDebug() ? 'true' : 'false',
                    Colors::FC_LIGHT_YELLOW | Colors::AT_BOLD
                )
            )
        );
        $this->console->writeLn('Tip: run <command> --help for details, or list --all to include hidden commands.');
        $this->console->writeLn();

        // Render builtin commands
        if (!empty($builtin_commands)) {
            $this->console->writeLn(
                $this->console->colorize('Available commands:', Colors::FC_LIGHT_GREEN | Colors::AT_BOLD)
            );
            $this->renderCommandsTable($builtin_commands, $all);
        }

        // Render application commands
        if (!empty($app_commands)) {
            if (!empty($builtin_commands)) {
                $this->console->writeLn();
                $title = 'Application commands:';
            } else {
                $title = 'Available commands:';
            }
            $this->console->writeLn(
                $this->console->colorize($title, Colors::FC_LIGHT_GREEN | Colors::AT_BOLD)
            );
            $this->renderCommandsTable($app_commands, $all);
        }

        return 0;
    }

    /**
     * Builds machine-readable JSON for all commands (builtin + application).
     *
     * @param array<string, string> $builtin
     * @param array<string, string> $app
     *
     * @return string JSON string
     */
    protected function buildCommandsJson(array $builtin, array $app, bool $all): string
    {
        $commands = [];
        foreach (['builtin' => $builtin, 'application' => $app] as $kind => $definitions) {
            ksort($definitions);
            foreach ($definitions as $name => $definition) {
                if (!class_exists($definition)) {
                    continue;
                }
                if (!$all && $this->commandInspector->isHiddenCommand($definition)) {
                    continue;
                }
                if ($this->shouldOmitFromList($definition, $all)) {
                    continue;
                }
                $description = trim($this->commandInspector->getCommandDescription($definition));
                $actionDescriptions = $this->commandInspector->getActions($definition, $all);
                $actions = [];
                foreach ($actionDescriptions as $action => $actionDesc) {
                    $actionKebab = $action === 'default' ? $action : Naming::kebab($action);
                    $invocation = $actionKebab === 'default' ? $name : ($name . ':' . $actionKebab);
                    $actionEntry = [
                        'name' => $actionKebab,
                        'invocation' => $invocation,
                        'description' => $actionDesc,
                    ];
                    $aiDoc = $this->commandInspector->getActionAiDoc($definition, $action);
                    if ($aiDoc !== null) {
                        $actionEntry['ai_doc'] = $aiDoc;
                    }
                    $actions[] = $actionEntry;
                }
                $commands[] = [
                    'name' => $name,
                    'kind' => $kind,
                    'class' => $definition,
                    'description' => $description,
                    'actions' => $actions,
                ];
            }
        }
        $payload = [
            'framework_version' => $this->frameworkVersion->version(),
            'app' => [
                'id' => $this->app->id(),
                'name' => $this->app->name(),
                'version' => $this->app->version(),
                'env' => $this->app->env(),
                'debug' => $this->app->isDebug(),
            ],
            'commands' => $commands,
        ];

        return Json::stringify($payload);
    }

    /**
     * Renders command list table.
     *
     * @param array<string, string> $commands
     */
    protected function renderCommandsTable(array $commands, bool $all): void
    {
        ksort($commands);

        // Prepare table data
        $tableData = [];
        $commandNameWidth = 0;
        $subcommandNameWidth = 0;

        foreach ($commands as $name => $definition) {
            // Skip non-existent command classes
            if (!class_exists($definition)) {
                // Use error output (red) for better visibility
                $this->console->writeLn(
                    $this->console->colorize('✗ ', Colors::FC_LIGHT_RED | Colors::AT_BOLD) .
                    'Skipping command "' .
                    $this->console->colorize($name, Colors::FC_LIGHT_YELLOW) .
                    '": class ' .
                    $this->console->colorize($definition, Colors::FC_LIGHT_RED) .
                    ' not found'
                );
                continue;
            }

            if (!$all && $this->commandInspector->isHiddenCommand($definition)) {
                continue;
            }

            if ($this->shouldOmitFromList($definition, $all)) {
                continue;
            }

            $description = trim($this->commandInspector->getCommandDescription($definition));
            $actions = $this->commandInspector->getActions($definition, $all);

            // Calculate widths
            $commandNameWidth = max($commandNameWidth, $this->getDisplayWidth($name));

            if (empty($actions)) {
                // Command without subcommands
                $tableData[] = [
                    'type' => 'command',
                    'command' => $name,
                    'subcommand' => '',
                    'description' => $description,
                    'hasSubcommands' => false,
                ];
            } else {
                // Command with subcommands - don't show description for controller-level command
                $tableData[] = [
                    'type' => 'command',
                    'command' => $name,
                    'subcommand' => '',
                    'description' => '',
                    'hasSubcommands' => true,
                ];

                // Add subcommands; for default action show command name only (no ":default")
                foreach ($actions as $action => $actionDescription) {
                    $actionKebab = $action === 'default' ? $action : Naming::kebab($action);
                    $subcommandFullName = $actionKebab === 'default' ? $name : ($name . ':' . $actionKebab);
                    $subcommandNameWidth = max(
                        $subcommandNameWidth,
                        $this->getDisplayWidth($subcommandFullName)
                    );
                    $tableData[] = [
                        'type' => 'subcommand',
                        'command' => $name,
                        'subcommand' => $actionKebab,
                        'subcommandFull' => $subcommandFullName,
                        'description' => $actionDescription,
                        'hasSubcommands' => false,
                    ];
                }
            }
        }

        if (empty($tableData)) {
            return;
        }

        // Render table
        $commandColumnWidth = max($commandNameWidth, 12);
        $subcommandColumnWidth = max($subcommandNameWidth, 16);

        // Calculate unified description column start position
        $indentSize = 2;
        $subcommandIndent = 4;
        $descriptionStartPos = $subcommandIndent + $subcommandColumnWidth + 2;

        // Render rows
        foreach ($tableData as $row) {
            $line = '';

            if ($row['type'] === 'command') {
                // Main command row
                $commandDisplay = $this->console->colorize($row['command'], Colors::FC_LIGHT_YELLOW | Colors::AT_BOLD);
                $commandWidth = $this->getDisplayWidth($row['command']);
                $commandPadding = max(0, $commandColumnWidth - $commandWidth);

                $line .= str_repeat(' ', $indentSize);
                $line .= $commandDisplay . str_repeat(' ', $commandPadding);

                if ($row['description'] !== '') {
                    $currentPos = $indentSize + $commandColumnWidth;
                    $paddingToDesc = max(2, $descriptionStartPos - $currentPos);
                    $line .= str_repeat(' ', $paddingToDesc) . $row['description'];
                }
            } else {
                // Subcommand row - show full command format (e.g., "list:default")
                $subcommandFullName = $row['subcommandFull'] ?? ($row['command'] . ':' . $row['subcommand']);
                $subcommandDisplay = $this->console->colorize($subcommandFullName, Colors::FC_LIGHT_CYAN);
                $subcommandWidth = $this->getDisplayWidth($subcommandFullName);
                $subcommandPadding = max(0, $subcommandColumnWidth - $subcommandWidth);

                $line .= str_repeat(' ', $subcommandIndent);
                $line .= $subcommandDisplay;
                $line .= str_repeat(' ', $subcommandPadding);
                $line .= '  ' . $row['description'];
            }

            $this->console->writeLn($line);
        }
    }

    /**
     * Omit from default list when every `*Action` is #[Hidden]: no human-facing subcommands to show.
     *
     * With --all, still list them (hidden actions become visible). Pure tool-only commands stay discoverable via tool:* JSON.
     */
    protected function shouldOmitFromList(string $commandClassName, bool $all): bool
    {
        if ($all || !class_exists($commandClassName)) {
            return false;
        }

        $visible = $this->commandInspector->getActions($commandClassName);
        if ($visible !== []) {
            return false;
        }

        return $this->commandInspector->getActions($commandClassName, true) !== [];
    }

}
