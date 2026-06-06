<?php

declare(strict_types=1);

namespace Switon\Cli;

use JsonException;
use JsonSerializable;
use ReflectionParameter;
use Stringable;
use Switon\Command\Attribute\ShortOption;
use Switon\Core\Json;
use Switon\Core\Naming;

use function array_shift;
use function explode;
use function implode;
use function ltrim;
use function str_contains;
use function str_split;
use function str_starts_with;
use function strlen;
use function strtr;
use function substr;

/**
 * Parses CLI argv into normalized options and positional arguments.
 *
 * Supports long options, short options, combined flags, <code>--key=value</code>,
 * and positional arguments.
 *
 * @see \Switon\Cli\OptionsInterface
 * @see \Switon\Cli\Handler
 * @see \Switon\Cli\Exception\OptionsException
 * @see \Switon\Binding\ArgumentsBinderInterface::resolve()
 * @see \Switon\Core\InputInterface
 */
class Options implements OptionsInterface, JsonSerializable, Stringable
{
    /** @var array<int, string> */
    protected array $argv;
    /** @var array<string, mixed> */
    protected array $options = [];
    /** @var list<string> */
    protected array $positional = [];
    /** @var array<string, string> short => long */
    protected array $aliases = [];

    /** {@inheritDoc} */
    public function parse(array $argv): array
    {
        $this->argv = $argv;
        $this->options = [];
        $this->positional = [];

        while ($argv !== []) {
            if (!str_starts_with($argv[0], '-')) {
                $this->positional[] = array_shift($argv);
                continue;
            }

            $option = array_shift($argv);

            if (str_contains($option, '=')) {
                $parts = explode('=', $option, 2);
                $this->setOption(ltrim($parts[0], '-'), $parts[1]);
                continue;
            }

            if ($option === '--') {
                // Everything after -- is positional
                while ($argv !== []) {
                    $this->positional[] = array_shift($argv);
                }
                break;
            } elseif (str_starts_with($option, '--') || strlen($option) === 2) {
                $name = ltrim($option, '-');

                // --no-xxx negation: store as xxx = false
                if (str_starts_with($name, 'no-') && strlen($name) > 3) {
                    $this->setOption(substr($name, 3), false);
                    continue;
                }

                if ($argv === []) {
                    $value = true;
                } elseif (str_starts_with($argv[0], '-')) {
                    $value = true;
                } else {
                    $value = array_shift($argv);
                }
                $this->setOption($name, $value);
            } else {
                foreach (str_split(substr($option, 1)) as $c) {
                    $this->setOption($c, true);
                }
            }
        }

        if ($this->positional !== []) {
            $this->options[''] = implode(' ', $this->positional);
        }

        return $this->options;
    }

    /** {@inheritDoc} */
    public function normalize(array $parameters): array
    {
        $map = [];
        $grouped = [];

        foreach ($parameters as $key => $parameter) {
            if (!$parameter instanceof ReflectionParameter) {
                continue;
            }

            $attrs = $parameter->getAttributes(ShortOption::class);
            $isExplicit = $attrs !== [];
            $letter = $isExplicit
                ? $attrs[0]->newInstance()->letter
                : $parameter->getName()[0];
            $grouped[$letter][] = [
                'key' => $key,
                'explicit' => $isExplicit,
            ];
        }

        foreach ($grouped as $letter => $items) {
            if (count($items) === 1) {
                $map[$letter] = $items[0]['key'];
                continue;
            }

            // Conflict policy:
            // - if any explicit #[ShortOption] exists => keep first explicit only
            // - if no explicit exists => drop this short letter for all
            foreach ($items as $item) {
                if (!$item['explicit']) {
                    continue;
                }
                $map[$letter] = $item['key'];
                break;
            }
        }

        /** @var array<string, string> $aliases */
        $aliases = $map;
        $this->aliases = $aliases;
        foreach ($aliases as $short => $long) {
            if (!array_key_exists($short, $this->options)) {
                continue;
            }
            if (!array_key_exists($long, $this->options)) {
                $this->options[$long] = $this->options[$short];
            }
            unset($this->options[$short]);
        }

        return $map;
    }

    /** {@inheritDoc} */
    public function getPositional(): array
    {
        return $this->positional;
    }

    /** Stores option value and merges repeated values. */
    protected function setOption(string $name, mixed $value): void
    {
        if (!array_key_exists($name, $this->options)) {
            $this->options[$name] = $value;
            return;
        }

        $existing = $this->options[$name];
        if ($existing === true) {
            $this->options[$name] = true;
            return;
        }

        if (is_array($existing)) {
            $existing[] = $value;
            $this->options[$name] = $existing;
            return;
        }

        $this->options[$name] = [$existing, $value];
    }

    /** {@inheritDoc} */
    public function all(): array
    {
        return $this->options;
    }

    /** {@inheritDoc} */
    public function get(string|int $name, mixed $default = null): mixed
    {
        $key = $this->resolveKey($name);
        return $key !== null ? $this->options[$key] : $default;
    }

    /** {@inheritDoc} */
    public function has(string|int $name): bool
    {
        return $this->resolveKey($name) !== null;
    }

    /**
     * Resolves lookup key from aliases and naming variants.
     *
     * Supports exact, underscore↔hyphen, and camel→kebab matching.
     */
    protected function resolveKey(string|int $name): ?string
    {
        $name = (string)$name;
        foreach (explode('|', $name) as $option) {
            if (isset($this->options[$option])) {
                return $option;
            }

            if (str_contains($option, '_')) {
                $converted = strtr($option, '_', '-');
                if (isset($this->options[$converted])) {
                    return $converted;
                }
            }

            if (str_contains($option, '-')) {
                $converted = strtr($option, '-', '_');
                if (isset($this->options[$converted])) {
                    return $converted;
                }
            }

            $kebab = Naming::kebab($option);
            if ($kebab !== $option && isset($this->options[$kebab])) {
                return $kebab;
            }
        }

        return null;
    }

    /** Returns option value as int. */
    public function getInt(string $name, int $default = 0): int
    {
        $value = $this->get($name);
        if ($value === null) {
            return $default;
        }
        return (int)$value;
    }

    /**
     * Returns option value as bool.
     *
     * True values: <code>true</code>, <code>1</code>, <code>yes</code>, <code>on</code>, <code>true</code>.
     */
    public function getBool(string $name, bool $default = false): bool
    {
        $value = $this->get($name);
        if ($value === null) {
            return $default;
        }

        if (is_bool($value)) {
            return $value;
        }

        $value = strtolower((string)$value);
        return in_array($value, ['1', 'yes', 'on', 'true'], true);
    }

    /** Returns option value as float. */
    public function getFloat(string $name, float $default = 0.0): float
    {
        $value = $this->get($name);
        if ($value === null) {
            return $default;
        }
        return (float)$value;
    }

    /**
     * Returns option value as array.
     *
     * Accepts native arrays, JSON arrays/objects, or comma-separated strings.
     *
     * @param array<array-key, mixed> $default
     *
     * @return array<array-key, mixed>
     */
    public function getArray(string $name, array $default = []): array
    {
        $value = $this->get($name);
        if ($value === null) {
            return $default;
        }

        if (is_array($value)) {
            return $value;
        }

        $value = (string)$value;

        // Try JSON decode first
        if (str_starts_with($value, '[') || str_starts_with($value, '{')) {
            try {
                $decoded = json_decode($value, true, 512, JSON_THROW_ON_ERROR);
                if (is_array($decoded)) {
                    return $decoded;
                }
            } catch (JsonException $e) {
                // Fall through to comma-separated parsing
            }
        }

        // Parse as comma-separated values
        if ($value === '') {
            return [];
        }

        return array_map('trim', explode(',', $value));
    }

    /** @return array<string, mixed> */
    public function jsonSerialize(): array
    {
        return $this->options;
    }

    /** {@inheritDoc} */
    public function __toString(): string
    {
        return Json::stringify($this->jsonSerialize());
    }
}
