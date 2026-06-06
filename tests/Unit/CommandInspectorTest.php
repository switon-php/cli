<?php

declare(strict_types=1);

namespace Switon\Cli\Tests\Unit;

use ReflectionMethod;
use Switon\Cli\Command\ListCommand;
use Switon\Cli\Tests\TestCase;
use Switon\Command\Attribute\Tool;
use Switon\Command\CommandDiscoveryInterface;
use Switon\Command\CommandInspector;
use DateTimeImmutable;

/**
 * Test cases for CommandInspector class.
 */
class CommandInspectorTest extends TestCase
{
    protected CommandInspector $inspector;

    protected function setUp(): void
    {
        parent::setUp();
        $this->inspector = $this->container->make(CommandInspector::class);
    }

    public function testIsHiddenCommandWithNonExistentClass(): void
    {
        // Arrange
        $className = 'NonExistent\\Class\\Name';

        // Act
        $result = $this->inspector->isHiddenCommand($className);

        // Assert
        $this->assertFalse($result);
    }

    public function testIsHiddenCommandWithHiddenCommand(): void
    {
        // Arrange - ListCommand has #[Hidden] attribute
        $className = ListCommand::class;

        // Act
        $result = $this->inspector->isHiddenCommand($className);

        // Assert
        $this->assertTrue($result);
    }

    public function testGetCommandDescriptionWithNonExistentClass(): void
    {
        // Arrange
        $className = 'NonExistent\\Class\\Name';

        // Act
        $result = $this->inspector->getCommandDescription($className);

        // Assert
        $this->assertSame('', $result);
    }

    public function testGetCommandDescriptionWithValidClass(): void
    {
        // Arrange
        $className = ListCommand::class;

        // Act
        $result = $this->inspector->getCommandDescription($className);

        // Assert
        $this->assertIsString($result);
    }

    public function testGetCommandDescriptionStripsHtmlTags(): void
    {
        // Arrange
        $className = CommandInspectorDocblockFixture::class;

        // Act
        $result = $this->inspector->getCommandDescription($className);

        // Assert
        $this->assertSame('Fixture command description', $result);
    }

    public function testGetActionsWithNonExistentClass(): void
    {
        // Arrange
        $className = 'NonExistent\\Class\\Name';

        // Act
        $result = $this->inspector->getActions($className);

        // Assert
        $this->assertSame([], $result);
    }

    public function testGetActionsWithValidCommand(): void
    {
        // Arrange
        $className = ListCommand::class;

        // Act
        $result = $this->inspector->getActions($className);

        // Assert
        $this->assertIsArray($result);
        // ListCommand should have at least one action
        $this->assertNotEmpty($result);
    }

    public function testGetActionsIncludesHiddenWhenRequested(): void
    {
        // Arrange
        $className = ListCommand::class;

        // Act
        $resultWithHidden = $this->inspector->getActions($className, true);
        $resultWithoutHidden = $this->inspector->getActions($className, false);

        // Assert - both should return actions
        $this->assertIsArray($resultWithHidden);
        $this->assertIsArray($resultWithoutHidden);
    }

    public function testGetMethodDescriptionWithDocComment(): void
    {
        // Arrange - ListCommand uses defaultAction, not mainAction
        $reflection = new ReflectionMethod(ListCommand::class, 'defaultAction');

        // Act
        $result = $this->inspector->getMethodDescription($reflection);

        // Assert
        $this->assertIsString($result);
    }

    public function testGetMethodDescriptionStripsHtmlTags(): void
    {
        // Arrange
        $reflection = new ReflectionMethod(CommandInspectorDocblockFixture::class, 'demoAction');

        // Act
        $result = $this->inspector->getMethodDescription($reflection);

        // Assert
        $this->assertSame('Action summary', $result);
    }

    public function testGetMethodDescriptionTrimsChineseFullStop(): void
    {
        // Arrange
        $reflection = new ReflectionMethod(CommandInspectorDocblockFixture::class, 'zhAction');

        // Act
        $result = $this->inspector->getMethodDescription($reflection);

        // Assert
        $this->assertSame('中文说明', $result);
    }

    public function testHasOptionsWithTypedParameters(): void
    {
        // Arrange
        $reflection = new ReflectionMethod(ListCommand::class, 'defaultAction');

        // Act
        $result = $this->inspector->hasOptions($reflection);

        // Assert
        $this->assertIsBool($result);
    }

    public function testGetOptionsWithMethod(): void
    {
        // Arrange
        $reflection = new ReflectionMethod(ListCommand::class, 'defaultAction');

        // Act
        $result = $this->inspector->getOptions($reflection);

        // Assert
        $this->assertIsArray($result);
    }

    public function testGetOptionsReturnsExpectedStructure(): void
    {
        // Arrange
        $reflection = new ReflectionMethod(ListCommand::class, 'defaultAction');

        // Act
        $result = $this->inspector->getOptions($reflection);

        // Assert - each option should have description, default, and type
        foreach ($result as $name => $option) {
            $this->assertArrayHasKey('description', $option);
            $this->assertArrayHasKey('default', $option);
            $this->assertArrayHasKey('type', $option);
        }
    }

    public function testGetOptionsStripsHtmlTagsInParamDescription(): void
    {
        // Arrange
        $reflection = new ReflectionMethod(CommandInspectorDocblockFixture::class, 'demoAction');

        // Act
        $result = $this->inspector->getOptions($reflection);

        // Assert
        $this->assertSame('Option name used for filtering', $result['name']['description'] ?? '');
    }

    public function testDiscoveredCommandActionDescriptionsAreCliFriendly(): void
    {
        // Arrange
        $discovery = $this->container->make(CommandDiscoveryInterface::class);

        // Act
        $commands = $discovery->discover();

        // Assert
        foreach ($commands as $commandName => $commandClass) {
            $actions = $this->inspector->getActions($commandClass, true);
            foreach ($actions as $action => $description) {
                $label = $commandName . ':' . $action;
                $this->assertNotSame('', trim($description), "Action description must not be empty for {$label}");
                $this->assertStringNotContainsString('<', $description, "Action description must not contain '<' for {$label}");
                $this->assertStringNotContainsString('>', $description, "Action description must not contain '>' for {$label}");
                $this->assertFalse(str_ends_with($description, '.'), "Action description must not end with '.' for {$label}");
                $this->assertFalse(str_ends_with($description, '。'), "Action description must not end with '。' for {$label}");
            }
        }
    }

    public function testGetActionAiDocReturnsNullWhenNoToolAttribute(): void
    {
        $doc = $this->inspector->getActionAiDoc(CommandInspectorToolFixture::class, 'plain');
        $this->assertNull($doc);
    }

    public function testGetActionAiDocReturnsTrimmedDocWhenToolAttributePresent(): void
    {
        $doc = $this->inspector->getActionAiDoc(CommandInspectorToolFixture::class, 'tool');
        $this->assertSame('Returns JSON: {"ok": true}', $doc);
    }

    public function testGetActionParametersReturnsOptionsForExistingAction(): void
    {
        $params = $this->inspector->getActionParameters(CommandInspectorDocblockFixture::class, 'demo');

        $this->assertArrayHasKey('name', $params);
        $this->assertSame('string', $params['name']['type']);
    }

    public function testGetActionParametersReturnsEmptyForMissingClassOrAction(): void
    {
        $this->assertSame([], $this->inspector->getActionParameters('Missing\\Class', 'demo'));
        $this->assertSame([], $this->inspector->getActionParameters(CommandInspectorDocblockFixture::class, 'missing'));
    }

    public function testHasOptionsReturnsFalseWhenOnlyObjectParameters(): void
    {
        $reflection = new ReflectionMethod(CommandInspectorObjectOnlyFixture::class, 'defaultAction');

        $this->assertFalse($this->inspector->hasOptions($reflection));
        $this->assertSame([], $this->inspector->getOptions($reflection));
    }

    public function testGetMethodDescriptionFallsBackToKebabCaseWhenNoDocComment(): void
    {
        $reflection = new ReflectionMethod(CommandInspectorNoDocFixture::class, 'runFastAction');

        $this->assertSame('run-fast', $this->inspector->getMethodDescription($reflection));
    }

    public function testGetCommandDescriptionReturnsEmptyForClassPrefixAndTagPrefix(): void
    {
        $this->assertSame('', $this->inspector->getCommandDescription(CommandInspectorClassPrefixFixture::class));
        $this->assertSame('', $this->inspector->getCommandDescription(CommandInspectorTagPrefixFixture::class));
    }

    public function testGetOptionsIgnoresObjectTypedParamEvenWhenDocContainsParam(): void
    {
        $reflection = new ReflectionMethod(CommandInspectorObjectDocFixture::class, 'defaultAction');
        $options = $this->inspector->getOptions($reflection);

        $this->assertArrayNotHasKey('date', $options);
        $this->assertArrayHasKey('name', $options);
        $this->assertSame('Name to print', $options['name']['description']);
    }

    public function testGetActionAiDocReturnsNullWhenToolDocIsEmptyAfterTrim(): void
    {
        $doc = $this->inspector->getActionAiDoc(CommandInspectorToolFixture::class, 'emptyTool');
        $this->assertNull($doc);
    }
}

class CommandInspectorToolFixture
{
    public function plainAction(): void
    {
    }

    #[Tool('  Returns JSON: {"ok": true}  ')]
    public function toolAction(): void
    {
    }

    #[Tool('   ')]
    public function emptyToolAction(): void
    {
    }
}

/**
 * Fixture command <code>description</code>.
 */
class CommandInspectorDocblockFixture
{
    /**
     * Action <code>summary</code>.
     *
     * @param string $name Option <code>name</code> used for filtering.
     */
    public function demoAction(string $name = ''): void
    {
    }

    /**
     * 中文说明。
     */
    public function zhAction(): void
    {
    }
}

class CommandInspectorObjectOnlyFixture
{
    public function defaultAction(DateTimeImmutable $date): void
    {
    }
}

class CommandInspectorNoDocFixture
{
    public function runFastAction(): void
    {
    }
}

/**
 * Class CommandInspectorClassPrefixFixture
 */
class CommandInspectorClassPrefixFixture
{
}

/**
 * @internal
 */
class CommandInspectorTagPrefixFixture
{
}

class CommandInspectorObjectDocFixture
{
    /**
     * @param DateTimeImmutable $date Date object.
     * @param string $name Name to print.
     */
    public function defaultAction(DateTimeImmutable $date, string $name = 'x'): void
    {
    }
}
