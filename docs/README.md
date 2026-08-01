# CLI Package {#cli-package}
`switon/cli` gives Switon apps one command surface for plain command classes, option binding, help output, discovery, and shell completion. Choose it when manual operator entrypoints, maintenance scripts, and recurring jobs should look like the rest of the application instead of fragmenting into ad-hoc scripts.

**Key features:**

- **Plain command classes:** one `*Command` class is one command family; public `*Action()` methods are runnable entries.
- **Stable invocation shape:** run `command` or `command:action`, with `command --help` and bare `--help` routed to built-ins.
- **Parameter binding:** action parameters bind from named options first, then from positional values in declaration order.
- **Built-in commands:** `list`, `help`, and `completion:install` ship with the package; no generated catalog is needed.
- **Visibility metadata:** `Switon\Command\Attribute\Hidden` keeps support actions out of `list` while still runnable.
- **AI/tooling metadata:** `Switon\Command\Attribute\Tool` attaches a stable description for tool registries.

## Installation {#installation}

**Requirements:** PHP 8.3+

```bash
composer require switon/cli
```

The skeleton ships `bin/console`. Apps without a skeleton can boot the CLI kernel from a small entry script — see [Fallback entry](#fallback-entry).

## Quick Start {#quick-start}

Put a `*Command` class in a scanned command path and run it through `bin/console`.

```php
namespace App\Command;

use Switon\Core\Attribute\Autowired;
use Switon\Core\ConsoleInterface;

class ReportCommand
{
    #[Autowired] protected ConsoleInterface $console;

    /**
     * Show one daily report summary.
     *
     * @param string $date Report date in Y-m-d format.
     */
    public function dailyAction(string $date = '2026-05-03'): void
    {
        $this->console->success('Report ready for {date}.', ['date' => $date]);
    }

    /**
     * Rebuild one snapshot.
     *
     * @param int $id Snapshot id.
     * @param bool $force Rebuild even when the snapshot looks fresh.
     */
    public function rebuildAction(int $id, bool $force = false): void
    {
        $this->console->info('Rebuilt snapshot {id}.', ['id' => $id]);
    }
}
```

```bash
bash bin/console list
bash bin/console report:daily --date=2026-05-03
bash bin/console report:rebuild --id=42 --force
bash bin/console report:daily --help
```

### Fallback entry {#fallback-entry}

Apps without `bin/console` can bootstrap the same kernel from a small PHP entry (for example `switon.php`):

```php
require __DIR__ . '/vendor/autoload.php';

(new Switon\Cli\Kernel())->start();
```

Invoke the script directly, e.g. `php switon.php list`.

## Core Concepts {#core-concepts}

CLI stays predictable with four rules: command shape, action routing, discovery, and visibility.

### Shape and routing {#shape-and-routing}

Class name maps to command name; method name maps to action name.

- `ReportCommand` -> `report`; `BackupDatabaseCommand` -> `backup-database`.
- `defaultAction()` -> bare `command`; `createAction()` -> `command:create`; `createBackupAction()` -> `command:create-backup`.
- Empty argv and `--help` route to `list`. `command --help` and `command:action --help` route to `help`.

### Discovery {#discovery}

Application commands come from `App\Command\*Command` and `App\Areas\<Area>\Command\*Command`. Package commands register in `composer.json` under `extra.switon.commands` and are read through `switon/composer-extra`. The two sources merge into one resolver.

### Parameter binding {#parameter-binding}

Named options bind first, then remaining positional values fill in declaration order.

```php
class UserCommand
{
    public function createAction(string $email, string $name, string $role = 'user'): void
    {
    }
}
```

```bash
bash bin/console user:create --email=john@example.com --name=John
bash bin/console user:create john@example.com John
bash bin/console user:create --role=admin john@example.com John
```

Use `Switon\Command\Attribute\ShortOption` to disambiguate when two parameters would collide on the same first-letter alias.

### Visibility {#visibility}

`#[Hidden]` keeps a command or action out of the default `list` output. The action is still runnable when invoked by name.

```php
use Switon\Command\Attribute\Hidden;

class ReportCommand
{
    #[Hidden]
    public function rebuildIndexAction(): void
    {
    }
}
```

`bash bin/console list --all` includes hidden entries.

### Built-in tooling {#built-in-tooling}

- `bash bin/console list` and `bash bin/console list --json` for human and machine inventories.
- `bash bin/console help <command>[:<action>]` for one-action help; `--help` reaches the same renderer.
- `bash bin/console completion:install` writes the shell completion script; `completion:check` verifies it.
- Inject `Switon\Core\ConsoleInterface` for output and prompts; inject `Switon\Console\ProgressBarInterface` for progress bars.

## Keep in Mind {#keep-in-mind}

- `defaultAction()` keeps the bare `command` invocation available; without it, `command` alone falls back to help.
- Hidden commands are not missing commands. Run `list --all` before searching the codebase.
- AI/tooling metadata via `#[Tool]` is an overlay; it does not change CLI authoring or invocation.

## Next {#next}

- **To wire a service into the console:** [Add CLI](../../../docs/en/guides/add-cli.md#add-cli)
- **To run scheduled or cron-style work:** [Schedule Package](../../schedule/docs/README.md)
- **To bootstrap kernels and config:** [Kernel Package](../../kernel/docs/README.md)
- **For common questions and troubleshooting:** [FAQ](faq.md#cli-faq)
- **For trade-offs and rationale:** [Design Rationale](design.md#design-rationale)
