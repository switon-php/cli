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
        $home = sys_get_temp_dir() . '/switon-cli-home-' . uniqid('', true);
        mkdir($home . '/.switon', 0777, true);
        file_put_contents($home . '/.switon/llm.yml', "aliases:\n  best: missing\n");
        $previousHome = getenv('HOME');
        $previousProfile = getenv('USERPROFILE');
        putenv('HOME=' . $home);
        putenv('USERPROFILE=' . $home);

        try {
            $result = Runner::run(function (ContainerInterface $container) use (&$seen, &$cid): string {
                $seen = $container;
                $cid = extension_loaded('swoole') ? \Swoole\Coroutine::getCid() : -1;
                $container->get(TerminationInterface::class)->register(new Channel(1));

                return 'ok';
            });
        } finally {
            Runtime::setCoroutineEnabled(false);
            if ($previousHome === false) {
                putenv('HOME');
            } else {
                putenv('HOME=' . $previousHome);
            }
            if ($previousProfile === false) {
                putenv('USERPROFILE');
            } else {
                putenv('USERPROFILE=' . $previousProfile);
            }
            @unlink($home . '/.switon/llm.yml');
            @rmdir($home . '/.switon');
            @rmdir($home);
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
