<?php

declare(strict_types=1);

namespace Switon\Cli;

use ReflectionParameter;
use Switon\Core\InputInterface;
use Switon\Core\PositionalInputInterface;

/**
 * Contract for CLI option parsing and typed option access.
 *
 * Parse once with <code>parse()</code>, then read values through
 * <code>get*</code> helpers and <code>getPositional()</code>.
 *
 * Guidance: For application commands, prefer Invoker argument binding; use `--name=value` for values that begin with `-`.
 *
 * Road-signs:
 * - parse() normalizes argv once
 * - normalized storage uses long option keys
 * - positional args stay available for argument binding
 * - normalize() returns short-to-long alias map
 *
 * @see \Switon\Cli\Options
 * @see \Switon\Cli\Options::parse()
 * @see \Switon\Cli\Options::getPositional()
 * @see \Switon\Cli\Handler
 * @see \Switon\Invoking\InvokerInterface::invoke()
 * @see \Switon\Binding\ArgumentsBinderInterface::resolve()
 */
interface OptionsInterface extends InputInterface, PositionalInputInterface
{
    /**
     * Parses argv into normalized option values.
     *
     * @param array<string> $argv Command-line arguments to parse
     *
     * @return array<string, mixed> Parsed option map
     */
    public function parse(array $argv): array;

    /**
     * Returns option value as int.
     */
    public function getInt(string $name, int $default = 0): int;

    /**
     * Returns option value as bool.
     */
    public function getBool(string $name, bool $default = false): bool;

    /**
     * Returns option value as float.
     */
    public function getFloat(string $name, float $default = 0.0): float;

    /**
     * Returns option value as array.
     *
     * @param array<array-key, mixed> $default
     *
     * @return array<array-key, mixed>
     */
    public function getArray(string $name, array $default = []): array;

    /**
     * Returns positional (non-option) arguments.
     *
     * @return list<string>
     */
    public function getPositional(): array;

    /**
     * Normalizes parsed options for an action's scalar parameters.
     *
     * - short aliases are input-only; normalized storage uses long keys
     * - explicit <code>#[ShortOption]</code> keeps priority and remains effective on conflicts
     * - auto short aliases (first-letter) are disabled when their letter conflicts
     *
     * @param array<array-key, ReflectionParameter> $parameters
     *
     * @return array<string, array-key> short => long
     */
    public function normalize(array $parameters): array;
}
