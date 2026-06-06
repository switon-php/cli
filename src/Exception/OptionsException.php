<?php

declare(strict_types=1);

namespace Switon\Cli\Exception;

use Switon\Cli\Exception;

/**
 * Signals command-line option parsing errors (exit code 253).
 *
 * @see \Switon\Cli\Options
 * @see \Switon\Cli\Exception
 */
class OptionsException extends Exception
{
}
