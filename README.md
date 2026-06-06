# Switon CLI Package

[![CI](https://img.shields.io/github/actions/workflow/status/switon-php/cli/ci.yml?branch=main&label=CI)](https://github.com/switon-php/cli/actions/workflows/ci.yml) [![PHP 8.3+](https://img.shields.io/badge/PHP-8.3%2B-777BB4)](https://www.php.net/)

Switon's CLI runtime for auto-discovered commands, help, and shell completion.

## Highlights

- **Auto-discovered commands:** package and app commands appear without manual registration.
- **Multi-action commands:** one command can expose several actions with shared help.
- **Automatic options:** action parameters become long options by default, with `#[ShortOption]` for short aliases.
- **Inline help text:** command help stays close to the code.
- **Shell completion:** the same command metadata feeds bash and zsh completion.

## Installation

```bash
composer require switon/cli
```

## Quick Start

```php
use Switon\Core\Attribute\Autowired;
use Switon\Core\ConsoleInterface;

/**
 * Send a greeting.
 */
class GreetCommand
{
    #[Autowired] protected ConsoleInterface $console;

    /**
     * Say hello to one user.
     *
     * @param string $name Recipient name.
     * @param bool $shout Uppercase the message.
     */
    public function helloAction(string $name = 'world', bool $shout = false): void
    {
        $message = $shout ? 'HELLO, {name}!' : 'Hello, {name}!';
        $this->console->success($message, ['name' => $name]);
    }
}
```

```bash
bash bin/console greet:hello --name=world --shout
```

Docs: https://docs.switon.dev/latest/cli

## License

MIT.
