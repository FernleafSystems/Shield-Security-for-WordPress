# Operator CLI workflow

## Purpose

Provide a small, transparent local interface for repeatable release and packaging actions without requiring operators to remember Composer and PHP command syntax.

## Command surface

```text
php bin/shield operator
php bin/shield operator:package-svn
php bin/shield operator:prepare-release
php bin/shield operator:build-zip
```

`operator` is the interactive menu. Each `operator:*` command owns one focused workflow, and the menu delegates to those commands rather than reproducing their execution logic.

## Initial workflows

1. Package the plugin to an existing directory for WordPress.org SVN.
2. Prepare a release version.
3. Build a distributable ZIP.

The package target must exist as a directory. No SVN metadata validation is required in the initial implementation.

## Underlying command mappings

The action commands are interactive wrappers only. They must delegate to the existing scripts and Composer scripts without duplicating their packaging or release logic.

| Operator action | Underlying command |
|---|---|
| Package for SVN | `composer package-plugin -- --output=<existing-directory>` |
| Prepare release | `php bin/prepare-release.php --version=<version> --release-timestamp=<timestamp> --build=<build>` |
| Build ZIP | `composer build-zip` |

For release preparation, the operator command generates a current Unix timestamp when the operator accepts the default, then shows that exact timestamp in the previewed command.

## Operator interaction

Each action:

1. Prompts only for its required values and presents remembered values as defaults.
2. Prints the exact underlying Composer or PHP command.
3. Requires explicit confirmation.
4. Writes the selected values to the local state file immediately after confirmation and before starting the underlying process.
5. Prints clear progress, the resulting exit status, and the state-file path.

There is no non-interactive or confirmation-bypass mode initially.

## Remembered values

State is stored in the ignored repository-local file:

```text
tmp/operator-state.json
```

It should include at least the most recent package output directory and release version. A release timestamp defaults to a newly generated current timestamp rather than a previously stored timestamp; build defaults to `auto`. ZIP creation retains the current timestamped `builds/` output behaviour and requires no new initial prompt.

The directory and release version appear as prompt defaults: press Enter to reuse a value, or type its replacement. Both values are retained in `inputs` across confirmed actions, including ZIP builds, alongside the current action's answers. The top-level `action` and `command` describe the latest confirmed operation. Values are saved before execution, even if that operation subsequently fails; declining or cancelling leaves existing state unchanged.

Existing state files are read directly. Missing, unreadable, or malformed state supplies no remembered defaults; invalid or blank fields are ignored individually. Without a saved version, the configured plugin version is suggested. An accepted saved directory goes through the same validation as a typed directory; if it no longer exists or is inside the project, enter a valid replacement when prompted again. Removing the repository-local state file forgets the saved values.
