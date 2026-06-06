<?php

declare(strict_types=1);

namespace Switon\Cli;

/**
 * Contract for parsing argv into command routing parts.
 *
 * Guidance: Public command and action names use kebab-case; only <code>:</code> splits <code>command:action</code>.
 *
 * Road-signs:
 * - parse() stores routing state
 * - command and action remain public kebab-case names
 * - params are the argv tail passed to Options::parse()
 *
 * @see \Switon\Cli\Router
 * @see \Switon\Cli\RouterInterface::parse()
 * @see \Switon\Cli\Handler
 */
interface RouterInterface
{
    /**
     * Parses argv and stores routing result.
     *
     * @param array<string> $args
     *
     * @return self
     */
    public function parse(array $args): self;

    /**
     * Returns entrypoint script name.
     */
    public function getEntrypoint(): string;

    /**
     * Returns resolved public command name in kebab-case.
     */
    public function getCommand(): string;

    /**
     * Returns resolved public action name in kebab-case.
     */
    public function getAction(): string;

    /**
     * Returns command parameters.
     *
     * @return array<int, string>
     */
    public function getParams(): array;
}
