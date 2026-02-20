# Bin Script Conventions

This directory contains developer tooling scripts for build, packaging, release, and local validation workflows.

## Core Rules

1. Use Symfony `Process` via shared wrappers:
   - `FernleafSystems\ShieldPlatform\Tooling\Process\ProcessRunner`
   - `FernleafSystems\ShieldPlatform\Tooling\PluginPackager\CommandRunner`
2. Build command invocations as argument arrays, not shell command strings.
3. Use Symfony `Path` utilities for path handling:
   - `Path::normalize()` for user input and environment paths
   - `Path::join()` for path construction
4. Avoid custom path-join/path-normalize helpers in script files unless there is a hard requirement.
5. Avoid direct `exec()`, `passthru()`, `proc_open()`, and `shell_exec()` in bin PHP scripts.

## Windows-Safe Invocation Rules

1. Do not pass absolute Windows host paths to Bash scripts when avoidable.
2. Prefer running from a known working directory and passing repo-relative script paths (for example `./bin/run-docker-tests.sh`).
3. If Bash is required on Windows, resolve it explicitly when needed (for example `Program Files/Git/bin/bash.exe`) rather than relying on ambiguous `PATH` resolution.
4. Use `cmd /c` only when you need Windows shell built-ins or `.cmd` launcher behavior.
5. If a Git-Bash shell script invokes native Windows PHP, convert POSIX paths with `cygpath -m` before passing file/path arguments to PHP.

## Script Review Checklist

1. Command execution uses `ProcessRunner`/`CommandRunner`.
2. No shell-string construction for command execution.
3. Paths use `Path::normalize()`/`Path::join()` consistently.
4. Script works when project root contains spaces.
5. Script behavior is deterministic on Windows and Unix-like environments.

## Current Deviations To Track

1. `bin/run-playground-local.php` currently uses direct shell execution (`passthru()`/`proc_open()`) and custom `normalizePath()`/`pathJoin()` helpers.
2. Consider migrating `bin/run-playground-local.php` to `ProcessRunner` and Symfony `Path` for consistency and cross-platform robustness.
