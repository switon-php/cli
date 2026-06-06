<?php

declare(strict_types=1);

namespace Switon\Cli;

use Psr\EventDispatcher\EventDispatcherInterface;
use ReflectionClass;
use Stringable;
use Switon\Cli\Event\ConsoleDebug;
use Switon\Cli\Event\ConsoleError;
use Switon\Cli\Event\ConsoleInfo;
use Switon\Cli\Event\ConsoleWarning;
use Switon\Console\AnsiWidthCalculator;
use Switon\Console\Colors;
use Switon\Console\TerminalWidthDetectorInterface;
use Switon\Core\Attribute\Autowired;
use Switon\Core\ConsoleInterface;
use Switon\Core\Strings;
use Throwable;

use function defined;
use function fgets;
use function function_exists;
use function getenv;
use function implode;
use function is_float;
use function is_int;
use function is_string;
use function max;
use function sprintf;
use function str_contains;
use function str_ends_with;
use function str_pad;
use function str_repeat;
use function str_starts_with;
use function strlen;
use function trim;

/**
 * Renders interactive CLI output with ANSI styling, interpolation, and console events.
 *
 * Use when you need colored output, interactive prompts, and structured
 * console events (<code>ConsoleInfo</code>, <code>ConsoleWarning</code>, etc.).
 *
 * @see \Switon\Cli\Exception
 * @see \Switon\Console\TerminalWidthDetectorInterface
 * @see \Switon\Console\ProgressBarInterface
 * @see \Switon\Cli\Event\ConsoleInfo
 * @see \Switon\Cli\Event\ConsoleError
 */
class Console implements ConsoleInterface
{
    use AnsiWidthCalculator;

    #[Autowired] protected EventDispatcherInterface $eventDispatcher;

    #[Autowired] protected TerminalWidthDetectorInterface $widthDetector;

    /** Cached terminal width. */
    protected ?int $width = null;

    /** Returns cached width or detects it once. */
    protected function getWidth(): int
    {
        if ($this->width === null) {
            $this->width = $this->widthDetector->getWidth();
        }

        return $this->width;
    }

    /** {@inheritDoc} */
    public function isSupportColor(): bool
    {
        if (false !== $this->getEnv('NO_COLOR')) {
            return false;
        }

        $isOutputTty = $this->isOutputTty();
        if ($isOutputTty === false) {
            return false;
        }

        if ($this->isUnix()) {
            return true;
        }

        $term = (string)$this->getEnv('TERM');

        return false !== $this->getEnv('ANSICON')
            || false !== $this->getEnv('WT_SESSION')
            || 'ON' === $this->getEnv('ConEmuANSI')
            || str_starts_with($term, 'xterm');
    }

    protected function isUnix(): bool
    {
        return DIRECTORY_SEPARATOR === '/';
    }

    protected function getEnv(string $name): string|false
    {
        return getenv($name);
    }

    protected function isOutputTty(): ?bool
    {
        if (!defined('STDOUT')) {
            return null;
        }

        if (function_exists('stream_isatty')) {
            return stream_isatty(STDOUT);
        }

        if (function_exists('posix_isatty')) {
            return posix_isatty(STDOUT);
        }

        return null;
    }

    /** {@inheritDoc} */
    public function colorize(string $text, int $options = 0, int $width = 0): string
    {
        $map = [
            Colors::AT_BOLD => "\033[1m",
            Colors::AT_DIM => "\033[2m",
            Colors::AT_ITALICS => "\033[3m",
            Colors::AT_UNDERLINE => "\033[4m",
            Colors::AT_BLINK => "\033[5m",
            Colors::AT_INVERSE => "\033[7m",
            Colors::AT_STRIKETHROUGH => "\033[9m",

            Colors::BC_BLACK => "\033[40m",
            Colors::BC_RED => "\033[41m",
            Colors::BC_GREEN => "\033[42m",
            Colors::BC_YELLOW => "\033[43m",
            Colors::BC_BLUE => "\033[44m",
            Colors::BC_MAGENTA => "\033[45m",
            Colors::BC_CYAN => "\033[46m",
            Colors::BC_WHITE => "\033[47m",

            Colors::FC_BLACK => "\033[30m",
            Colors::FC_RED => "\033[31m",
            Colors::FC_GREEN => "\033[32m",
            Colors::FC_YELLOW => "\033[33m",
            Colors::FC_BLUE => "\033[34m",
            Colors::FC_MAGENTA => "\033[35m",
            Colors::FC_CYAN => "\033[36m",
            Colors::FC_WHITE => "\033[37m",

            Colors::FC_GRAY => "\033[90m",
            Colors::FC_LIGHT_RED => "\033[91m",
            Colors::FC_LIGHT_GREEN => "\033[92m",
            Colors::FC_LIGHT_YELLOW => "\033[93m",
            Colors::FC_LIGHT_BLUE => "\033[94m",
            Colors::FC_LIGHT_MAGENTA => "\033[95m",
            Colors::FC_LIGHT_CYAN => "\033[96m",
            Colors::FC_LIGHT_WHITE => "\033[97m",
        ];

        if (!$this->isSupportColor()) {
            return $width ? str_pad($text, $width) : $text;
        }

        $c = '';
        for ($i = 0; $i < 32; $i++) {
            $flag = 1 << $i;
            if (($flag & $options) && isset($map[$flag])) {
                $c .= $map[$flag];
            }
        }

        return $c . $text . "\033[0m" . str_repeat(' ', max($width - strlen($text), 0));
    }

    /** {@inheritDoc} */
    public function write(string|Stringable $message, array $context = [], int $options = 0): void
    {
        if (is_string($message) && $context !== [] && str_contains($message, '{')) {
            $message = Strings::interpolate($message, $context);
        }

        if ($options === 0) {
            echo $message;
        } else {
            echo $this->colorize((string)$message, $options);
        }

        if (($v = $context['exception'] ?? null) !== null && $v instanceof Throwable) {
            echo $v;
        }
    }

    /** {@inheritDoc} */
    public function sampleColorizer(): void
    {
        $rClass = new ReflectionClass(Colors::class);
        $bc_list = [0 => 0];
        $fc_list = [0 => 0];

        foreach ($rClass->getConstants() as $name => $value) {
            if (str_starts_with($name, 'BC_')) {
                $bc_list[$name] = $value;
            } elseif (str_starts_with($name, 'FC_')) {
                $fc_list[$name] = $value;
            }
        }

        foreach ($bc_list as $bc_name => $bc_value) {
            foreach ($fc_list as $fc_name => $fc_value) {
                $headers = [];
                if ($bc_value) {
                    $headers[] = 'Colors::' . $bc_name;
                }

                if ($fc_value) {
                    $headers[] = 'Colors::' . $fc_name;
                }

                $www = $this->colorize('Switon https://www.switon.com/', $bc_value | $fc_value);
                echo str_pad(implode('|', $headers), 40), $www, PHP_EOL;
            }
        }

        $this->write('');
        $this->info('This is info text');
        $this->warning('This is warn text');
        $this->success('This is success text');
        $this->error('This is error text');

        $this->writeLn();
        $this->writeLn('Progress bar example:');
        $this->progress('current process is {value}', 25);
        $this->progress('current process is {value}', 50);
        $this->progress('current process is {value}', 75);
        $this->progress('current process is {value}', 100);
    }

    /** {@inheritDoc} */
    public function writeLn(string|Stringable $message = '', array $context = [], int $options = 0): void
    {
        $this->write($message, $context, $options);
        $this->write(PHP_EOL);
    }

    /**
     * {@inheritDoc}
     *
     * Dispatches ConsoleDebug event before output.
     */
    public function debug(string|Stringable $message = '', array $context = [], int $options = 0): void
    {
        $this->eventDispatcher->dispatch(new ConsoleDebug($message, $context));
        $this->writeLn($message, $context, $options);
    }

    /**
     * {@inheritDoc}
     *
     * Uses FC_LIGHT_BLUE color and dispatches ConsoleInfo event.
     */
    public function info(string|Stringable $message, array $context = []): void
    {
        $this->eventDispatcher->dispatch(new ConsoleInfo($message, $context));
        $this->writeLn($message, $context, Colors::FC_LIGHT_BLUE);
    }

    /**
     * {@inheritDoc}
     *
     * Uses FC_LIGHT_YELLOW with bold emphasis and dispatches ConsoleWarning event.
     */
    public function warning(string|Stringable $message, array $context = []): void
    {
        $this->eventDispatcher->dispatch(new ConsoleWarning($message, $context));
        $this->writeLn($message, $context, Colors::FC_LIGHT_YELLOW | Colors::AT_BOLD);
    }

    /**
     * {@inheritDoc}
     *
     * Uses FC_LIGHT_GREEN color and dispatches ConsoleInfo event.
     */
    public function success(string|Stringable $message, array $context = []): void
    {
        $this->eventDispatcher->dispatch(new ConsoleInfo($message, $context));
        $this->writeLn($message, $context, Colors::FC_LIGHT_GREEN);
    }

    /** {@inheritDoc} */
    public function error(string|Stringable $message, array $context = [], int $code = 1): int
    {
        $this->eventDispatcher->dispatch(new ConsoleError($message, $context));
        $this->writeLn($message, $context, Colors::FC_LIGHT_RED | Colors::AT_BOLD);

        return $code;
    }

    /** {@inheritDoc} */
    public function progress(string|Stringable $message, mixed $value = null): void
    {
        if ($value !== null) {
            if (is_int($value) || is_float($value)) {
                $percent = sprintf('%2.2f', $value) . '%';
            } else {
                $percent = $value;
            }

            $context = ['value' => $this->colorize((string)$percent, Colors::FC_LIGHT_GREEN)];
        } else {
            $context = ['value' => 0];
        }

        $width = $this->getWidth();
        $this->write(str_pad("\r", $width));
        $this->write("\r");
        $this->write($message, $context);

        if ($value === null) {
            $this->write(PHP_EOL);
        }
    }

    /** {@inheritDoc} */
    public function read(): string
    {
        return trim((string)fgets(STDIN));
    }

    /** {@inheritDoc} */
    public function ask(string $message): string
    {
        if (str_ends_with($message, '?')) {
            $this->writeLn($message);
        } elseif (str_ends_with($message, ':')) {
            $this->write($message . ' ');
        } else {
            $this->write($message . ': ');
        }

        return $this->read();
    }

    /** {@inheritDoc} */
    public function confirm(string $message, bool $default = true): bool
    {
        $affirmative = ['y', 'yes', 'true', '1', 'on'];
        $negative = ['n', 'no', 'false', '0', 'off'];
        $defaultLabel = $default ? 'Y/n' : 'y/N';
        $prompt = $message . ' [' . $defaultLabel . ']: ';

        while (true) {
            $this->write($prompt);
            $response = strtolower(trim($this->read()));

            if ($response === '') {
                return $default;
            }

            if (in_array($response, $affirmative, true)) {
                return true;
            }

            if (in_array($response, $negative, true)) {
                return false;
            }

            // Invalid input - show error and retry
            $this->error(sprintf(
                'Invalid input "%s". Please enter yes/no (y/n).',
                $response
            ));
        }
    }

    /** {@inheritDoc} */
    public function choice(string $message, array $options, string|int|null $default = null): string|int
    {
        $this->writeLn($message);

        // Display options with numbers
        $keys = array_keys($options);
        $isIndexed = array_keys($keys) === $keys; // Check if simple indexed array

        foreach ($options as $key => $label) {
            $displayKey = $isIndexed ? ((int)$key + 1) : $key;
            $indicator = $default === $key ? '*' : ' ';
            $this->writeLn(sprintf(
                ' %s[%s] %s',
                $indicator,
                $this->colorize((string)$displayKey, Colors::FC_LIGHT_CYAN),
                $label
            ));
        }

        // Show default hint if provided
        if ($default !== null) {
            $defaultDisplay = $isIndexed ? ((int)$default + 1) : $default;
            $this->write('Choice [' . $this->colorize((string)$defaultDisplay, Colors::FC_LIGHT_GREEN) . ']: ');
        } else {
            $this->write('Choice: ');
        }

        $response = trim($this->read());

        // Handle empty input
        if ($response === '') {
            if ($default !== null) {
                return $isIndexed ? $options[$default] : $default;
            }
            // No default and empty input - show error and retry
            $validOptions = $isIndexed
                ? implode(', ', range(1, count($options)))
                : implode(', ', array_keys($options));
            $this->error(sprintf(
                'Please select an option. Valid options: %s',
                $validOptions
            ));
            return $this->choice($message, $options, $default);
        }

        // Try numeric selection (1-based for indexed arrays)
        if (is_numeric($response)) {
            $index = (int)$response;
            if ($isIndexed) {
                --$index; // Convert 1-based to 0-based
                if (isset($options[$index])) {
                    return $options[$index];
                }
            } else {
                // For associative arrays, try to find by key
                if (isset($options[$index])) {
                    return $index;
                }
            }
        }

        // Try matching by key (associative) or value (indexed)
        if ($isIndexed) {
            // For indexed arrays, try to match value
            if (in_array($response, $options, true)) {
                return $response;
            }
        } else {
            // For associative arrays, check if key exists
            if (isset($options[$response])) {
                return $response;
            }
        }

        // Invalid selection - show error and retry
        $validOptions = $isIndexed
            ? implode(', ', range(1, count($options)))
            : implode(', ', array_keys($options));
        $this->error(sprintf(
            'Invalid selection "%s". Valid options: %s',
            $response,
            $validOptions
        ));

        // Recursive retry
        return $this->choice($message, $options, $default);
    }

    /** {@inheritDoc} */
    public function secret(string $message): string
    {
        $this->write($message . ': ');

        // Try to use stty for hiding input (Unix/Linux/macOS)
        if (DIRECTORY_SEPARATOR === '/') {
            // Save current stty configuration
            $sttyModeRaw = $this->runShellCommand('stty -g 2>/dev/null');
            $sttyMode = is_string($sttyModeRaw) ? trim($sttyModeRaw) : null;

            if ($sttyMode === null || $sttyMode === '') {
                // stty not available, fall back to visible input
                $this->writeLn();
                $this->warning('Warning: Input will be visible (stty not available)');
                return $this->read();
            }

            // Register shutdown function to restore terminal state
            register_shutdown_function(function () use ($sttyMode) {
                $this->runShellCommand('stty ' . escapeshellarg($sttyMode) . ' 2>/dev/null');
            });

            // Disable echo - stty -echo returns empty string on success
            $this->runShellCommand('stty -echo 2>/dev/null');

            // Read input
            $input = $this->read();

            // Restore stty configuration (use escapeshellarg to prevent injection)
            $this->runShellCommand('stty ' . escapeshellarg($sttyMode) . ' 2>/dev/null');

            // Add newline since input was hidden
            $this->writeLn();

            return $input;
        }

        // Fallback: visible input with warning
        $this->writeLn();
        $this->warning('Warning: Input will be visible (stty not available)');
        $this->write($message . ': ');

        return $this->read();
    }

    /** Executes a shell command and returns string output when available. */
    protected function runShellCommand(string $command): ?string
    {
        $output = shell_exec($command);
        return is_string($output) ? $output : null;
    }

    /**
     * {@inheritDoc}
     */
    public function block(
        string|array $messages,
        ?string      $type = null,
        ?string      $prefix = null,
        bool         $padding = true
    ): void {
        $messages = is_array($messages) ? $messages : [$messages];

        // Determine style based on type
        [$color, $defaultPrefix] = match (strtoupper($type ?? '')) {
            'ERROR' => [Colors::FC_LIGHT_RED | Colors::AT_BOLD, 'ERROR'],
            'WARNING' => [Colors::FC_LIGHT_YELLOW | Colors::AT_BOLD, 'WARNING'],
            'SUCCESS' => [Colors::FC_LIGHT_GREEN | Colors::AT_BOLD, 'SUCCESS'],
            'INFO' => [Colors::FC_LIGHT_BLUE, 'INFO'],
            default => [Colors::FC_WHITE, ''],
        };

        $prefix = $prefix ?? $defaultPrefix;
        $prefixStr = $prefix ? $prefix . ' ' : '  ';
        $prefixWidth = $this->getDisplayWidth($prefixStr);

        // Cap block width so long messages wrap instead of overflowing
        $width = $this->getWidth();
        $blockWidth = max(40, min($width - 4, 120));
        $contentMaxWidth = $blockWidth - 4 - max($prefixWidth, 2);

        // Top border
        $this->writeLn($this->colorize(str_repeat('═', $blockWidth), $color));
        if ($padding) {
            $this->writeLn($this->colorize('║' . str_repeat(' ', $blockWidth - 2) . '║', $color));
        }

        // Content lines (wrap long messages)
        foreach ($messages as $message) {
            $lines = $this->wrapByDisplayWidth((string)$message, $contentMaxWidth);
            foreach ($lines as $i => $lineContent) {
                $content = ($i === 0 && $prefix ? $prefixStr : '  ') . $lineContent;
                $paddingRight = $blockWidth - 4 - $this->getDisplayWidth($content);
                $line = '║ ' . $content . str_repeat(' ', max(0, $paddingRight)) . ' ║';
                $this->writeLn($this->colorize($line, $color));
            }
        }

        // Add padding if requested
        if ($padding) {
            $this->writeLn($this->colorize('║' . str_repeat(' ', $blockWidth - 2) . '║', $color));
        }

        // Bottom border
        $this->writeLn($this->colorize(str_repeat('═', $blockWidth), $color));
    }

    /**
     * Split text into lines that do not exceed the given display width (word wrap).
     *
     * @return list<string>
     */
    protected function wrapByDisplayWidth(string $text, int $maxWidth): array
    {
        if ($maxWidth <= 0 || $this->getDisplayWidth($text) <= $maxWidth) {
            return [$text];
        }
        $lines = [];
        $remaining = trim($text);
        while ($remaining !== '') {
            $line = '';
            $lineWidth = 0;
            $chunk = null;
            $rest = '';
            $pos = strpos($remaining, ' ');
            if ($pos !== false) {
                $chunk = substr($remaining, 0, $pos);
                $rest = ltrim(substr($remaining, $pos));
            } else {
                $chunk = $remaining;
                $rest = '';
            }
            $chunkWidth = $this->getDisplayWidth($chunk);
            if ($chunkWidth > $maxWidth) {
                // Token longer than max: break by character
                $chars = preg_split('//u', $chunk, -1, PREG_SPLIT_NO_EMPTY) ?: [];
                foreach ($chars as $c) {
                    $w = $this->getDisplayWidth($c);
                    if ($lineWidth + $w > $maxWidth && $line !== '') {
                        $lines[] = $line;
                        $line = '';
                        $lineWidth = 0;
                    }
                    $line .= $c;
                    $lineWidth += $w;
                }
                $remaining = $rest;
            } else {
                $line = $chunk;
                $lineWidth = $chunkWidth;
                $remaining = $rest;
                while ($remaining !== '') {
                    $pos = strpos($remaining, ' ');
                    $next = $pos !== false ? substr($remaining, 0, $pos) : $remaining;
                    $nextRest = $pos !== false ? ltrim(substr($remaining, $pos)) : '';
                    $nextWidth = $this->getDisplayWidth($next);
                    $spaceWidth = $line === '' ? 0 : $this->getDisplayWidth(' ');
                    if ($lineWidth + $spaceWidth + $nextWidth <= $maxWidth) {
                        $line .= ($line === '' ? '' : ' ') . $next;
                        $lineWidth += $spaceWidth + $nextWidth;
                        $remaining = $nextRest;
                    } else {
                        break;
                    }
                }
            }
            if ($line !== '') {
                $lines[] = $line;
            }
        }
        return $lines;
    }

    /**
     * {@inheritDoc}
     */
    public function section(string $message): void
    {
        $this->writeLn();
        $this->writeLn($this->colorize($message, Colors::FC_LIGHT_YELLOW | Colors::AT_BOLD));
        $messageWidth = $this->getDisplayWidth($message);
        $this->writeLn($this->colorize(str_repeat('=', $messageWidth), Colors::FC_LIGHT_YELLOW));
    }

    /**
     * {@inheritDoc}
     */
    public function note(string $message): void
    {
        $this->writeLn($this->colorize(' NOTE ', Colors::FC_LIGHT_BLUE | Colors::AT_BOLD) . ' ' . $message);
    }

    /**
     * {@inheritDoc}
     */
    public function caution(string $message): void
    {
        $this->writeLn($this->colorize(' CAUTION ', Colors::FC_LIGHT_YELLOW | Colors::AT_BOLD) . ' ' . $message);
    }

    /**
     * {@inheritDoc}
     */
    public function listing(array $items): void
    {
        foreach ($items as $item) {
            $this->writeLn($this->colorize(' - ', Colors::FC_LIGHT_CYAN) . $item);
        }
    }

    /** {@inheritDoc} */
    public function table(array $headers, array $rows, int $minWidth = 8, bool $withRowNumber = true): void
    {
        if ($withRowNumber && $rows !== []) {
            $headers = $headers !== [] ? array_merge(['#'], $headers) : ['#'];
            $rows = array_map(static fn (array $row, int $i): array => array_merge([$i + 1], $row), $rows, array_keys($rows));
        }
        $cols = empty($rows) ? count($headers) : max(count($headers), max(array_map('count', $rows)));
        if ($cols === 0) {
            return;
        }
        $cellDisplay = static function (mixed $v): string {
            return $v === null ? '-' : (string)$v;
        };
        $widths = [];
        for ($i = 0; $i < $cols; $i++) {
            $w = $minWidth;
            if ($headers !== []) {
                $w = max($w, strlen($cellDisplay($headers[$i] ?? null)));
            }
            foreach ($rows as $row) {
                $w = max($w, strlen($cellDisplay($row[$i] ?? null)));
            }
            $widths[$i] = $w;
        }
        $padLeft = static fn (string $s, int $w) => str_pad($s, $w);
        $padRight = static fn (string $s, int $w) => str_pad($s, $w, ' ', STR_PAD_LEFT);
        if ($headers !== []) {
            $headerCells = [];
            for ($i = 0; $i < $cols; $i++) {
                $s = $cellDisplay($headers[$i] ?? null);
                $headerCells[] = $i === 0 ? $padRight($s, $widths[$i]) : $padLeft($s, $widths[$i]);
            }
            $this->writeLn(implode('  ', $headerCells));
        }
        foreach ($rows as $row) {
            $cells = [];
            for ($i = 0; $i < $cols; $i++) {
                $s = $cellDisplay($row[$i] ?? null);
                $cells[] = $i === 0 ? $padRight($s, $widths[$i]) : $padLeft($s, $widths[$i]);
            }
            $this->writeLn(implode('  ', $cells));
        }
    }

    /**
     * {@inheritDoc}
     */
    public function newLine(int $count = 1): void
    {
        for ($i = 0; $i < $count; $i++) {
            $this->writeLn();
        }
    }

    /**
     * {@inheritDoc}
     */
    public function line(string $message = ''): void
    {
        $this->writeLn($message);
    }

}
