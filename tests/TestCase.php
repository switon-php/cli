<?php

declare(strict_types=1);

namespace Switon\Cli\Tests;

use Switon\Cli\OptionsInterface;
use Switon\Core\InputInterface;
use Switon\Testing\TestCase as BaseTestCase;

/**
 * Base test case for CLI tests
 */
abstract class TestCase extends BaseTestCase
{
    protected function setUpContainer(): void
    {
        parent::setUpContainer();

        $this->container->set(InputInterface::class, OptionsInterface::class);
    }
}
