# Design Rationale {#design-rationale}
`switon/cli` keeps the manual operator surface inside the same application shape as HTTP, workers, and listeners, instead of letting it fragment into shell scripts and one-off command objects.

## See also {#see-also}

- [CLI Package](README.md) — usage, discovery, completion, and operational guidance
- [FAQ](faq.md#cli-faq) — common questions and troubleshooting
- [Command Package](https://github.com/switon-php/command/blob/main/docs/README.md) — discovery metadata and attributes
- [Console Package](https://github.com/switon-php/console/blob/main/docs/README.md) — terminal primitives behind the helpers

---

## Core Problem {#core-problem}

Every team needs commands for maintenance, repair, imports, cleanup, and operator workflows. Without a shared shape, those entrypoints drift away from the rest of the application:

- one task becomes a shell script, another becomes a command object with its own conventions;
- some reach into the container directly, others build their own option parsers;
- AI/tooling metadata grows separately from the human CLI surface.

The framework needs one manual entry boundary that uses the same DI, the same input plumbing, and the same discovery story as the rest of the app.

## Primary Goal {#primary-goal}

`switon/cli` standardizes one small command shape first, then layers help, discovery, and completion on top.

- **Benefit:** operator actions stay easy to find, review, and reuse.
- **Benefit:** simple commands take one class plus one method.
- **Cost:** larger workflow engines and remote job runners stay outside the default package.

## Core Choice {#core-choice}

One class is one command family; public `*Action()` methods are the runnable entries.

- **Routing:** `command` or `command:action`. Empty argv and `--help` go to `list`. `command --help` and `command:action --help` go to `help`.
- **Discovery:** application code uses path mappings (`App\Command\*Command`, `App\Areas\*\Command\*Command`); package code uses `extra.switon.commands` in `composer.json`.
- **Binding:** named options bind first, then remaining positional values fill in declaration order.

That keeps the public invocation contract small while leaving room for normal DI inside the command class.

## Trade-Off {#trade-off}

The package does not ship a workflow engine, a remote runner, or a full tool registry.

- **Benefit:** the mental model stays small; one class, one PHPDoc, one set of conventions.
- **Cost:** apps that need orchestration, remote execution, or rich tool metadata layer those on top of CLI rather than expecting them in the package.

## Deliberate Simplicity {#deliberate-simplicity}

Visibility, AI/tooling metadata, and completion are overlays, not separate models.

- `#[Hidden]` decides listing visibility; the command stays runnable.
- `#[Tool]` attaches a description for tooling consumers; CLI invocation does not change.
- Completion is generated from the current discovered set and reflected options, not from a frozen registry.

That keeps overlays optional: an app that does not use them still has a complete CLI.
