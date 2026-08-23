<?php

declare(strict_types=1);

namespace Switon\Cli\Tests\Unit;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerInterface;
use Psr\EventDispatcher\EventDispatcherInterface;
use ReflectionMethod;
use Switon\Binding\ArgumentsBinder;
use Switon\Binding\ObjectResolverInterface;
use Switon\Binding\ScalarResolver;
use Switon\Cli\Options;
use Switon\Core\InputInterface;
use Switon\Core\MakerInterface;
use Switon\Validating\Validation;
use Switon\Validating\ValidatorInterface;

/**
 * Contract tests: Options peel of <code>--</code> plus ScalarResolver / ArgumentsBinder.
 *
 * Regressions here break Codex-style <code>add id --force -- cmd …</code> signatures.
 */
class OptionsTrailingBindingTest extends TestCase
{
    #[DataProvider('addStyleArgvProvider')]
    public function testScalarResolverMatchesAddStyleSignature(
        array $argv,
        string $server,
        ?bool $forceResolved,
        ?string $urlResolved,
        ?array $command,
    ): void {
        $options = new Options();
        $options->parse($argv);

        $resolver = $this->scalarResolverFor($options);
        $parameters = (new ReflectionMethod(OptionsTrailingBindFixture::class, 'addAction'))->getParameters();

        $this->assertSame($server, $resolver->resolve($parameters[0], 'string'));
        $this->assertSame($forceResolved, $resolver->resolve($parameters[1], 'bool'));
        $this->assertSame($urlResolved, $resolver->resolve($parameters[2], 'string'));
        $this->assertSame($command, $resolver->resolve($parameters[3], 'array'));
    }

    #[DataProvider('addStyleArgvProvider')]
    public function testArgumentsBinderAppliesDefaultsForAddStyleSignature(
        array $argv,
        string $server,
        ?bool $forceResolved,
        ?string $urlResolved,
        ?array $command,
    ): void {
        $options = new Options();
        $options->parse($argv);

        $resolved = $this->argumentsBinderFor($options)->resolve(
            new ReflectionMethod(OptionsTrailingBindFixture::class, 'addAction'),
        );

        $this->assertSame($server, $resolved[0]);
        $this->assertSame($forceResolved ?? false, $resolved[1]);
        $this->assertSame($urlResolved ?? '', $resolved[2]);
        $this->assertSame($command ?? [], $resolved[3]);
    }

    public function testWithoutSeparatorOptionalScalarsStillStealCommandTokens(): void
    {
        // Avoid tokens starting with `-` — those are options, not positionals.
        $options = new Options();
        $options->parse(['app', 'npx', 'pkg', 'extra']);

        $resolver = $this->scalarResolverFor($options);
        $parameters = (new ReflectionMethod(OptionsTrailingBindFixture::class, 'addAction'))->getParameters();

        // Footgun without `--`: unbound optionals consume positionals before the array tail.
        $this->assertSame('app', $resolver->resolve($parameters[0], 'string'));
        $this->assertSame('npx', $resolver->resolve($parameters[1], 'bool')); // raw steal into bool slot
        $this->assertSame('pkg', $resolver->resolve($parameters[2], 'string'));
        $this->assertSame(['extra'], $resolver->resolve($parameters[3], 'array'));
    }

    public function testUrlOnlyAddDoesNotRequireSeparator(): void
    {
        $options = new Options();
        $options->parse(['docs', '--url=https://example.com/mcp']);

        $resolved = $this->argumentsBinderFor($options)->resolve(
            new ReflectionMethod(OptionsTrailingBindFixture::class, 'addAction'),
        );

        $this->assertSame(['docs', false, 'https://example.com/mcp', []], $resolved);
    }

    public function testForceAndUrlNamedWithTrailingCommand(): void
    {
        $options = new Options();
        $options->parse([
            'app',
            '--force',
            '--url=https://should-not-matter-when-command-also-set',
            '--',
            'npx',
            '-y',
            'pkg',
        ]);

        $resolved = $this->argumentsBinderFor($options)->resolve(
            new ReflectionMethod(OptionsTrailingBindFixture::class, 'addAction'),
        );

        $this->assertSame('app', $resolved[0]);
        $this->assertTrue($resolved[1]);
        $this->assertSame('https://should-not-matter-when-command-also-set', $resolved[2]);
        $this->assertSame(['npx', '-y', 'pkg'], $resolved[3]);
    }

    public function testTrailingOptionLikeTokensAreNotParsedAsNamedOptions(): void
    {
        $options = new Options();
        $options->parse(['app', '--', '--force', '--url=https://x']);

        $this->assertFalse($options->has('force'));
        $this->assertFalse($options->has('url'));

        $resolved = $this->argumentsBinderFor($options)->resolve(
            new ReflectionMethod(OptionsTrailingBindFixture::class, 'addAction'),
        );

        $this->assertSame(['app', false, '', ['--force', '--url=https://x']], $resolved);
    }

    /**
     * @return iterable<string, array{
     *   0: list<string>,
     *   1: string,
     *   2: bool|null,
     *   3: string|null,
     *   4: list<string>|null
     * }>
     */
    public static function addStyleArgvProvider(): iterable
    {
        yield 'force and stdio command' => [
            ['app', '--force', '--', 'npx', '-y', 'tool'],
            'app',
            true,
            null,
            ['npx', '-y', 'tool'],
        ];
        yield 'stdio command without force' => [
            ['app', '--', 'npx', '-y', 'tool'],
            'app',
            null,
            null,
            ['npx', '-y', 'tool'],
        ];
        yield 'empty command after separator' => [
            ['app', '--force', '--'],
            'app',
            true,
            null,
            null,
        ];
        yield 'url named only' => [
            ['docs', '--url=https://example.com/mcp'],
            'docs',
            null,
            'https://example.com/mcp',
            null,
        ];
        yield 'url and force without command' => [
            ['docs', '--force', '--url=https://example.com/mcp'],
            'docs',
            true,
            'https://example.com/mcp',
            null,
        ];
        yield 'command with dashes stays trailing' => [
            ['app', '--', 'node', './bin', '--port', '8080'],
            'app',
            null,
            null,
            ['node', './bin', '--port', '8080'],
        ];
    }

    protected function scalarResolverFor(InputInterface $input): ScalarResolver
    {
        $resolver = new class () extends ScalarResolver {
            public function setInput(InputInterface $input): void
            {
                $this->input = $input;
            }
        };
        $resolver->setInput($input);

        return $resolver;
    }

    protected function argumentsBinderFor(InputInterface $input): ArgumentsBinder
    {
        $validation = $this->createStub(Validation::class);
        $validation->method('validate')->willReturn(true);

        $validator = $this->createStub(ValidatorInterface::class);
        $validator->method('beginValidate')->willReturn($validation);

        $binder = new class () extends ArgumentsBinder {
            public function setScalarResolver(\Switon\Binding\ScalarResolverInterface $scalarResolver): void
            {
                $this->scalarResolver = $scalarResolver;
            }

            public function setObjectResolver(ObjectResolverInterface $objectResolver): void
            {
                $this->objectResolver = $objectResolver;
            }

            public function setContainer(ContainerInterface $container): void
            {
                $this->container = $container;
            }

            public function setValidator(ValidatorInterface $validator): void
            {
                $this->validator = $validator;
            }

            public function setEventDispatcher(EventDispatcherInterface $eventDispatcher): void
            {
                $this->eventDispatcher = $eventDispatcher;
            }

            public function setMaker(MakerInterface $maker): void
            {
                $this->maker = $maker;
            }
        };

        $binder->setScalarResolver($this->scalarResolverFor($input));
        $binder->setObjectResolver($this->createStub(ObjectResolverInterface::class));
        $binder->setContainer($this->createStub(ContainerInterface::class));
        $binder->setValidator($validator);
        $binder->setEventDispatcher($this->createStub(EventDispatcherInterface::class));
        $binder->setMaker($this->createStub(MakerInterface::class));

        return $binder;
    }
}

class OptionsTrailingBindFixture
{
    /**
     * @param list<string> $command
     */
    public function addAction(
        string $server,
        bool $force = false,
        string $url = '',
        array $command = [],
    ): void {
    }
}
