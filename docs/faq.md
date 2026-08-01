# CLI FAQ {#cli-faq}
Short answers for authoring, discovery, and CLI runtime issues.

> **Prerequisites:** [CLI Package](README.md)

## Getting Started {#getting-started}

### How do I add a command? {#add-command}

Create an `*Command` class with public `*Action()` methods in `App\Command` (or an Areas command path) and run it through `bin/console`. See [Quick Start](README.md#quick-start).

### How do I make the bare `command` work? {#bare-command}

Add `defaultAction()` to the command class. The bare `command` invocation routes there. See [Shape and routing](README.md#shape-and-routing).

### How do options reach my action? {#options-into-action}

Named options bind first, then positional values fill the rest in declaration order. See [Parameter binding](README.md#parameter-binding).

### Can I hide a support command? {#hide-command}

Yes. Put `#[Switon\Command\Attribute\Hidden]` on the class or action; `list --all` still surfaces it.

### Why is my command missing from `list`? {#missing-from-list}

It is usually undiscovered or hidden. Check the scan path and `extra.switon.commands` first, then run `bash bin/console list --all` to confirm. See [Discovery](README.md#discovery).

## Output and Tooling {#output-and-tooling}

### How do I print lines or ask for input? {#console-input-output}

Inject `Switon\Core\ConsoleInterface` and call its output and prompt methods.

### How do I add a progress bar? {#progress-bar}

Inject `Switon\Console\ProgressBarInterface`.

### How do I enable shell completion? {#shell-completion}

Run `bash bin/console completion:install` to write the shell completion script, then verify with `bash bin/console completion:check`.

### What does `#[Tool]` change about the command? {#tool-metadata}

Nothing about invocation. It attaches a short tooling description for AI/tool registries that read it. CLI authoring stays the same.
