<?php

declare(strict_types=1);

namespace Switon\Cli\Event;

use Stringable;
use Switon\Core\Json;

use function array_slice;
use function basename;
use function implode;
use function preg_match_all;
use function str_contains;
use function str_replace;
use function strtr;

/**
 * Base event for formatted CLI console messages.
 *
 * Normalizes placeholders with context and auto-fills command/args from
 * <code>$GLOBALS['argv']</code> when not provided.
 *
 * Road-signs:
 * - console message events: ConsoleDebug/Info/Warning/Error
 * - emitted by Console methods
 * - separate from command lifecycle events in Handler
 * - command and args are auto-filled from globals when omitted
 *
 * @see \Switon\Cli\Console
 * @see \Switon\Cli\Handler
 * @see \Switon\Cli\Event\CliInvoking
 * @see \Switon\Cli\Event\CliInvoked
 */
abstract class AbstractCommandEvent
{
    /**
     * Creates an event and computes formatted message immediately.
     *
     * @param array<string, mixed> $context
     */
    public function __construct(
        string|Stringable $message,
        array             $context = [],
        string            $command = '',
        string            $args = '',
    ) {
        $this->message = (string)$message;
        $this->context = $context;
        $this->formatted = $this->formatMessage($this->message, $this->context);
        $this->command = $command ?: $this->getCommandFromGlobals();
        $this->args = $args ?: $this->getArgsFromGlobals();
    }

    /** Message after context interpolation. */
    public string $formatted;
    /** Original message template. */
    public string $message;
    /** @var array<string, mixed> Interpolation context. */
    public array $context;
    /** Entrypoint command name. */
    public string $command;
    /** Command arguments string. */
    public string $args;

    /** Reads entrypoint command from globals. */
    protected function getCommandFromGlobals(): string
    {
        return isset($GLOBALS['argv'][0]) ? basename($GLOBALS['argv'][0]) : '';
    }

    /** Reads argument string from globals (without entrypoint). */
    protected function getArgsFromGlobals(): string
    {
        if (!isset($GLOBALS['argv']) || count($GLOBALS['argv']) < 2) {
            return '';
        }
        return implode(' ', array_slice($GLOBALS['argv'], 1));
    }

    /**
     * Replaces <code>{key}</code> placeholders with context values.
     *
     * Complex values are JSON-encoded.
     *
     * @param array<string, mixed> $context
     */
    protected function formatMessage(string $message, array $context): string
    {
        if ($context === [] || !str_contains($message, '{')) {
            return str_replace('"', "'", $message);
        }

        $replaces = [];
        preg_match_all('#{([\w.]+)}#', $message, $matches);
        foreach ($matches[1] as $key) {
            if (($val = $context[$key] ?? null) === null) {
                continue;
            }

            if (is_string($val)) {
                $replaces['{' . $key . '}'] = $val;
            } elseif (is_scalar($val)) {
                $replaces['{' . $key . '}'] = (string)$val;
            } else {
                $replaces['{' . $key . '}'] = Json::stringify($val);
            }
        }

        $formatted = strtr($message, $replaces);
        return str_replace('"', "'", $formatted);
    }
}
