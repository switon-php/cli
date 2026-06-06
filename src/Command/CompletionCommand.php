<?php

declare(strict_types=1);

namespace Switon\Cli\Command;

use ReflectionMethod;
use Switon\Cli\RouterInterface;
use Switon\Command\Attribute\Hidden;
use Switon\Command\CommandDiscoveryInterface;
use Switon\Command\CommandInspectorInterface;
use Switon\Console\Colors;
use Switon\Core\Attribute\Autowired;
use Switon\Core\ConsoleInterface;
use Switon\Core\FilesystemInterface;
use Switon\Core\MakerInterface;
use Switon\Core\Naming;
use ReflectionException;
use ReflectionNamedType;
use Throwable;

use function array_keys;
use function array_map;
use function array_slice;
use function array_values;
use function basename;
use function count;
use function dirname;
use function explode;
use function getenv;
use function implode;
use function in_array;
use function is_dir;
use function is_writable;
use function method_exists;
use function preg_match;
use function sprintf;
use function str_contains;
use function str_replace;
use function str_split;
use function str_starts_with;
use function stripos;
use function strrpos;
use function substr;

/**
 * Generate and install shell completion scripts.
 *
 * Road-signs:
 * - <code>install</code> and <code>check</code> are user-facing
 * - <code>complete</code> is the shell completion protocol entry
 * - completion candidates come from command discovery and action inspection
 *
 * Guidance: Expose <code>completion:install</code> and <code>completion:check</code> in user docs; keep <code>completion:complete</code> as an internal shell-facing entry.
 *
 * @see \Switon\Command\CommandDiscoveryInterface Command index
 * @see \Switon\Command\CommandInspectorInterface Action inspection
 * @see \Switon\Core\FilesystemInterface File completion boundary
 * @see \Switon\Core\Naming Action naming conversion
 */
#[Hidden]
class CompletionCommand
{
    #[Autowired] protected ConsoleInterface $console;
    #[Autowired] protected CommandDiscoveryInterface $commandDiscovery;
    #[Autowired] protected CommandInspectorInterface $commandInspector;
    #[Autowired] protected FilesystemInterface $filesystem;
    #[Autowired] protected MakerInterface $maker;
    #[Autowired] protected RouterInterface $router;

    protected function isWindows(): bool
    {
        return DIRECTORY_SEPARATOR === '\\';
    }

    /**
     * Format user paths for CLI output (e.g. /Users/x/... -> ~/...).
     */
    protected function formatPathForDisplay(string $home, string $path): string
    {
        if ($path === '') {
            return '';
        }

        if ($home === '') {
            return $path;
        }

        $homeTrim = rtrim($home, '/');
        if ($path === $homeTrim) {
            return '~';
        }

        if (str_starts_with($path, $homeTrim . '/')) {
            return '~' . substr($path, strlen($homeTrim));
        }

        return $path;
    }

    /**
     * Best-effort cache clear: helps newly opened zsh sessions rebuild completion tables.
     */
    protected function bestEffortClearZcompdump(): void
    {
        $home = getenv('HOME') ?: '';
        if ($home === '') {
            return;
        }

        try {
            $deletedAny = false;
            foreach ($this->filesystem->glob($home . '/.zcompdump*') as $dump) {
                $deletedAny = true;
                try {
                    $this->filesystem->delete($dump);
                } catch (Throwable) {
                    // Keep going; zsh can also recover on next compinit/restart.
                }
            }

            if ($deletedAny) {
                $this->console->success('zsh: cleared ~/.zcompdump* cache');
            } else {
                $this->console->info('zsh: no ~/.zcompdump* cache to clear');
            }
        } catch (Throwable) {
            $this->console->warning('zsh: failed to clear ~/.zcompdump* cache; restart shell if completion is stale');
        }
    }

    /**
     * Resolve command class only when command exists and is visible.
     */
    protected function getVisibleCommandClass(?string $command): ?string
    {
        if ($command === null) {
            return null;
        }

        $commandClassName = $this->commandDiscovery->discover()[$command] ?? null;
        if ($commandClassName === null) {
            return null;
        }

        if ($this->commandInspector->isHiddenCommand($commandClassName)) {
            return null;
        }

        return $commandClassName;
    }

    /** Self-check installation and auto-load status for current user. */
    public function checkAction(): int
    {
        $home = getenv('HOME') ?: '';
        if ($home === '') {
            return $this->console->error('Cannot resolve HOME for completion check.');
        }

        $bashInstalled = $this->firstExistingPath([
            $home . '/.bash_completion.d/switon',
        ]);
        $zshPrimary = $home . '/.zsh/site-functions-switon/_switon';
        $zshInstalled = $this->filesystem->exists($zshPrimary) ? $zshPrimary : null;

        $bashAuto = $this->isBashAutoloadLikely($home);
        $zshAuto = $this->isZshAutoloadLikely($home);

        $this->console->section('Completion Check');
        $this->console->writeLn('bash installed: ' . ($bashInstalled ?? 'no'));
        $this->console->writeLn('bash autoload : ' . ($bashAuto ? 'likely yes' : 'likely no'));
        $this->console->writeLn('zsh installed : ' . ($zshInstalled ?? 'no'));
        $this->console->writeLn('zsh autoload  : ' . ($zshAuto ? 'likely yes' : 'likely no'));
        $zshrc = $home . '/.zshrc';
        $hasBind = false;
        if ($this->filesystem->exists($zshrc)) {
            try {
                $hasBind = str_contains($this->filesystem->read($zshrc), 'switon-cli-completion-bind');
            } catch (Throwable) {
                // unreadable ~/.zshrc — leave $hasBind false
            }
        }
        $this->console->writeLn('zsh php bind  : ' . ($hasBind ? 'present in ~/.zshrc' : 'missing (php may use system _php)'));

        if ($bashInstalled !== null && !$bashAuto) {
            $this->console->warning('bash may not auto-load in new shells.');
            $this->console->writeLn("Fix: source $bashInstalled now, then add equivalent source/load in your bash startup.");
        }
        if ($zshInstalled !== null && !$zshAuto) {
            $this->console->warning('zsh may not auto-load in new shells.');
            $this->console->writeLn("Fix: ensure '" . dirname($zshInstalled) . "' is in fpath, then run autoload -U compinit && compinit.");
        }

        if ($bashInstalled === null && $zshInstalled === null) {
            return $this->console->error('No completion scripts found. Run completion:install first.');
        }

        $this->console->success('Completion check finished.');
        return ConsoleInterface::SUCCESS;
    }

    /** Returns available action names for a command. */
    /**
     * Returns available action names for a command.
     *
     * @return list<string>
     */
    protected function getActions(?string $command): array
    {
        $commandClassName = $this->getVisibleCommandClass($command);
        if ($commandClassName === null) {
            return [];
        }

        $actions = array_keys($this->commandInspector->getActions($commandClassName));
        $actionNames = array_map(static fn (string $action): string => Naming::kebab($action), $actions);

        if ($command === null || $command === '') {
            return $actionNames;
        }

        $filtered = [];
        foreach ($actionNames as $actionName) {
            // Avoid redundant form such as "db:db" in action completion.
            if ($actionName === $command) {
                continue;
            }
            $filtered[] = $actionName;
        }

        return $filtered;
    }

    /** Returns option names inferred from command action parameters. */
    /**
     * Returns option names inferred from command action parameters.
     *
     * @return list<string>
     */
    protected function getArgumentNames(?string $command, ?string $action): array
    {
        if ($command === null || $action === null) {
            return [];
        }

        $commandClassName = $this->getVisibleCommandClass($command);
        if ($commandClassName === null) {
            return [];
        }

        $action = Naming::pascal($action) . 'Action';
        if (!method_exists($commandClassName, $action)) {
            return [];
        }

        $arguments = [];
        foreach ((new ReflectionMethod($commandClassName, $action))->getParameters() as $rParameter) {
            $arguments[] = '--' . str_replace('_', '-', $rParameter->name);
        }

        return $arguments;
    }

    /** Returns completion candidates for a specific argument. */
    /**
     * Returns completion candidates for a specific argument.
     *
     * @return list<string>
     */
    protected function getArgumentValues(?string $command, ?string $action, string $argumentName, string $current): array
    {
        if ($command === null || $action === null) {
            return [];
        }

        $commandClassName = $this->getVisibleCommandClass($command);
        if ($commandClassName === null) {
            return [];
        }

        $argument_values = [];
        $completionMethod = Naming::camel($action) . 'Completion';
        if (method_exists($commandClassName, $completionMethod)) {
            try {
                $argument_values = $this->maker->make($commandClassName)->$completionMethod($argumentName, $current);
            } catch (Throwable $e) {
                // Completion method failed - fall through to file completion
            }
        }

        // Built-in defaults for common scalar parameter types.
        if ($argument_values === []) {
            $argument_values = $this->getDefaultArgumentValues($commandClassName, $completionMethod, $argumentName);
        }

        if ($current !== '' && $argument_values === []) {
            if (str_contains($current, '/')) {
                $slashPos = strrpos($current, '/');
                $dir = $slashPos === false ? '' : substr($current, 0, $slashPos);
                if ($dir === '') {
                    $dir = '/';
                }
                foreach ($this->filesystem->glob($dir . '/*') as $item) {
                    $argument_values[] = $this->filesystem->isDir($item) ? $item . '/' : $item;
                }
            } elseif (preg_match('#^\w+$#', $current)) {
                foreach ($this->filesystem->glob('./*') as $item) {
                    $argument_values[] = $this->filesystem->isDir($item) ? basename($item) . '/' : basename($item);
                }
            }
        }

        return $argument_values;
    }

    /**
     * Returns built-in candidates for argument types when command does not provide custom completion.
     *
     * @return list<string>
     */
    protected function getDefaultArgumentValues(string $commandClassName, string $completionMethod, string $argumentName): array
    {
        $actionMethod = str_replace('Completion', 'Action', Naming::pascal($completionMethod));
        if (!method_exists($commandClassName, $actionMethod)) {
            return [];
        }

        $parameterName = str_replace('-', '_', substr($argumentName, 2));
        try {
            $method = new ReflectionMethod($commandClassName, $actionMethod);
        } catch (ReflectionException $e) {
            return [];
        }

        foreach ($method->getParameters() as $parameter) {
            if ($parameter->name !== $parameterName) {
                continue;
            }

            $type = $parameter->getType();
            if ($type instanceof ReflectionNamedType && $type->getName() === 'bool') {
                return ['true', 'false', '1', '0', 'yes', 'no'];
            }
            break;
        }

        return [];
    }

    /** Filters words by current completion input. */
    /**
     * Filters words by current completion input.
     *
     * @param list<string> $words
     *
     * @return list<string>
     */
    protected function filterWords(array $words, string $current): array
    {
        if ($current === '') {
            return $words;
        }

        $filtered_words = [];

        foreach ($words as $word) {
            if (str_starts_with($word, $current)) {
                $filtered_words[] = $word;
            }
        }

        if ($filtered_words !== []) {
            return $filtered_words;
        }

        foreach ($words as $word) {
            if (stripos($word, $current) !== false) {
                $filtered_words[] = $word;
            }
        }

        if ($filtered_words !== []) {
            return $filtered_words;
        }

        if (preg_match('#^\w#', $current)) {
            $prefix = $current[0];
        } else {
            $prefix = preg_match('#^\W+\w#', $current, $match) ? $match[0] : '';
        }

        $chars = str_split($current);

        foreach ($words as $word) {
            if (!str_starts_with($word, $prefix)) {
                continue;
            }

            $pos = 0;
            foreach ($chars as $char) {
                if (($pos = stripos($word, $char, $pos)) === false) {
                    break;
                }
            }

            if ($pos !== false) {
                $filtered_words[] = $word;
            }
        }

        return $filtered_words;
    }

    /**
     * Build first-token command candidates.
     *
     * Only visible actions are exposed as "command:action" forms.
     * Commands without visible actions are skipped.
     *
     * @return list<string>
     */
    protected function getCommandEntryCandidates(bool $includeInlineAction = true): array
    {
        $words = [];

        foreach ($this->commandDiscovery->discover() as $commandName => $commandClassName) {
            if ($this->commandInspector->isHiddenCommand($commandClassName)) {
                continue;
            }

            $actions = $this->getActions($commandName);
            if ($actions === []) {
                continue;
            }

            if (!$includeInlineAction) {
                // Bash: never emit bare "command:" — only full "command:action" (avoids readline prefix doubling like db:db:).
                if ($actions === ['default']) {
                    if (!in_array($commandName, $words, true)) {
                        $words[] = $commandName;
                    }
                    continue;
                }
                if (in_array('default', $actions, true) && !in_array($commandName, $words, true)) {
                    $words[] = $commandName;
                }
                foreach ($actions as $actionName) {
                    if ($actionName === 'default') {
                        continue;
                    }
                    if ($actionName === $commandName) {
                        if (!in_array($commandName, $words, true)) {
                            $words[] = $commandName;
                        }
                        continue;
                    }
                    $words[] = $commandName . ':' . $actionName;
                }
                continue;
            }

            $hasNonDefault = false;
            foreach ($actions as $actionName) {
                if ($actionName !== 'default') {
                    $hasNonDefault = true;
                    break;
                }
            }
            // Inline form: avoid mixing bare "command" with "command:action" for multi-action commands.
            if (!$hasNonDefault && !in_array($commandName, $words, true)) {
                $words[] = $commandName;
            }

            if ($actions === ['default']) {
                continue;
            }

            foreach ($actions as $actionName) {
                if ($actionName === 'default') {
                    continue;
                }
                // Avoid redundant token like "cache:cache"; prefer plain "cache".
                if ($actionName === $commandName) {
                    if (!in_array($commandName, $words, true)) {
                        $words[] = $commandName;
                    }
                    continue;
                }
                $words[] = $commandName . ':' . $actionName;
            }
        }

        return $words;
    }

    /** Print completion candidates for the current shell input. */
    public function completeAction(): int
    {
        $params = $this->router->getParams();
        if ($params === []) {
            $params = array_slice($GLOBALS['argv'], 3);
        }
        $position = (int)($params[0] ?? 0);
        $arguments = array_slice($params, 1);

        $count = count($arguments);
        $completionShell = (string)(getenv('SWITON_COMPLETION_SHELL') ?: '');
        $includeInlineAction = $completionShell !== 'bash';
        $alreadyFiltered = false;

        $command = null;
        $hasInlineAction = false;
        if ($count > 1) {
            $command = $arguments[1];
            $hasInlineAction = str_contains($command, ':');
        }

        $action = null;
        if ($count > 2) {
            $action = $arguments[2];
            if ($action !== '' && str_starts_with($action, '-')) {
                $action = 'default';
            }
        }

        // Normalize "command:action" token form used by CLI routing.
        if ($command !== null && str_contains($command, ':')) {
            [$commandName, $actionName] = explode(':', $command, 2);
            $command = $commandName;
            if ($action === null || $action === '' || $action === 'default') {
                $action = $actionName;
            }
        }

        $previous = $position > 0 ? ($arguments[$position - 1] ?? null) : null;

        $current = $arguments[$position] ?? '';

        if ($position === 1 && str_contains($current, ':')) {
            [$inlineCommand, $inlineActionCurrent] = explode(':', $current, 2);
            if ($inlineCommand !== '' && $this->getVisibleCommandClass($inlineCommand) !== null) {
                $actions = $this->getActions($inlineCommand);
                $actions = $this->filterWords($actions, $inlineActionCurrent);
                $words = array_map(static fn (string $actionName): string => $inlineCommand . ':' . $actionName, $actions);
                $alreadyFiltered = true;
            } else {
                $words = $this->getCommandEntryCandidates($completionShell === 'bash' ? true : $includeInlineAction);
            }
        } elseif (str_starts_with($current, '--') && str_contains($current, '=')) {
            $segments = explode('=', $current, 2);
            $argumentName = $segments[0];
            $argumentCurrent = $segments[1] ?? '';
            $words = $this->getArgumentValues($command, $action, $argumentName, $argumentCurrent);
        } elseif ($position === 1) {
            $words = $this->getCommandEntryCandidates($includeInlineAction);
        } elseif ($position === 2 && $hasInlineAction && $action !== null && $action !== '' && $action !== 'default') {
            $words = $this->getArgumentNames($command, $action);
        } elseif ($position === 2) {
            $words = $this->getActions($command);
        } elseif ($previous !== null && str_starts_with($previous, '-') && !str_starts_with($current, '-')) {
            // zsh might pass an extra empty token after just `-- `.
            // When the previous token is only "--", treat it as "start typing option name"
            // instead of trying to complete values for a non-existent argument name.
            $words = $previous === '--'
                ? $this->getArgumentNames($command, $action)
                : $this->getArgumentValues($command, $action, $previous, $current);
        } else {
            $words = $this->getArgumentNames($command, $action);
            foreach ($words as $k => $word) {
                if (in_array($word, $arguments, true)) {
                    unset($words[$k]);
                }
            }
            $words = array_values($words);
        }

        if ($alreadyFiltered) {
            // Already filtered in the inline command:action branch.
        } elseif (str_starts_with($current, '--') && str_contains($current, '=')) {
            $segments = explode('=', $current, 2);
            $argumentPrefix = $segments[0] . '=';
            $argumentCurrent = $segments[1] ?? '';
            $words = $this->filterWords($words, $argumentCurrent);
            $words = array_map(static fn (string $word): string => $argumentPrefix . $word, $words);
        } else {
            $words = $this->filterWords($words, $current);
        }

        $words = array_values(array_unique($words));

        $this->console->writeLn(implode(' ', $words));

        return 0;
    }

    /** Install completion scripts for available shells. */
    public function installAction(): int
    {
        if ($this->isWindows()) {
            return $this->console->error('Windows system is not support shell completion install!');
        }

        $installed = [];

        if ($this->isShellAvailable('bash')) {
            if ($this->installBashScript() === 0) {
                $installed[] = 'bash';
            }
        }

        if ($this->isShellAvailable('zsh')) {
            if ($this->installZshScript() === 0) {
                $installed[] = 'zsh';
            }
        }

        if ($installed === []) {
            return $this->console->error('Completion install failed for all detected shells.');
        }

        $this->console->success('Completion installed for: {shells}', ['shells' => implode(', ', $installed)]);
        return 0;
    }

    /** Install Bash completion script. */
    public function installBashAction(): int
    {
        if ($this->isWindows()) {
            return $this->console->error('Windows system is not support bash completion!');
        }

        return $this->installBashScript();
    }

    /** Install Zsh completion script. */
    public function installZshAction(): int
    {
        if ($this->isWindows()) {
            return $this->console->error('Windows system is not support zsh completion!');
        }

        $result = $this->installZshScript();
        if ($result !== 0) {
            return $result;
        }

        // Step 3 (best-effort): clear zcompdump so newly opened shells rebuild tables.
        $this->bestEffortClearZcompdump();

        return 0;
    }

    /** Write Bash completion script to user completion directory. */
    protected function installBashScript(): int
    {
        $content = <<<'EOT'
#!/bin/bash

# Lazy-load bash-completion helpers so ":" is not a spurious word break (readline vs PHP argv stay aligned).
if ! declare -F _get_comp_words_by_ref >/dev/null 2>&1; then
   for _switon_bc in /opt/homebrew/etc/profile.d/bash_completion.sh /usr/local/etc/profile.d/bash_completion.sh /etc/profile.d/bash_completion.sh /usr/share/bash-completion/bash_completion /usr/local/share/bash-completion/bash_completion; do
      if [[ -r "$_switon_bc" ]]; then
         # shellcheck disable=SC1090
         . "$_switon_bc" >/dev/null 2>&1 || true
         break
      fi
   done
   unset _switon_bc
fi

_switon_bash_apply_nospace_if_colon_candidate() {
   local _candidate
   for _candidate in "$@"; do
      if [[ "$_candidate" == *: ]]; then
         compopt -o nospace 2>/dev/null || true
         break
      fi
   done
}

_switon_bash_php_candidates() {
  # Suggest directories (with trailing "/") + direct *.php matches for the current prefix.
  # This keeps yml out of the script-argument completion while still enabling subdir navigation.
  local _cur="${1}"
  local _dirprefix=""
  local _nameprefix=""
  local _base=""
  local _fileglob=""
  local _d _f

  COMPREPLY=()

  if [[ "${_cur}" == */ ]]; then
    _dirprefix="${_cur}"
    _nameprefix=""
    for _d in $(compgen -G "${_dirprefix}*/" 2>/dev/null); do
      COMPREPLY+=("${_d}")
    done
    _fileglob="${_dirprefix}*.php"
    for _f in $(compgen -G "${_fileglob}" 2>/dev/null); do
      COMPREPLY+=("${_f}")
    done
    return 0
  fi

  if [[ "${_cur}" == */* ]]; then
    _dirprefix="${_cur%/*}/"
    _nameprefix="${_cur##*/}"
  else
    _nameprefix="${_cur}"
  fi

  # Directories starting with basename prefix.
  for _d in $(compgen -G "${_dirprefix}${_nameprefix}*/" 2>/dev/null); do
    COMPREPLY+=("${_d}")
  done

  # *.php files matching basename prefix / extension prefix.
  if [[ "${_nameprefix}" == *.* ]]; then
    _base="${_nameprefix%.*}"
    _fileglob="${_dirprefix}${_base}*.php"
  else
    _fileglob="${_dirprefix}${_nameprefix}*.php"
  fi
  for _f in $(compgen -G "${_fileglob}" 2>/dev/null); do
    COMPREPLY+=("${_f}")
  done

  return 0
}

_switon(){
   local _entry="${COMP_WORDS[0]}"
   local cur prev words cword
   local -a words
   if declare -F _get_comp_words_by_ref >/dev/null 2>&1; then
      # Align with readline: exclude ":" (and "=") from word breaks so "db:info" is one token (Symfony-style).
      _get_comp_words_by_ref -n := cur prev words cword
      COMPREPLY=( $(SWITON_COMPLETION_SHELL=bash XDEBUG_MODE=off "$_entry" completion:complete "$cword" "${words[@]}" 2>/dev/null) )
      if declare -F __ltrim_colon_completions >/dev/null 2>&1; then
         __ltrim_colon_completions "$cur"
      fi
   else
      local _prefix="${COMP_LINE:0:COMP_POINT}"
      local _cword
      local -a _words
      read -r -a _words <<<"$_prefix"
      if [[ "$_prefix" == *" " ]]; then
         _words+=("")
      fi
      _cword=$((${#_words[@]} - 1))
      COMPREPLY=( $(SWITON_COMPLETION_SHELL=bash XDEBUG_MODE=off "$_entry" completion:complete "$_cword" "${_words[@]}" 2>/dev/null) )
   fi
   _switon_bash_apply_nospace_if_colon_candidate "${COMPREPLY[@]}"
   return 0;
}

_switon_php(){
   # Stock _php is replaced by this function; must delegate filename completion when not switon.php.
   local cur prev words cword
   local -a words
   if declare -F _get_comp_words_by_ref >/dev/null 2>&1; then
      _get_comp_words_by_ref -n := cur prev words cword
   else
      words=("${COMP_WORDS[@]}")
      cword="${COMP_CWORD}"
      cur="${COMP_WORDS[COMP_CWORD]}"
   fi

   local _php="${words[0]:-php}"
   # Build a correct *.php glob from current partial input.
   # - Keep directory prefix (if any).
   # - If user typed an extension part like "switon.p", suggest "switon*.php".
   # - If user typed "switon." treat it as "switon*.php".
   local _switon_php_glob
   _switon_php_glob="${cur}"
   if [[ "${cur}" == */* ]]; then
     local _cur_dir="${cur%/*}"
     local _cur_name="${cur##*/}"
     if [[ "${_cur_name}" == *.* ]]; then
       local _cur_base="${_cur_name%.*}"
       _switon_php_glob="${_cur_dir%/}/${_cur_base}*.php"
     else
       _switon_php_glob="${_cur_dir%/}/${_cur_name}*.php"
     fi
   else
     local _cur_name="${cur}"
     if [[ "${_cur_name}" == *.* ]]; then
       local _cur_base="${_cur_name%.*}"
       _switon_php_glob="${_cur_base}*.php"
     else
       _switon_php_glob="${_cur_name}*.php"
     fi
   fi

   if (( cword < 1 )); then
      COMPREPLY=()
      return 0
   fi

   if (( cword == 1 )); then
      case "${words[1]}" in
         switon.php|./switon.php|*/switon.php)
            ;;
         *)
            compopt +o nospace 2>/dev/null || true
            _switon_bash_php_candidates "${cur}"
            return 0
            ;;
      esac
   fi

   local _script="${words[1]}"
   case "$_script" in
      switon.php|./switon.php|*/switon.php)
         ;;
      *)
         compopt +o nospace 2>/dev/null || true
         _switon_bash_php_candidates "${cur}"
         return 0
         ;;
   esac

   if [[ "$_script" != /* && "$_script" != */* && -f "./$_script" ]]; then
      _script="./$_script"
   elif [[ "$_script" != /* ]]; then
      local _dir="${PWD}"
      while [[ "$_dir" != "/" && "$_dir" != "." ]]; do
         if [[ -f "$_dir/$_script" ]]; then
            _script="$_dir/$_script"
            break
         fi
         _dir="${_dir%/*}"
      done
   fi

   if [[ ! -f "$_script" ]]; then
      if (( cword == 1 )); then
         compopt +o nospace 2>/dev/null || true
         _switon_bash_php_candidates "${cur}"
      else
         COMPREPLY=()
      fi
      return 0
   fi

   if (( cword == 1 )); then
      COMPREPLY=()
      return 0
   fi

   words=("${words[@]:1}")
   cword=$((cword - 1))
   COMPREPLY=( $(SWITON_COMPLETION_SHELL=bash XDEBUG_MODE=off "$_php" "$_script" completion:complete "$cword" "${words[@]}" 2>/dev/null) )
   if declare -F __ltrim_colon_completions >/dev/null 2>&1; then
      __ltrim_colon_completions "$cur"
   fi
   # If the current word already contains an inline form prefix like "db:",
   # completion candidates may be action-only (e.g. "info").
   # Bash will replace the whole current word, so we must re-attach the prefix.
   if [[ "${cur}" == *:* ]]; then
     local _colon_prefix="${cur%%:*}:"
     local _colon_count=0
     local _i _ch
     for ((_i=0; _i<${#cur}; _i++)); do
       _ch="${cur:${_i}:1}"
       if [[ "${_ch}" == ':' ]]; then
         ((_colon_count=_colon_count+1))
       fi
     done
     local _out=()
     local _item
     if (( _colon_count == 1 )); then
       # e.g. cur="db:" or cur="db:pi" => candidate might be action-only like "ping"
       for _item in "${COMPREPLY[@]}"; do
         if [[ "${_item}" == *:* ]]; then
           _out+=("${_item}")
         else
           _out+=("${_colon_prefix}${_item}")
         fi
       done
       COMPREPLY=("${_out[@]}")
     fi
   fi
   _switon_bash_apply_nospace_if_colon_candidate "${COMPREPLY[@]}"
   return 0;
}

complete -F _switon switon ./switon bin/console ./bin/console
complete -F _switon_php php
EOT;

        $home = getenv('HOME') ?: '';
        if ($home === '') {
            return $this->console->error('bash: HOME is not set; cannot install completion into user directories.');
        }

        // Fixed install path for Switon: user-scoped bash-completion directory.
        $candidates = [
            $home . '/.bash_completion.d/switon',
        ];

        $installedBashPath = null;
        $installError = null;
        $result = $this->writeCompletionScriptToCandidates($candidates, $content, 'bash', $installedBashPath, $installError);
        if ($result !== ConsoleInterface::SUCCESS) {
            return $this->console->error('bash: failed to install completion script: {reason}', [
                'reason' => $installError ?? 'unknown error',
            ]);
        }

        $installedPath = (string)$installedBashPath;
        $displayInstalledPath = $this->formatPathForDisplay($home, $installedPath);
        $this->console->writeLn(
            'bash: completion script installed at ' . $this->console->colorize(
                $displayInstalledPath,
                Colors::FC_LIGHT_CYAN | Colors::AT_BOLD
            )
        );

        $ok = $this->appendBashrcSwitonCompletionLoad($home, $installedPath);
        if (!$ok) {
            return $this->console->error('bash: failed to patch ~/.bashrc; completion may not load in new shells.');
        }
        $this->console->writeLn(
            'bash: patched ' . $this->console->colorize('~/.bashrc', Colors::FC_LIGHT_CYAN | Colors::AT_BOLD) . ' (loader block)'
        );

        $this->console->info('bash: open a new shell or run `source ~/.bashrc`');
        return ConsoleInterface::SUCCESS;
    }

    /** Write Zsh completion script to user completion directory. */
    protected function installZshScript(): int
    {
        $mainContent = <<<'EOT'
compdef _switon switon
compdef _switon bin/console
compdef _switon ./bin/console
compdef _switon -P '*/bin/console'
compdef _switon -P '*/switon'
_switon_zsh_prefix_lex(){ local _prefix="${BUFFER:0:$CURSOR}"; typeset -g -a _switon_prefix_tokens; _switon_prefix_tokens=("${(z)_prefix}"); typeset -g _switon_prefix_last_char; _switon_prefix_last_char="${_prefix[-1]:-}"; }
_switon(){ if [[ "${words[1]:t}" =~ '^php([0-9]+(\.[0-9]+)*)?$' ]]; then _switon_php; return $?; fi; _switon_zsh_prefix_lex; local -a _allWords=("${_switon_prefix_tokens[@]}"); local entry="${_allWords[1]:-${words[1]}}"; (( ${#_allWords[@]} == 0 )) && _allWords=("${entry}"); [[ "${_switon_prefix_last_char:-}" == ' ' || "${_switon_prefix_last_char:-}" == $'\t' ]] && _allWords+=(''); local _pos=$(( ${#_allWords[@]} - 1 )); (( _pos<1 )) && _pos=1; if [[ "${entry}" != /* && ! -x "${entry}" ]]; then local dir="${PWD}"; while [[ "${dir}" != "/" && "${dir}" != "." ]]; do [[ -x "${dir}/${entry}" ]] && { entry="${dir}/${entry}"; break; }; dir="${dir%/*}"; done; fi; local out; out=$(XDEBUG_MODE=off "$entry" completion:complete "$_pos" "${_allWords[@]}" 2>/dev/null); local -a reply; reply=(${(u)${(z)out}}); compadd -Q -- "${reply[@]}"; return 0; }
_switon_php(){ local phpbin="${words[1]}"; local script="${words[2]}"; case "$script" in switon.php|./switon.php|*/switon.php) ;; *) _files -g '*.php'; return 0;; esac; local resolved="$script"; if [[ "$resolved" != /* && "$resolved" != */* && -f "./$resolved" ]]; then resolved="${PWD}/$resolved"; elif [[ "$resolved" != /* ]]; then local dir="${PWD}"; while [[ "$dir" != "/" && "$dir" != "." ]]; do [[ -f "$dir/$script" ]] && { resolved="$dir/$script"; break; }; dir="${dir%/*}"; done; fi; [[ -f "$resolved" ]] || return 1; _switon_zsh_prefix_lex; local -a args=("${(@)_switon_prefix_tokens[2,-1]}"); (( ${#args[@]} == 0 )) && args=("${script}"); [[ "${_switon_prefix_last_char:-}" == ' ' || "${_switon_prefix_last_char:-}" == $'\t' ]] && args+=(''); if (( ${#args[@]} == 2 )); then local _last="${args[-1]:-}"; [[ "${_last}" == *:* && "${_last}" != *: ]] && args+=(''); fi; local _pos=$(( ${#args[@]} - 1 )); (( _pos<1 )) && _pos=1; local out; out=$(XDEBUG_MODE=off "$phpbin" "$resolved" completion:complete "$_pos" "${args[@]}" 2>/dev/null); local -a reply; reply=(${(u)${(z)out}}); (( ${#reply[@]} )) || return 1; compadd -Q -- "${reply[@]}"; }
EOT;

        $phpProxyContent = <<<'EOT'
#compdef php
_zsh_switon_php() {
  local s="${words[2]:-}"
  case "$s" in
    switon.php|./switon.php|*/switon.php) autoload -Uz _switon; _switon "$@";;
    *)
      # When completing the script argument (words[2]), only suggest .php files (ignore *.yml).
      # Avoid relying on a specific CURRENT index; zsh's indexing differs across scenarios.
      if [[ "${words[${CURRENT:-0}]:-}" == "${words[2]:-}" ]]; then
        _files -g '*.php'
      else
        autoload -Uz _php; _php "$@";
      fi
      ;;
  esac
}
_zsh_switon_php "$@"
EOT;

        $home = getenv('HOME') ?: '';
        if ($home === '') {
            return $this->console->error('zsh: HOME is not set; cannot install completion into user directories.');
        }

        // Fixed install dir for Switon to keep completion isolated and predictable.
        $firstInstalled = $home . '/.zsh/site-functions-switon/_switon';
        if (!$this->isWritableTargetPath($firstInstalled)) {
            return $this->console->error('zsh: No writable install path for user completion scripts.');
        }
        $installedDir = dirname($firstInstalled);
        $firstProxyInstalled = $installedDir . '/_zsh_switon_php';

        $mainError = null;
        $result = $this->writeCompletionScript($firstInstalled, $mainContent, 'zsh', $mainError);
        if ($result !== ConsoleInterface::SUCCESS) {
            return $this->console->error($mainError ?? 'zsh: failed to write completion script.');
        }
        $proxyError = null;
        $resultPhp = $this->writeCompletionScript($firstProxyInstalled, $phpProxyContent, 'zsh', $proxyError);
        if ($resultPhp !== ConsoleInterface::SUCCESS) {
            return $this->console->error($proxyError ?? 'zsh: failed to write php proxy completion script.');
        }

        $displayZshInstalled = $this->formatPathForDisplay($home, $firstInstalled);
        $displayProxyInstalled = $this->formatPathForDisplay($home, $firstProxyInstalled);

        $this->console->writeLn(
            'zsh: completion script installed at ' . $this->console->colorize(
                $displayZshInstalled,
                Colors::FC_LIGHT_CYAN | Colors::AT_BOLD
            )
        );
        $this->console->writeLn(
            'zsh: php proxy installed at ' . $this->console->colorize(
                $displayProxyInstalled,
                Colors::FC_LIGHT_CYAN | Colors::AT_BOLD
            )
        );

        $ok = $this->appendZshrcSwitonCompletionBind($home, $installedDir);
        if (!$ok) {
            return $this->console->error('zsh: failed to patch ~/.zshrc (php rebinder). Completion may require manual setup.');
        }
        $this->console->writeLn(
            'zsh: patched ' . $this->console->colorize('~/.zshrc', Colors::FC_LIGHT_CYAN | Colors::AT_BOLD) . ' (php rebinder)'
        );
        $this->console->info('zsh: open a new shell or run `source ~/.zshrc`');
        return ConsoleInterface::SUCCESS;
    }

    /**
     * Appends a ~/.zshrc block that re-binds `php` to Switon after compinit (system _php wins otherwise).
     */
    protected function appendZshrcSwitonCompletionBind(string $home, string $installedDir): bool
    {
        $zshrc = $home . '/.zshrc';
        $marker = 'switon-cli-completion-bind';
        $markerCompinit = 'switon-cli-completion-compinit';
        $installedDir = rtrim($installedDir, '/');
        $escapedInstalledDir = str_replace("'", "'\"'\"'", $installedDir);
        $block = <<<'SH'


# switon-cli-completion-bind: system Completion/Php/_php overrides Switon at compinit; re-bind after startup.
SH;
        $block .= "\n" . "if [[ -d '$escapedInstalledDir' ]]; then" . <<<'SH'
  fpath=(${fpath:#'__SWITON_ZSH_FPATH__'})
  fpath=('__SWITON_ZSH_FPATH__' $fpath)
  # switon-cli-completion-compinit: rebuild completion tables after fpath changes.
  autoload -Uz compinit
  compinit
  autoload -Uz _switon _zsh_switon_php
  # Ensure command completion is registered even if the site-function file is not sourced fully.
  compdef _switon switon
  compdef _switon bin/console
  compdef _switon ./bin/console
  compdef _switon -P '*/bin/console'
  compdef _switon -P '*/switon'
  compdef _zsh_switon_php php 2>/dev/null
fi
SH;
        $block = str_replace('__SWITON_ZSH_FPATH__', $installedDir, $block);

        try {
            $content = $this->filesystem->exists($zshrc) ? $this->filesystem->read($zshrc) : '';
        } catch (Throwable) {
            return false;
        }

        $alreadyOk = str_contains($content, $markerCompinit)
            && str_contains($content, $installedDir)
            && str_contains($content, 'compdef _switon switon');
        if ($alreadyOk) {
            return true;
        }

        if (str_contains($content, $marker)) {
            // Replace the existing block in-place (older versions may miss compinit call).
            $pattern = '/# switon-cli-completion-bind:.*?\nfi/s';
            $replaced = preg_replace($pattern, $block, $content, 1);
            if (is_string($replaced) && $replaced !== $content) {
                try {
                    $this->filesystem->write($zshrc, $replaced);
                    return true;
                } catch (Throwable) {
                    return false;
                }
            }
        }

        try {
            $this->filesystem->write($zshrc, $content . $block);
        } catch (Throwable) {
            return false;
        }

        return true;
    }

    /**
     * Appends a portable ~/.bashrc loader so Switon completion works across package managers.
     *
     * It first tries common bash-completion locations, then falls back to directly sourcing the installed Switon script.
     */
    protected function appendBashrcSwitonCompletionLoad(string $home, string $installedPath): bool
    {
        $bashrc = $home . '/.bashrc';
        $marker = 'switon-cli-completion-load';
        $escapedPath = str_replace("'", "'\"'\"'", $installedPath);
        $block = <<<BASH


# switon-cli-completion-load: portable bootstrap for bash completion + Switon fallback.
if ! shopt -oq posix; then
  [[ -r '$escapedPath' ]] && . '$escapedPath'
  [[ -r "\$HOME/.bash_completion" ]] && . "\$HOME/.bash_completion"
fi
BASH;

        $content = '';
        if ($this->filesystem->exists($bashrc)) {
            try {
                $content = $this->filesystem->read($bashrc);
            } catch (Throwable) {
                return false;
            }

            if (str_contains($content, $marker)) {
                return true;
            }
        }

        try {
            $this->filesystem->write($bashrc, $content . $block);
        } catch (Throwable) {
            return false;
        }

        return true;
    }

    /** Writes completion script file with normalized line endings. */
    protected function writeCompletionScript(string $file, string $content, string $shell, ?string &$error = null): int
    {
        $error = null;
        try {
            $dir = dirname($file);
            if (!$this->filesystem->exists($dir)) {
                $this->filesystem->mkdir($dir);
            }
            $this->filesystem->write($file, PHP_EOL === "\n" ? $content : str_replace("\r", '', $content));
            $this->filesystem->chmod($file, 0755);
        } catch (Throwable $e) {
            $error = sprintf('write %s completion script failed: %s', $shell, $e->getMessage());
            return ConsoleInterface::FAILURE;
        }

        return ConsoleInterface::SUCCESS;
    }

    /**
     * Writes completion script to the first writable candidate path.
     *
     * @param list<string> $candidates
     */
    protected function writeCompletionScriptToCandidates(array $candidates, string $content, string $shell, ?string &$installedPath, ?string &$error = null): int
    {
        $installedPath = null;
        $error = null;

        foreach ($candidates as $candidate) {
            if (!$this->isWritableTargetPath($candidate)) {
                continue;
            }

            $candidateError = null;
            $result = $this->writeCompletionScript($candidate, $content, $shell, $candidateError);
            if ($result === ConsoleInterface::SUCCESS) {
                $installedPath = $candidate;
                return ConsoleInterface::SUCCESS;
            }

            if ($candidateError !== null) {
                $error = $candidateError;
            }
        }

        $error ??= sprintf('No writable install path for %s.', $shell);
        return ConsoleInterface::FAILURE;
    }

    /** Checks whether target file path can be written by current user. */
    protected function isWritableTargetPath(string $file): bool
    {
        $dir = dirname($file);
        while ($dir !== '' && $dir !== '/' && !is_dir($dir)) {
            $parent = dirname($dir);
            if ($parent === $dir) {
                break;
            }
            $dir = $parent;
        }

        if ($dir === '') {
            return false;
        }

        return is_writable($dir);
    }

    /** Detects whether a supported shell binary is installed. */
    protected function isShellAvailable(string $shell): bool
    {
        $candidates = match ($shell) {
            'bash' => ['/bin/bash', '/usr/bin/bash', '/usr/local/bin/bash'],
            'zsh' => ['/bin/zsh', '/usr/bin/zsh', '/usr/local/bin/zsh'],
            default => [],
        };

        foreach ($candidates as $candidate) {
            if ($this->filesystem->exists($candidate)) {
                return true;
            }
        }

        return false;
    }

    /** @param list<string> $paths */
    protected function firstExistingPath(array $paths): ?string
    {
        foreach ($paths as $path) {
            if ($this->filesystem->exists($path)) {
                return $path;
            }
        }
        return null;
    }

    /** @param list<string> $files @param list<string> $needles */
    /**
     * @param list<string> $files
     * @param list<string> $needles
     */
    protected function hasAnyConfigHints(array $files, array $needles): bool
    {
        foreach ($files as $file) {
            if (!$this->filesystem->exists($file)) {
                continue;
            }
            try {
                $content = $this->filesystem->read($file);
            } catch (Throwable $e) {
                continue;
            }
            foreach ($needles as $needle) {
                if (str_contains($content, $needle)) {
                    return true;
                }
            }
        }
        return false;
    }

    /** Heuristic: whether bash startup likely auto-loads completion. */
    protected function isBashAutoloadLikely(string $home): bool
    {
        return $this->hasAnyConfigHints([
            $home . '/.bashrc',
            $home . '/.bash_profile',
            $home . '/.profile',
        ], ['bash_completion', '.bash_completion', '.bash_completion.d', 'completions/switon']);
    }

    /** Heuristic: whether zsh startup likely auto-loads completion. */
    protected function isZshAutoloadLikely(string $home): bool
    {
        return $this->hasAnyConfigHints([
            $home . '/.zshrc',
            $home . '/.zprofile',
        ], ['compinit', 'fpath', 'site-functions', '.zfunc', 'switon-cli-completion-bind']);
    }
}
