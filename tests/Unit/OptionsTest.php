<?php

declare(strict_types=1);

namespace Switon\Cli\Tests\Unit;

use Switon\Cli\Options;
use Switon\Cli\Tests\TestCase;
use ReflectionMethod;
use ReflectionParameter;

/**
 * Test cases for Options class.
 *
 * Tests the command-line options parser and accessor.
 */
class OptionsTest extends TestCase
{
    protected Options $options;

    protected function setUp(): void
    {
        parent::setUp();
        $this->options = new Options();
    }

    public function testParseLongOptionWithValue(): void
    {
        $argv = ['--name', 'John'];
        $result = $this->options->parse($argv);

        $this->assertArrayHasKey('name', $result);
        $this->assertSame('John', $result['name']);
    }

    public function testParseLongOptionWithEquals(): void
    {
        $argv = ['--name=John'];
        $result = $this->options->parse($argv);

        $this->assertArrayHasKey('name', $result);
        $this->assertSame('John', $result['name']);
    }

    public function testParseLongOptionWithNegativeValueAsEquals(): void
    {
        $argv = ['--length=-1'];
        $result = $this->options->parse($argv);

        $this->assertArrayHasKey('length', $result);
        $this->assertSame('-1', $result['length']);
    }

    public function testParseShortOption(): void
    {
        $argv = ['-n', 'John'];
        $result = $this->options->parse($argv);

        $this->assertArrayHasKey('n', $result);
        $this->assertSame('John', $result['n']);
    }

    public function testParseCombinedShortOptions(): void
    {
        $argv = ['-abc'];
        $result = $this->options->parse($argv);

        $this->assertArrayHasKey('a', $result);
        $this->assertArrayHasKey('b', $result);
        $this->assertArrayHasKey('c', $result);
        $this->assertTrue($result['a']);
        $this->assertTrue($result['b']);
        $this->assertTrue($result['c']);
    }

    public function testParseOptionWithEndMarker(): void
    {
        // Arrange
        $argv = ['--name', 'John', '--', 'arg1', 'arg2'];

        // Act
        $result = $this->options->parse($argv);

        // Assert - named option parsed, remaining go to positional
        $this->assertSame('John', $result['name']);
        $this->assertSame(['arg1', 'arg2'], $this->options->getPositional());
    }

    public function testParseFlagOption(): void
    {
        $argv = ['--verbose'];
        $result = $this->options->parse($argv);

        $this->assertArrayHasKey('verbose', $result);
        $this->assertTrue($result['verbose']);
    }

    public function testParseMultipleOptions(): void
    {
        $argv = ['--name', 'John', '--age', '30', '--verbose'];
        $result = $this->options->parse($argv);

        $this->assertSame('John', $result['name']);
        $this->assertSame('30', $result['age']);
        $this->assertTrue($result['verbose']);
    }

    public function testParseReturnsAllOptions(): void
    {
        $argv = ['--name', 'John', '-a', 'value'];
        $this->options->parse($argv);

        $all = $this->options->all();

        $this->assertIsArray($all);
        $this->assertArrayHasKey('name', $all);
        $this->assertArrayHasKey('a', $all);
        $this->assertSame('John', $all['name']);
        $this->assertSame('value', $all['a']);
    }

    public function testGetExistingOption(): void
    {
        $argv = ['--name', 'John'];
        $this->options->parse($argv);

        $value = $this->options->get('name');
        $this->assertSame('John', $value);
    }

    public function testGetNonExistingOption(): void
    {
        $argv = ['--name', 'John'];
        $this->options->parse($argv);

        $value = $this->options->get('age');
        $this->assertNull($value);
    }

    public function testGetWithDefaultValue(): void
    {
        $argv = ['--name', 'John'];
        $this->options->parse($argv);

        $value = $this->options->get('age', 25);
        $this->assertSame(25, $value);
    }

    public function testGetWithAliases(): void
    {
        $argv = ['--verbose', '1'];
        $this->options->parse($argv);

        $value = $this->options->get('verbose|v|debug', false);
        $this->assertSame('1', $value);
    }

    public function testGetWithAliasesSecondAlias(): void
    {
        $argv = ['-v', '1'];
        $this->options->parse($argv);

        $value = $this->options->get('verbose|v|debug', false);
        $this->assertSame('1', $value);
    }


    public function testHasReturnsTrueForExistingOption(): void
    {
        $argv = ['--name', 'John'];
        $this->options->parse($argv);

        $this->assertTrue($this->options->has('name'));
    }

    public function testHasReturnsFalseForNonExistingOption(): void
    {
        $argv = ['--name', 'John'];
        $this->options->parse($argv);

        $this->assertFalse($this->options->has('age'));
    }

    public function testHasWithAliases(): void
    {
        $argv = ['-v'];
        $this->options->parse($argv);

        $this->assertTrue($this->options->has('verbose|v|debug'));
    }

    public function testGetIntReturnsInteger(): void
    {
        $argv = ['--count', '42'];
        $this->options->parse($argv);

        $value = $this->options->getInt('count');
        $this->assertSame(42, $value);
        $this->assertIsInt($value);
    }

    public function testGetIntWithDefault(): void
    {
        $argv = ['--name', 'John'];
        $this->options->parse($argv);

        $value = $this->options->getInt('count', 10);
        $this->assertSame(10, $value);
    }

    public function testGetBoolReturnsTrueForString(): void
    {
        $argv = ['--enabled', 'true'];
        $this->options->parse($argv);

        $value = $this->options->getBool('enabled');
        $this->assertTrue($value);
    }

    public function testGetBoolReturnsFalseForString(): void
    {
        $argv = ['--enabled', 'false'];
        $this->options->parse($argv);

        $value = $this->options->getBool('enabled');
        $this->assertFalse($value);
    }

    public function testGetBoolWithDefault(): void
    {
        $argv = ['--name', 'John'];
        $this->options->parse($argv);

        $value = $this->options->getBool('enabled', true);
        $this->assertTrue($value);
    }

    public function testGetFloatReturnsFloat(): void
    {
        $argv = ['--price', '19.99'];
        $this->options->parse($argv);

        $value = $this->options->getFloat('price');
        $this->assertSame(19.99, $value);
        $this->assertIsFloat($value);
    }

    public function testGetFloatWithDefault(): void
    {
        $argv = ['--name', 'John'];
        $this->options->parse($argv);

        $value = $this->options->getFloat('price', 9.99);
        $this->assertSame(9.99, $value);
    }

    public function testGetArrayParsesCommaSeparated(): void
    {
        $argv = ['--tags', 'php,cli,test'];
        $this->options->parse($argv);

        $value = $this->options->getArray('tags');
        $this->assertSame(['php', 'cli', 'test'], $value);
    }

    public function testGetArrayParsesJson(): void
    {
        $argv = ['--data', '["one","two","three"]'];
        $this->options->parse($argv);

        $value = $this->options->getArray('data');
        $this->assertSame(['one', 'two', 'three'], $value);
    }

    public function testGetArrayWithDefault(): void
    {
        $argv = ['--name', 'John'];
        $this->options->parse($argv);

        $value = $this->options->getArray('tags', ['default']);
        $this->assertSame(['default'], $value);
    }

    public function testJsonSerialization(): void
    {
        $argv = ['--name', 'John', '--age', '30'];
        $this->options->parse($argv);

        $json = json_encode($this->options);
        $decoded = json_decode($json, true);

        $this->assertIsArray($decoded);
        $this->assertArrayHasKey('name', $decoded);
        $this->assertArrayHasKey('age', $decoded);
    }

    public function testStringRepresentation(): void
    {
        $argv = ['--name', 'John', '--age', '30'];
        $this->options->parse($argv);

        $string = (string)$this->options;

        $this->assertIsString($string);
        $this->assertStringContainsString('name', $string);
        $this->assertStringContainsString('age', $string);
    }

    public function testParseEmptyArgv(): void
    {
        $argv = [];
        $result = $this->options->parse($argv);

        $this->assertIsArray($result);
        $this->assertEmpty($result);
    }

    public function testParseOnlyArguments(): void
    {
        // Arrange
        $argv = ['arg1', 'arg2', 'arg3'];

        // Act
        $result = $this->options->parse($argv);

        // Assert - all go to positional, also accessible via get('')
        $this->assertSame('arg1 arg2 arg3', $this->options->get(''));
        $this->assertSame(['arg1', 'arg2', 'arg3'], $this->options->getPositional());
    }

    public function testGetWithUnderscoreToHyphenConversion(): void
    {
        // Arrange - option stored with hyphen
        $argv = ['--my-option', 'value'];
        $this->options->parse($argv);

        // Act - get with underscore
        $value = $this->options->get('my_option');

        // Assert
        $this->assertSame('value', $value);
    }

    public function testGetWithHyphenToUnderscoreConversion(): void
    {
        // Arrange - option stored with underscore
        $argv = ['--my_option', 'value'];
        $this->options->parse($argv);

        // Act - get with hyphen
        $value = $this->options->get('my-option');

        // Assert
        $this->assertSame('value', $value);
    }

    public function testHasWithUnderscoreToHyphenConversion(): void
    {
        // Arrange
        $argv = ['--my-option'];
        $this->options->parse($argv);

        // Act & Assert
        $this->assertTrue($this->options->has('my_option'));
    }

    public function testHasWithHyphenToUnderscoreConversion(): void
    {
        // Arrange
        $argv = ['--my_option'];
        $this->options->parse($argv);

        // Act & Assert
        $this->assertTrue($this->options->has('my-option'));
    }

    /**
     * Both --max-size (recommended) and --maxSize (camelCase) bind to $maxSize.
     */
    public function testGetWithHyphenOptionBindsToCamelCaseParameter(): void
    {
        // Arrange - user passes hyphen style (recommended)
        $argv = ['--max-size', '100'];
        $this->options->parse($argv);

        // Act - lookup by camelCase param name
        $value = $this->options->get('maxSize');

        // Assert
        $this->assertSame('100', $value);
    }

    /**
     * Both --max-size and --maxSize bind to $maxSize.
     */
    public function testGetWithCamelCaseOptionBindsToCamelCaseParameter(): void
    {
        // Arrange - user passes camelCase style
        $argv = ['--maxSize', '100'];
        $this->options->parse($argv);

        // Act - lookup by camelCase param name
        $value = $this->options->get('maxSize');

        // Assert
        $this->assertSame('100', $value);
    }

    public function testHasWithHyphenOptionReturnsTrueForCamelCaseLookup(): void
    {
        // Arrange
        $argv = ['--max-size', '100'];
        $this->options->parse($argv);

        // Act & Assert
        $this->assertTrue($this->options->has('maxSize'));
    }

    public function testHasWithCamelCaseOptionReturnsTrueForCamelCaseLookup(): void
    {
        // Arrange
        $argv = ['--maxSize', '100'];
        $this->options->parse($argv);

        // Act & Assert
        $this->assertTrue($this->options->has('maxSize'));
    }

    public function testGetBoolWithYesValue(): void
    {
        // Arrange
        $argv = ['--enabled', 'yes'];
        $this->options->parse($argv);

        // Act & Assert
        $this->assertTrue($this->options->getBool('enabled'));
    }

    public function testGetBoolWithOnValue(): void
    {
        // Arrange
        $argv = ['--enabled', 'on'];
        $this->options->parse($argv);

        // Act & Assert
        $this->assertTrue($this->options->getBool('enabled'));
    }

    public function testGetBoolWithOneValue(): void
    {
        // Arrange
        $argv = ['--enabled', '1'];
        $this->options->parse($argv);

        // Act & Assert
        $this->assertTrue($this->options->getBool('enabled'));
    }

    public function testGetBoolWithNoValue(): void
    {
        // Arrange
        $argv = ['--enabled', 'no'];
        $this->options->parse($argv);

        // Act & Assert
        $this->assertFalse($this->options->getBool('enabled'));
    }

    public function testGetBoolWithOffValue(): void
    {
        // Arrange
        $argv = ['--enabled', 'off'];
        $this->options->parse($argv);

        // Act & Assert
        $this->assertFalse($this->options->getBool('enabled'));
    }

    public function testGetBoolWithZeroValue(): void
    {
        // Arrange
        $argv = ['--enabled', '0'];
        $this->options->parse($argv);

        // Act & Assert
        $this->assertFalse($this->options->getBool('enabled'));
    }

    public function testGetBoolWithEmptyValue(): void
    {
        // Arrange - flag without value
        $argv = ['--enabled'];
        $this->options->parse($argv);

        // Act & Assert - flag without value is true
        $this->assertTrue($this->options->getBool('enabled'));
    }

    public function testGetArrayWithEmptyValue(): void
    {
        // Arrange
        $argv = ['--tags', ''];
        $this->options->parse($argv);

        // Act
        $value = $this->options->getArray('tags');

        // Assert
        $this->assertSame([], $value);
    }

    public function testGetArrayWithInvalidJson(): void
    {
        // Arrange - invalid JSON that starts with [
        $argv = ['--data', '[invalid'];
        $this->options->parse($argv);

        // Act - should fall back to comma-separated parsing
        $value = $this->options->getArray('data');

        // Assert
        $this->assertSame(['[invalid'], $value);
    }

    public function testGetArrayWithJsonObject(): void
    {
        // Arrange
        $argv = ['--data', '{"key":"value"}'];
        $this->options->parse($argv);

        // Act
        $value = $this->options->getArray('data');

        // Assert
        $this->assertSame(['key' => 'value'], $value);
    }

    public function testParseLongOptionWithoutValue(): void
    {
        // Arrange - long option followed by another option
        $argv = ['--verbose', '--name', 'John'];
        $result = $this->options->parse($argv);

        // Assert
        $this->assertTrue($result['verbose']);
        $this->assertSame('John', $result['name']);
    }

    public function testParseShortOptionWithoutValue(): void
    {
        // Arrange - short option at end
        $argv = ['-v'];
        $result = $this->options->parse($argv);

        // Assert
        $this->assertTrue($result['v']);
    }

    public function testParseShortOptionFollowedByOption(): void
    {
        // Arrange - short option followed by another option
        $argv = ['-v', '-n', 'John'];
        $result = $this->options->parse($argv);

        // Assert
        $this->assertTrue($result['v']);
        $this->assertSame('John', $result['n']);
    }

    // ========================================================================
    // Positional argument tests
    // ========================================================================

    public function testGetPositionalWithPurePositionalArgs(): void
    {
        // Arrange
        $argv = ['a', 'b', 'c'];

        // Act
        $this->options->parse($argv);

        // Assert
        $this->assertSame(['a', 'b', 'c'], $this->options->getPositional());
    }

    public function testGetPositionalWithNamedAndPositionalMixed(): void
    {
        // Arrange
        $argv = ['--name=X', 'a', 'b'];

        // Act
        $result = $this->options->parse($argv);

        // Assert
        $this->assertSame('X', $result['name']);
        $this->assertSame(['a', 'b'], $this->options->getPositional());
    }

    public function testGetPositionalWithPositionalThenFlagsThenPositional(): void
    {
        // Arrange - positional args interleaved with combined short flags
        // Combined flags (-fv) don't consume the next arg as a value
        $argv = ['a', '-fv', 'b'];

        // Act
        $result = $this->options->parse($argv);

        // Assert
        $this->assertSame(['a', 'b'], $this->options->getPositional());
        $this->assertTrue($result['f']);
        $this->assertTrue($result['v']);
    }

    public function testGetPositionalWithDoubleDashTerminator(): void
    {
        // Arrange - everything after -- is positional
        $argv = ['--', 'a', 'b'];

        // Act
        $this->options->parse($argv);

        // Assert
        $this->assertSame(['a', 'b'], $this->options->getPositional());
    }

    public function testGetPositionalWithNamedThenDoubleDash(): void
    {
        // Arrange
        $argv = ['--name', 'John', '--', '--looks-like-option', 'arg'];

        // Act
        $result = $this->options->parse($argv);

        // Assert
        $this->assertSame('John', $result['name']);
        $this->assertSame(['--looks-like-option', 'arg'], $this->options->getPositional());
    }

    public function testGetPositionalReturnsEmptyWhenNoPositional(): void
    {
        // Arrange
        $argv = ['--name', 'John', '--verbose'];

        // Act
        $this->options->parse($argv);

        // Assert
        $this->assertSame([], $this->options->getPositional());
    }

    // ========================================================================
    // Edge cases
    // ========================================================================

    public function testGetPositionalSingleArg(): void
    {
        // Arrange
        $argv = ['hello'];

        // Act
        $this->options->parse($argv);

        // Assert
        $this->assertSame(['hello'], $this->options->getPositional());
    }

    public function testGetPositionalNumericArg(): void
    {
        // Arrange
        $argv = ['42', '3.14'];

        // Act
        $this->options->parse($argv);

        // Assert - numeric strings are positional (don't start with -)
        $this->assertSame(['42', '3.14'], $this->options->getPositional());
    }

    public function testGetPositionalArgWithEqualsSign(): void
    {
        // Arrange - bare key=value without -- prefix is positional
        $argv = ['key=value', 'another'];

        // Act
        $this->options->parse($argv);

        // Assert
        $this->assertSame(['key=value', 'another'], $this->options->getPositional());
    }

    public function testLongOptionConsumesNextNonDashArgAsValue(): void
    {
        // Arrange - --force followed by non-dash arg: arg is consumed as value, NOT positional
        $argv = ['--force', 'pos1'];

        // Act
        $result = $this->options->parse($argv);

        // Assert - 'pos1' becomes the value of --force, not a positional arg
        $this->assertSame('pos1', $result['force']);
        $this->assertSame([], $this->options->getPositional());
    }

    public function testShortOptionConsumesNextNonDashArgAsValue(): void
    {
        // Arrange - -v followed by non-dash arg: arg is consumed as value
        $argv = ['-v', 'pos1'];

        // Act
        $result = $this->options->parse($argv);

        // Assert
        $this->assertSame('pos1', $result['v']);
        $this->assertSame([], $this->options->getPositional());
    }

    public function testPositionalBeforeAndAfterOptions(): void
    {
        // Arrange - positional args on both sides of an equals-style option
        $argv = ['pos1', '--name=John', 'pos2'];

        // Act
        $result = $this->options->parse($argv);

        // Assert
        $this->assertSame('John', $result['name']);
        $this->assertSame(['pos1', 'pos2'], $this->options->getPositional());
    }

    public function testDoubleDashWithNothingAfter(): void
    {
        // Arrange
        $argv = ['--name', 'John', '--'];

        // Act
        $result = $this->options->parse($argv);

        // Assert
        $this->assertSame('John', $result['name']);
        $this->assertSame([], $this->options->getPositional());
    }

    public function testDoubleDashOnly(): void
    {
        // Arrange
        $argv = ['--'];

        // Act
        $result = $this->options->parse($argv);

        // Assert
        $this->assertEmpty($result);
        $this->assertSame([], $this->options->getPositional());
    }

    public function testParseResetsStateOnSecondCall(): void
    {
        // Arrange
        $this->options->parse(['--name', 'John', 'pos1']);

        // Act - second parse should reset
        $result = $this->options->parse(['--age', '30']);

        // Assert - only second parse results remain
        $this->assertSame('30', $result['age']);
        $this->assertArrayNotHasKey('name', $result);
        $this->assertSame([], $this->options->getPositional());
    }

    public function testGetPositionalBeforeParseReturnsEmpty(): void
    {
        // Arrange - fresh instance, no parse() called
        $fresh = new Options();

        // Assert
        $this->assertSame([], $fresh->getPositional());
    }

    public function testPositionalNotInAllOrGet(): void
    {
        // Arrange - positional args should NOT appear in options map
        $argv = ['pos1', '--name', 'John', 'pos2'];

        // Act
        $this->options->parse($argv);

        // Assert - all() has named options + compat '' key; positional is separate
        $this->assertSame('John', $this->options->get('name'));
        $this->assertNull($this->options->get('pos1'));
        $this->assertSame('pos1 pos2', $this->options->get(''));
        $this->assertSame(['pos1', 'pos2'], $this->options->getPositional());
    }

    public function testDoubleDashMakesOptionLikeArgsPositional(): void
    {
        // Arrange - after --, even -x and --foo become positional
        $argv = ['--', '-x', '--foo', 'bar'];

        // Act
        $result = $this->options->parse($argv);

        // Assert
        $this->assertSame('-x --foo bar', $this->options->get(''));
        $this->assertSame(['-x', '--foo', 'bar'], $this->options->getPositional());
    }

    public function testPositionalWithEmptyString(): void
    {
        // Arrange - empty string doesn't start with '-'
        $argv = ['', 'hello'];

        // Act
        $this->options->parse($argv);

        // Assert
        $this->assertSame(['', 'hello'], $this->options->getPositional());
    }

    public function testComplexMixedScenario(): void
    {
        // Arrange - realistic mixed usage
        $argv = ['pos1', '--name=John', '-v', '--', '--not-option', 'pos2'];

        // Act
        $result = $this->options->parse($argv);

        // Assert
        $this->assertSame('John', $result['name']);
        $this->assertTrue($result['v']);
        $this->assertSame(['pos1', '--not-option', 'pos2'], $this->options->getPositional());
    }

    // ========================================================================
    // Repeated option accumulation tests
    // ========================================================================

    /**
     * Test that repeating the same option accumulates values into an array.
     */
    public function testRepeatedOptionAccumulatesIntoArray(): void
    {
        // Arrange
        $argv = ['--tag=a', '--tag=b', '--tag=c'];

        // Act
        $result = $this->options->parse($argv);

        // Assert
        $this->assertSame(['a', 'b', 'c'], $result['tag']);
    }

    /**
     * Test that getArray() returns accumulated repeated option values.
     */
    public function testGetArrayReturnsAccumulatedValues(): void
    {
        // Arrange
        $argv = ['--tag=php', '--tag=cli'];
        $this->options->parse($argv);

        // Act
        $value = $this->options->getArray('tag');

        // Assert
        $this->assertSame(['php', 'cli'], $value);
    }

    /**
     * Test that repeating the same option twice produces a two-element array.
     */
    public function testRepeatedOptionTwiceProducesArray(): void
    {
        // Arrange
        $argv = ['--name', 'John', '--name', 'Jane'];

        // Act
        $result = $this->options->parse($argv);

        // Assert
        $this->assertSame(['John', 'Jane'], $result['name']);
    }

    /**
     * Test that repeating boolean flags stays true (no accumulation).
     */
    public function testRepeatedBoolFlagStaysTrue(): void
    {
        // Arrange
        $argv = ['--verbose', '--verbose'];

        // Act
        $result = $this->options->parse($argv);

        // Assert
        $this->assertTrue($result['verbose']);
    }

    // ========================================================================
    // --no-* negation tests
    // ========================================================================

    public function testNoForceStoresAsFalse(): void
    {
        // Arrange
        $argv = ['--no-force'];

        // Act
        $result = $this->options->parse($argv);

        // Assert - stored as 'force' => false
        $this->assertArrayHasKey('force', $result);
        $this->assertFalse($result['force']);
    }

    public function testNoVerboseStoresAsFalse(): void
    {
        // Arrange
        $argv = ['--no-verbose'];

        // Act
        $result = $this->options->parse($argv);

        // Assert
        $this->assertFalse($result['verbose']);
        $this->assertFalse($this->options->getBool('verbose'));
    }

    public function testNoPrefixWithOtherOptions(): void
    {
        // Arrange
        $argv = ['--name', 'John', '--no-force', '--verbose'];

        // Act
        $result = $this->options->parse($argv);

        // Assert
        $this->assertSame('John', $result['name']);
        $this->assertFalse($result['force']);
        $this->assertTrue($result['verbose']);
    }

    public function testNoPrefixLastWinsOverFlag(): void
    {
        // Arrange - --force then --no-force: last wins
        $argv = ['--force', '--no-force'];

        // Act
        $result = $this->options->parse($argv);

        // Assert - setOption accumulation: true + false via setOption
        $this->assertArrayHasKey('force', $result);
    }

    public function testNoPrefixDoesNotConsumeNextArg(): void
    {
        // Arrange - --no-force should NOT consume 'pos1' as its value
        $argv = ['--no-force', 'pos1'];

        // Act
        $result = $this->options->parse($argv);

        // Assert
        $this->assertFalse($result['force']);
        $this->assertSame(['pos1'], $this->options->getPositional());
    }

    public function testNoPrefixWithEqualsIgnored(): void
    {
        // Arrange - --no-force=xxx goes through equals branch, stored as 'no-force' = 'xxx'
        $argv = ['--no-force=xxx'];

        // Act
        $result = $this->options->parse($argv);

        // Assert - equals branch handles it, no negation stripping
        $this->assertArrayHasKey('no-force', $result);
        $this->assertSame('xxx', $result['no-force']);
    }

    public function testNoPrefixWithHyphenatedName(): void
    {
        // Arrange - --no-dry-run → stores as 'dry-run' = false
        $argv = ['--no-dry-run'];

        // Act
        $result = $this->options->parse($argv);

        // Assert
        $this->assertArrayHasKey('dry-run', $result);
        $this->assertFalse($result['dry-run']);
    }

    public function testBareDashDashNoIsNotNegation(): void
    {
        // Arrange - --no alone (length == 2 after stripping --) is just a flag 'no'
        $argv = ['--no'];

        // Act
        $result = $this->options->parse($argv);

        // Assert - 'no' is treated as a regular flag, not negation
        $this->assertArrayHasKey('no', $result);
        $this->assertTrue($result['no']);
    }

    // ========================================================================
    // Action-aware normalize() regression tests
    // ========================================================================

    public function testNormalizeRewritesShortKeyToLongKey(): void
    {
        $this->options->parse(['-v']);
        $parameters = $this->getNormalizeParameters('toggleVerboseAction');

        $map = $this->options->normalize($parameters);

        $this->assertSame(['v' => 'verbose'], $map);
        $this->assertFalse($this->options->has('v'));
        $this->assertTrue($this->options->has('verbose'));
        $this->assertTrue($this->options->getBool('verbose'));
    }

    public function testNormalizeKeepsRepeatedShortValuesAsArrayOnLongKey(): void
    {
        $this->options->parse(['-t', 'a', '-t', 'b', '-t', 'c']);
        $parameters = $this->getNormalizeParameters('tagsAction');

        $map = $this->options->normalize($parameters);

        $this->assertSame(['t' => 'tags'], $map);
        $this->assertSame(['a', 'b', 'c'], $this->options->getArray('tags'));
        $this->assertFalse($this->options->has('t'));
    }

    public function testNormalizeKeepsLongValueWhenLongAndShortCoexist(): void
    {
        $this->options->parse(['--tags=long1', '--tags=long2', '-t', 'short1']);
        $parameters = $this->getNormalizeParameters('tagsAction');

        $this->options->normalize($parameters);

        // long-first strategy: keep explicit long values, do not merge short payload when long key already exists
        $this->assertSame(['long1', 'long2'], $this->options->getArray('tags'));
    }

    public function testNormalizeKeepsExplicitShortAliasWhenConflictedWithAuto(): void
    {
        $this->options->parse(['-n', 'alice']);
        $parameters = $this->getNormalizeParameters('conflictAction');

        $map = $this->options->normalize($parameters);

        $this->assertSame(['n' => 'name'], $map);
        $this->assertFalse($this->options->has('n'));
        $this->assertSame('alice', $this->options->get('name'));
        $this->assertNull($this->options->get('number'));
    }

    public function testNormalizeDisablesAllWhenAutoShortsConflict(): void
    {
        $this->options->parse(['-n', 'alice']);
        $parameters = $this->getNormalizeParameters('autoConflictAction');

        $map = $this->options->normalize($parameters);

        $this->assertSame([], $map);
        $this->assertSame('alice', $this->options->get('n'));
        $this->assertNull($this->options->get('name'));
        $this->assertNull($this->options->get('number'));
    }

    public function testNormalizeKeepsOnlyFirstWhenMultipleExplicitUseSameLetter(): void
    {
        $this->options->parse(['-x', 'alice']);
        $parameters = $this->getNormalizeParameters('multipleExplicitConflictAction');

        $map = $this->options->normalize($parameters);

        $this->assertSame(['x' => 'first'], $map);
        $this->assertSame('alice', $this->options->get('first'));
        $this->assertNull($this->options->get('second'));
        $this->assertFalse($this->options->has('x'));
    }

    /**
     * @return array<string, ReflectionParameter>
     */
    protected function getNormalizeParameters(string $method): array
    {
        $rMethod = new ReflectionMethod(OptionsNormalizeFixture::class, $method);
        $parameters = [];
        foreach ($rMethod->getParameters() as $parameter) {
            $parameters[$parameter->getName()] = $parameter;
        }

        return $parameters;
    }
}

class OptionsNormalizeFixture
{
    public function toggleVerboseAction(bool $verbose = false): void
    {
    }

    public function tagsAction(#[\Switon\Command\Attribute\ShortOption('t')] array $tags = []): void
    {
    }

    public function conflictAction(
        #[\Switon\Command\Attribute\ShortOption('n')] string $name = '',
        string                                               $number = ''
    ): void {
    }

    public function autoConflictAction(string $name = '', string $number = ''): void
    {
    }

    public function multipleExplicitConflictAction(
        #[\Switon\Command\Attribute\ShortOption('x')] string $first = '',
        #[\Switon\Command\Attribute\ShortOption('x')] string $second = ''
    ): void {
    }
}
