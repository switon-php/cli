<?php

declare(strict_types=1);

namespace Switon\Cli\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerInterface;
use Switon\Cli\Runner;
use Switon\Core\App;
use Switon\Core\Runtime;
use Switon\Sync\Channel;
use Switon\Sync\TerminationInterface;
use Swoole\Coroutine;

class RunnerTest extends TestCase
{
    public function testRunUsesCoroutineSchedulerWhenCoroutineModeEnabled(): void
    {
        if (!extension_loaded('swoole')) {
            self::markTestSkipped('Swoole extension is required');
        }

        Runtime::setCoroutineEnabled(true);
        try {
            $result = Runner::run(static fn (ContainerInterface $container): string => 'coroutine-ok');
        } finally {
            Runtime::setCoroutineEnabled(false);
        }

        $this->assertSame('coroutine-ok', $result);
    }

    public function testRunBootsContainerAndTerminatesInsideCoroutine(): void
    {
        $seen = null;
        $cid = 0;

        try {
            $result = Runner::run(function (ContainerInterface $container) use (&$seen, &$cid): string {
                $seen = $container;
                $cid = Coroutine::getCid();
                $container->get(TerminationInterface::class)->register(new Channel(1));

                return 'ok';
            });
        } finally {
            Runtime::setCoroutineEnabled(false);
        }

        $this->assertSame('ok', $result);
        $this->assertInstanceOf(ContainerInterface::class, $seen);
        if ($cid > 0) {
            $this->assertGreaterThan(0, $cid);
        } else {
            $this->assertSame(-1, $cid);
        }
        $this->assertTrue($seen->get(TerminationInterface::class)->isTerminated());
        $this->assertSame($seen, App::getContainer());
    }
}
