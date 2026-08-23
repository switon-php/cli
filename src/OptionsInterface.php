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
 * <code>get*</code> helpers, <code>getPositional()</code>, and <code>getTrailing()</code>.
 *
 * Guidance: For application commands, prefer Invoker argument binding; use `--name=value` for values that begin with `-`.
 * Put a bare <code>--</code> before a passthrough command line so the binder can fill a trailing <code>array</code> parameter without optional flags stealing those tokens.
 *
 * Road-signs:
 * - parse() peels bare <code>--</code> first, then normalizes the left side
 * - normalized storage uses long option keys
 * - positional (before <code>--</code>) and trailing (after <code>--</code>) stay available for argument binding
 * - normalize() returns short-to-long alias map
 *
 * @see \Switon\Cli\Options
 * @see \Switon\Cli\Options::parse()
 * @see \Switon\Cli\Options::getPositional()
 * @see \Switon\Cli\Options::getTrailing()
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
     * Returns positional operands before <code>--</code> (or all bare operands when <code>--</code> is absent).
     *
     * @return list<string>
     */
    public function getPositional(): array;

    /**
     * Returns operands after <code>--</code>, or <code>null</code> when <code>--</code> was not present.
     *
     * @return list<string>|null
     */
    public function getTrailing(): ?array;

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
