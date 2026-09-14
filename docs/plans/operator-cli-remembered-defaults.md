# Restore remembered defaults in the release operator CLI

Status: approved by plan validation, revision `validated-v1`, 2026-09-07. This revision is the implementation baseline. No implementation is complete.

## Validation summary

Outcome: approved. Final solution-trajectory verdict: `continue`.

Validated against the originating requests to restore remembered values through simple reuse, the operator workflow specification, the live command and routing, its tests, the installed Symfony question helper, and the repository's PHP/test configuration. A bounded independent read-only review found no blockers in the state contract or proposed coverage.

The existing class already owns prompting, validation, command construction, and state writing. The missing operation is a read; retaining two fields at its existing write call prevents cross-action loss. A read-only correction without retention would fail after Build ZIP or release preparation; a separate preferences object or service would add storage and ownership without satisfying an additional requirement. The selected design needs neither. Symfony applies question defaults before the existing validator, so no second directory-validation path is necessary.

No material design correction or scope change was needed. Verification wording was tightened to prove release-version replacement and preservation of old values when replacement answers are declined. All originating requirements remain governing. No blocking planning findings or implementation-design unknowns remain; this is plan approval, not evidence that the feature has been implemented or tested.

## Implementation handoff

Load `implementation-completion-workflow` as the execution driver after this plan is accepted. Load `linear-plan-workflow` within that workflow and materialize the single issue described below before coding. Follow this settled design; do not introduce a settings service, alternate persistence route, or broader release-tooling refactor. If required Linear access fails, apply that skill's retry policy and stop before coding if access remains unavailable, unless the user authorizes proceeding without it.

## Objective and acceptance criteria

Make `operator:package-svn` remember its directory between invocations, including when other operator actions run between them. Also restore the remembered release version required by `docs/operator-cli-workflow.md`. This is one independently completable slice.

- A saved SVN target appears as the prompt default; Enter reuses it and typing another target overrides it.
- A saved release version appears as the default; absent a usable saved version, use `configuredVersion()`. Timestamp remains current and build remains `auto`.
- Both remembered values survive other confirmed operator actions, including Build ZIP.
- Existing state files work immediately without manual edits, a separate migration, or another file.
- Missing, unreadable, malformed, or incorrectly typed optional state does not prevent normal prompting. Saved directories still undergo the existing validation when accepted.
- Confirmation, command construction, state-write timing, write-failure handling, cancellation, and process exit behavior retain their existing contracts.
- The implementation reuses the existing class and helpers and closes the targeted verification below. All criteria are currently pending implementation.

## Evidence and impacted areas

The investigation reproduced a successful first invocation that saved its target, followed by a second invocation where Enter failed to reuse it. `ReleaseOperatorCommand` writes state but never reads it. The SVN `Question` has no default. Each write replaces all prior inputs. Existing tests prove writing, not recall.

Planned changes:

| File | Change |
|---|---|
| `infrastructure/src/Tooling/Cli/Command/ReleaseOperatorCommand.php` | Read remembered inputs, supply visible defaults, and merge retained inputs into the existing write. |
| `tests/Unit/ReleaseOperatorCommandTest.php` | Exercise successive command instances and retained state through existing helpers. |
| `docs/operator-cli-workflow.md` | Briefly document retained inputs, Enter/override behavior, and missing or invalid state behavior. |

`ShieldCliApplication` already routes the menu and all fixed actions through this class. No routing change is needed. Repository searches found no production reader of this state file; tests are its other consumer. The existing test that checks top-level keys can remain unchanged.

PHP's declared floor is 7.4. Use compatible arrays and existing type conventions. Current unrelated working-tree edits must remain untouched. Recheck ownership before implementation; the planned files were clean at planning time.

## Reuse and settled implementation design

### 1. Read only the values that need remembering

Add one small private method, `readRememberedInputs(string $statePath): array`, returning `array<string,string>` containing only usable `target` and `version` values from the existing JSON `inputs` object.

Use the existing `statePath()` and native file/JSON functions. Missing/non-file or unreadable state, a failed read, invalid JSON, a non-array root, or non-array `inputs` yields an empty array. Keep read-failure handling local to this optional file read, including any necessary suppression of its filesystem warning; do not catch unrelated execution errors. Accept only nonblank strings for the two fields, independently, without coercing arrays, numbers, or booleans. Preserve accepted strings; the existing directory validator owns path trimming and canonicalization. Ignore all other saved fields.

Read once inside `execute()`, after action selection and its cancellation branch, before `askForInputs()`. Pass this local array into `askForInputs()` and use it again for the confirmed write. No new constructor dependency, mutable settings property, cache, schema version, or generalized configuration helper.

### 2. Reuse the existing questions and validators

In `askForInputs()`:

- SVN: use the saved `target`, or `null`, as the existing `Question` default. Keep its current validator calling `externalPackageTarget()` unchanged.
- Release version: use saved `version`, otherwise `configuredVersion()`, as the existing question default.
- Show these defaults in their question text so the operator knows Enter will accept them. Reuse Symfony `OutputFormatter::escape()` for displayed values; pass the original value as the actual default. The installed `QuestionHelper` does not automatically display a `Question` default.
- Leave timestamp, build, and Build ZIP input behavior unchanged.

Do not add a second path-validation pass on reading state. If a saved directory was removed or is inside the project, accepting it follows the existing validator's error/retry behavior; the operator can enter a valid replacement. Typing an explicit replacement bypasses the unused default naturally through the same question.

### 3. Preserve remembered inputs using the existing write

Keep the current top-level JSON shape: `action`, `inputs`, `command`. Give `inputs` this explicit meaning: retained `target` and `version`, plus the current action's answers. `action` and `command` continue to describe the latest confirmed operation.

Build the command from the current answers, exactly as today. After confirmation, call the existing `writeState()` with `array_replace($rememberedInputs, $inputs)` in place of its current inputs argument. Current answers win. Do not merge the entire old state or retain old timestamps/build values between unrelated actions. Do not execute a saved `command`.

This preserves the writer, file location, JSON flags, failure handling, and save-before-process behavior. It adds no second preferences object or duplicate stored value. An existing file containing only an SVN target is already readable by the new method.

Example: package to directory A, prepare version B, then build ZIP. The last state has `action: build-zip`, `inputs: {target: A, version: B}`, and the normal ZIP command. Subsequent package and prepare prompts recover A and B. The ZIP command still receives no extra arguments.

Confirmed choices remain saved even when the delegated process fails, as today. Decline/cancel must not modify an existing state file. Read failure may discard unavailable old defaults on the next confirmed save; no backup/recovery framework is warranted for this local convenience file.

## Ordered implementation and verification

1. Recheck applicable instructions and working-tree ownership. Create/update the one Linear issue below. Reuse the existing fixtures and test helpers.
2. Add the recall regression through `CommandTester`: execute two fresh `ReleaseOperatorCommand` instances sharing a tracked temporary project root, first with an explicit directory and then Enter. Assert both reach the same expected process command. Use `RecordingProcessRunner`; do not actually package or touch the operator's SVN directory.
3. Implement the reader, prompt defaults/display, and confirmed merge described above. Keep `buildCommand()`, `externalPackageTarget()`, `canonicalDirectory()`, and `writeState()` behavior in their existing owners.
4. Extend the same test class with the following meaningful cases. Group related cases where that keeps the suite readable:

| Behavior | Evidence |
|---|---|
| Existing file and overrides | Seed the currently supported JSON shape, accept its target, then explicitly choose another target and verify that a fresh invocation recalls the replacement. |
| Cross-action retention | Run package, prepare, ZIP, then recall package and prepare using fresh instances. Assert delegated arguments and retained state. Include the menu entry point in this sequence. |
| Release defaults | Use a remembered version different from fixture configuration; verify it wins. Explicitly choose a different version, then verify a fresh invocation recalls the replacement. Seed an old timestamp and custom build and verify Enter still supplies a current timestamp and `auto`. Existing no-state configured-version coverage remains. |
| Optional bad state | Missing state and representative invalid JSON, non-array `inputs`, and wrongly typed/blank fields still allow explicit valid input or configured-version defaults. One invalid field must not discard the other usable field. Do not add platform-sensitive permission tests solely for unreadability. |
| Saved path validation | Seed missing and project-internal targets; accepting them cannot invoke packaging. Entering a valid replacement through the existing retry path succeeds and saves that replacement. |
| Cancellation and failure | With pre-existing state, enter a replacement value and decline confirmation; assert the old state's bytes remain unchanged and no process runs. Also retain menu-cancel coverage with pre-existing state. Retain save-before-run, write-failure, and nonzero-process tests; ensure a confirmed failed process still leaves its chosen values available for recall. |

Assert process arguments, status, and state rather than exact prompt prose or output snapshots. In multi-invocation tests, assert each invocation's status and expected process call; accepting defaults must actually reach the runner, not merely return success through a declined confirmation. Reuse `TempDirLifecycleTrait`, `projectRoot()`, `execute()`, `readState()`, `RecordingProcessRunner`, and `StateObservingProcessRunner`. No production test seam is needed. Verify visible defaults with one disposable-fixture prompt inspection; do not create presentation-string tests.

5. Run focused verification from the repository root:

```text
composer test:unit -- tests/Unit/ReleaseOperatorCommandTest.php
php -l infrastructure/src/Tooling/Cli/Command/ReleaseOperatorCommand.php
php -l tests/Unit/ReleaseOperatorCommandTest.php
php vendor/phpstan/phpstan/phpstan analyse -c phpstan.tooling.neon.dist --no-progress --memory-limit=1G infrastructure/src/Tooling/Cli/Command/ReleaseOperatorCommand.php
```

The static check uses the repository's tooling configuration, narrowed to the changed production owner. Apply the test-evidence-cache skill if applicable when executing PHP checks. No full suite, Docker, asset build, live package run, or configuration change is required by this bounded change. If a test wrapper regenerates configuration from unrelated edits, identify that derivative output and do not include it in delivery.

6. Update the short operator documentation. Review the final diff against the criteria and the directive matrix below. Fix local blocking defects, material architectural defects, or missing required evidence in the same pass and rerun affected checks before claiming completion.
7. Follow implementation validation, a bounded final convergence cleanup, then independent final verification before any separately authorized commit. Keep these reviews confined to this plan's behavior and touched files. Close the issue/checklist only after implementation and required verification are complete.

## Directive closure

| User directive | Implementation commitment | Proof |
|---|---|---|
| Keep it simple; no major rewrite | One local reader; extend existing prompts and merge at the current write call. | Final diff has no new service, settings file, schema framework, dependency, or command. |
| Reuse existing pathways | Existing path resolution, question validation, command builder, writer, runner, and test fixtures remain the owners. | Diff inspection and real-command tests above. |
| Carefully plan before implementation | Resolve state shape, precedence, invalid-state behavior, and evidence here. | Planning changes only this plan; production changes wait for acceptance. |

## Sub-Agent Assignments

No qualifying delegation lane for implementation: the single class and its behavior tests are tightly coupled, and splitting this small slice adds coordination rather than useful independent work. Later independent validation remains part of the workflow sequence.

## Linear tracking plan

Use the Shield Security team backlog. Reuse a matching existing issue if found; otherwise create one issue titled **Restore remembered defaults in the release operator CLI**. Link this plan and include its acceptance checklist and verification evidence. No new project, sub-issues, milestones, or dependency relationships are needed. Leave scheduling/assignment fields unset unless already established. Planning makes no Linear changes.

## Risks, walkthrough, and scope limits

The walkthrough exposed two necessary details: merging all saved inputs would unnecessarily carry old timestamp/build fields, and setting `Question` defaults alone would leave them invisible. The design above retains only the two requested persistent fields and explicitly displays defaults.

Remembered values are repository-local and last-confirmed, not global or last-successful. Deleting `tmp/operator-state.json` forgets them. Existing values already overwritten by older invocations cannot be recovered. Cross-process concurrent state writes retain existing last-writer behavior; locking, atomic-write redesign, history, reset commands, version validation, and SVN metadata checks are outside this task.

No unresolved implementation-design questions or current code blockers remain. Linear availability is checked during implementation. No known non-blocking convergence items were identified; the final cleanup is limited to unnecessary scaffolding or duplication introduced by this change, and unrelated improvements remain deferred.
