# Passkey WebAuthn Adapter Handover

## Purpose

This document explains:

- why Shield's current WebAuthn dependency became a problem
- what was implemented in git commit `726ab4dfa3a4ffccbf8cd0eff57dd0fb82ffebef`
- the current passkey strategy going forward
- how passkey testing now works
- which replacement libraries were assessed
- which replacement is recommended and why

This is intended as a clean restart point for future passkey work.

## Why This Work Was Necessary

Shield currently depends on `web-auth/webauthn-lib:^3.3` in [composer.json](/D:/Work/Dev/Repos/FernleafSystems/WP_Plugin-Shield/composer.json).

That became a problem because:

- Composer began blocking the package during `composer update` due to a security advisory affecting the installed dependency line.
- The advisory that blocked the dependency was `GHSA-f7pm-6hr8-7ggm`, also tracked as `PKSA-n72g-8zd8-6dm2` / `CVE-2026-30964`.
- The first upstream non-vulnerable release requires PHP `>=8.2`.
- Shield still targets PHP `>=7.4`.

That means there is no straightforward secure in-place upgrade path for the current library while keeping current PHP support.

## What We Needed To Achieve

We needed to get Shield into a state where:

- the current passkey implementation remains stable
- passkey storage stays compatible with existing enrolled credentials
- passkey behavior can be tested without being welded to one vendor library
- a future WebAuthn library swap can be done behind a narrow internal seam

The key design decision was:

- Shield should own the passkey workflow contract
- the WebAuthn library should sit behind an internal adapter
- Shield business logic, storage, login intent handling, and UI flow should stay outside that adapter

## What Commit `726ab4df` Changed

Commit `726ab4dfa3a4ffccbf8cd0eff57dd0fb82ffebef` introduced a narrow internal adapter seam and replayable passkey verification tests.

### Production changes

#### 1. Added a Shield-owned passkey adapter contract

New files:

- [src/Modules/LoginGuard/Lib/TwoFactor/Passkey/PasskeyAdapterInterface.php](/D:/Work/Dev/Repos/FernleafSystems/WP_Plugin-Shield/src/Modules/LoginGuard/Lib/TwoFactor/Passkey/PasskeyAdapterInterface.php)
- [src/Modules/LoginGuard/Lib/TwoFactor/Passkey/PasskeyAdapterContext.php](/D:/Work/Dev/Repos/FernleafSystems/WP_Plugin-Shield/src/Modules/LoginGuard/Lib/TwoFactor/Passkey/PasskeyAdapterContext.php)

The adapter interface covers only four responsibilities:

- start registration
- verify registration
- start authentication
- verify authentication

This is deliberately narrow. It is not a public extension point and not a general MFA framework.

#### 2. Moved current `webauthn-lib` integration behind that adapter

New file:

- [src/Modules/LoginGuard/Lib/TwoFactor/Passkey/WebauthnLibAdapter.php](/D:/Work/Dev/Repos/FernleafSystems/WP_Plugin-Shield/src/Modules/LoginGuard/Lib/TwoFactor/Passkey/WebauthnLibAdapter.php)

This adapter contains the vendor-specific use of:

- `Server`
- `AuthenticatorAttestationResponseValidator`
- `PublicKeyCredentialCreationOptions`
- `PublicKeyCredentialRequestOptions`
- PSR-7 request creation

That keeps vendor classes from leaking through the rest of Shield.

#### 3. Refactored `Passkey` to use the adapter

Updated file:

- [src/Modules/LoginGuard/Lib/TwoFactor/Provider/Passkey.php](/D:/Work/Dev/Repos/FernleafSystems/WP_Plugin-Shield/src/Modules/LoginGuard/Lib/TwoFactor/Provider/Passkey.php)

`Passkey` still owns:

- challenge persistence in user meta
- passkey record persistence
- login OTP processing
- user/profile-facing success and failure behavior
- existing form and JS variable generation

The production seam is internal:

- `buildPasskeyAdapter()`

This was intentionally kept private to the provider flow. No public runtime swap hook was added.

#### 4. Extended passkey storage handling to work with adapter-returned data

Updated file:

- [src/Modules/LoginGuard/Lib/TwoFactor/Utilties/PasskeySourcesHandler.php](/D:/Work/Dev/Repos/FernleafSystems/WP_Plugin-Shield/src/Modules/LoginGuard/Lib/TwoFactor/Utilties/PasskeySourcesHandler.php)

The repository now supports:

- saving vendor objects through existing methods
- saving normalized credential arrays returned by the adapter
- updating credential data by credential id

This was necessary so the provider no longer needed direct `PublicKeyCredentialSource` handling everywhere.

### Test and fixture changes

#### 5. Added a passkey fixture system

Key files:

- [tests/Integration/MFA/Support/PasskeyFixtureLoader.php](/D:/Work/Dev/Repos/FernleafSystems/WP_Plugin-Shield/tests/Integration/MFA/Support/PasskeyFixtureLoader.php)
- [tests/fixtures/passkeys/fixture_ceremony.json](/D:/Work/Dev/Repos/FernleafSystems/WP_Plugin-Shield/tests/fixtures/passkeys/fixture_ceremony.json)
- [tests/fixtures/passkeys/README.md](/D:/Work/Dev/Repos/FernleafSystems/WP_Plugin-Shield/tests/fixtures/passkeys/README.md)

Important rule:

- the committed fixture is synthetic and safe
- any real site-derived fixture must live only in `tests/fixtures/passkeys/fixture_ceremony.local.json`
- that local file is git-ignored in [.gitignore](/D:/Work/Dev/Repos/FernleafSystems/WP_Plugin-Shield/.gitignore)

#### 6. Added a dedicated static passkey test lane

Key files:

- [tests/Unit/Modules/LoginGuard/Lib/TwoFactor/Passkey/WebauthnLibAdapterTest.php](/D:/Work/Dev/Repos/FernleafSystems/WP_Plugin-Shield/tests/Unit/Modules/LoginGuard/Lib/TwoFactor/Passkey/WebauthnLibAdapterTest.php)
- [composer.json](/D:/Work/Dev/Repos/FernleafSystems/WP_Plugin-Shield/composer.json)

Command:

```bash
composer test:passkeys
```

This lane verifies replay through the real current adapter for:

- registration verification
- authentication verification
- wrong origin rejection
- wrong challenge rejection
- malformed payload rejection

#### 7. Updated integration support and passkey flow tests

Key files:

- [tests/Integration/MFA/PasskeyProviderFlowIntegrationTest.php](/D:/Work/Dev/Repos/FernleafSystems/WP_Plugin-Shield/tests/Integration/MFA/PasskeyProviderFlowIntegrationTest.php)
- [tests/Integration/MFA/PasskeyActionsIntegrationTest.php](/D:/Work/Dev/Repos/FernleafSystems/WP_Plugin-Shield/tests/Integration/MFA/PasskeyActionsIntegrationTest.php)
- [tests/Integration/MFA/PasskeyWebauthnLibAdapterStartIntegrationTest.php](/D:/Work/Dev/Repos/FernleafSystems/WP_Plugin-Shield/tests/Integration/MFA/PasskeyWebauthnLibAdapterStartIntegrationTest.php)
- [tests/Integration/MFA/Support/PasskeyTestEnvironmentTrait.php](/D:/Work/Dev/Repos/FernleafSystems/WP_Plugin-Shield/tests/Integration/MFA/Support/PasskeyTestEnvironmentTrait.php)
- [tests/Helpers/TestDataFactory.php](/D:/Work/Dev/Repos/FernleafSystems/WP_Plugin-Shield/tests/Helpers/TestDataFactory.php)

These cover:

- start challenge persistence
- registration persistence
- authentication persistence and counter updates
- login-intent passkey verification flow
- action-router passkey flows

## Current Passkey Strategy

The new strategy is simple:

1. Keep Shield in control of passkey workflow and storage.
2. Keep WebAuthn vendor logic behind a narrow internal adapter.
3. Keep current storage compatible for existing enrolled passkeys.
4. Use replayable fixtures to test the verification path.
5. Swap the adapter when the library changes.

In practice, that means:

- `Passkey` remains the orchestration layer
- `PasskeySourcesHandler` remains the persistence bridge
- each WebAuthn library gets its own adapter implementation
- the rest of Shield should not depend on vendor classes

## What This Did Not Change

This work did not try to:

- redesign passkey storage
- change UI rendering or frontend flow
- add browser automation
- add a public runtime extension point for third-party passkey adapters
- solve the upstream vulnerable package problem by itself

This work was about creating a safe migration path.

## Security and Data-Handling Notes

### Sensitive fixture handling

Real passkey registration/assertion material should not be committed.

The safe pattern is:

- keep the checked-in fixture synthetic
- keep any real fixture only in `fixture_ceremony.local.json`
- do not commit that file
- delete and re-register any disposable real passkey used for local capture if needed

### Review outcome for commit `726ab4df`

A security-focused review of the commit did not identify a new security vulnerability introduced by the refactor itself.

What was verified:

- the adapter preserves the same current library verification primitives
- the committed fixture is synthetic
- the local sensitive fixture path is ignored
- the dedicated static passkey lane passes

Known operational limitation:

- the WordPress integration environment is still needed for full runtime confirmation of the integration suite

## Alternative Libraries Assessed

Two practical PHP 7.4-compatible candidates were assessed as replacement targets.

### 1. `madwizard/webauthn`

Status:

- strongest recommendation
- current package line supports PHP `^7.2|^8.0`
- current stable release assessed: `v1.0.0`
- Packagist currently reports `Security: 0`
- best match to Shield's adapter shape

Why it fits best:

- explicit registration and authentication ceremony lifecycle
- strong server-side library design
- browser JSON handling is close to Shield's current data flow
- credential storage model maps relatively cleanly to `PasskeySourcesHandler`
- official docs expose a credential-store interface very close to Shield's current persistence responsibilities

What an adapter would need to do:

- implement the same four adapter methods
- translate Shield's passkey context into a `RelyingParty` and user context
- map stored Shield credential records into the library's credential store interface
- convert browser JSON into the library's registration/authentication objects
- return Shield-usable credential arrays for persistence

Likely extra work:

- build a small credential-store bridge for existing Shield passkey rows
- verify how legacy `webauthn-lib`-shaped stored records map into the new library's expected structure
- possibly add a translation layer for legacy stored credentials

Important caveat:

- `madwizard/webauthn` requires `ext-sodium`
- it also brings in more dependencies than the current lightweight adapter seam, so packaging and platform checks would need confirming during migration

### 2. `lbuchs/webauthn`

Status:

- possible fallback
- less attractive as a migration target
- current latest package line assessed: `v2.2.0`
- current latest line requires PHP `>=8.0`
- PHP 7.4 support would require pinning an older `v1.1.x` branch
- Packagist currently reports `Security: 0`

Why it is weaker:

- lower-level API shape
- more manual handling in the adapter
- more security-sensitive checks likely need to remain in Shield code
- legacy Shield credential storage is a poorer fit
- for PHP 7.4, it would mean adopting an older branch rather than the current maintained line

What an adapter would need to do:

- break request payloads apart more manually
- convert stored credential data into the library's expected public key form
- handle credential lookup and ownership checks more explicitly
- do more translation between Shield storage and library expectations

Likely extra work:

- translate legacy stored public key material into the format `lbuchs` expects
- ensure allow-list and ownership behavior remains strict
- add more migration-specific test coverage because more logic would live in the adapter

## Recommendation

The recommended replacement target is:

- `madwizard/webauthn`

Why:

- it is the closest conceptual match to the adapter seam already built
- it should require the least awkward adapter code
- it is the best fit for a controlled migration without broad Shield changes
- it avoids pinning Shield to an older PHP-7-only branch in the way `lbuchs/webauthn` would

`lbuchs/webauthn` is still possible, but it is more likely to create adapter complexity and migration risk.

## What Future Migration Work Should Do

When the library swap is picked up again, the next implementation should:

1. Keep the current `PasskeyAdapterInterface`.
2. Add a new adapter implementation for the chosen library.
3. Keep `Passkey` and `PasskeySourcesHandler` as the orchestration and persistence layers.
4. Preserve existing enrolled passkeys as a hard compatibility goal.
5. Prove old stored passkeys can still authenticate without re-registration.
6. Run the existing static passkey lane against the new adapter.
7. Add or adjust adapter-focused replay tests for the new library.
8. Run the WordPress integration suite in a real test environment before release.

## Recommended Restart Checklist

When this work resumes:

1. Reconfirm the current status of the `web-auth/webauthn-lib` security advisory and PHP requirements.
2. Reconfirm the current package state of `madwizard/webauthn` and `lbuchs/webauthn`.
3. Start with a `MadwizardWebauthnAdapter` spike behind the existing interface.
4. Build the credential-store mapping for existing Shield passkey records.
5. Replay legacy authentication fixtures first.
6. Only move to registration once legacy authentication compatibility is proven.

## Files Most Relevant To Continue This Work

Production:

- [src/Modules/LoginGuard/Lib/TwoFactor/Provider/Passkey.php](/D:/Work/Dev/Repos/FernleafSystems/WP_Plugin-Shield/src/Modules/LoginGuard/Lib/TwoFactor/Provider/Passkey.php)
- [src/Modules/LoginGuard/Lib/TwoFactor/Passkey/PasskeyAdapterInterface.php](/D:/Work/Dev/Repos/FernleafSystems/WP_Plugin-Shield/src/Modules/LoginGuard/Lib/TwoFactor/Passkey/PasskeyAdapterInterface.php)
- [src/Modules/LoginGuard/Lib/TwoFactor/Passkey/PasskeyAdapterContext.php](/D:/Work/Dev/Repos/FernleafSystems/WP_Plugin-Shield/src/Modules/LoginGuard/Lib/TwoFactor/Passkey/PasskeyAdapterContext.php)
- [src/Modules/LoginGuard/Lib/TwoFactor/Passkey/WebauthnLibAdapter.php](/D:/Work/Dev/Repos/FernleafSystems/WP_Plugin-Shield/src/Modules/LoginGuard/Lib/TwoFactor/Passkey/WebauthnLibAdapter.php)
- [src/Modules/LoginGuard/Lib/TwoFactor/Utilties/PasskeySourcesHandler.php](/D:/Work/Dev/Repos/FernleafSystems/WP_Plugin-Shield/src/Modules/LoginGuard/Lib/TwoFactor/Utilties/PasskeySourcesHandler.php)

Tests and fixtures:

- [tests/Unit/Modules/LoginGuard/Lib/TwoFactor/Passkey/WebauthnLibAdapterTest.php](/D:/Work/Dev/Repos/FernleafSystems/WP_Plugin-Shield/tests/Unit/Modules/LoginGuard/Lib/TwoFactor/Passkey/WebauthnLibAdapterTest.php)
- [tests/Integration/MFA/PasskeyProviderFlowIntegrationTest.php](/D:/Work/Dev/Repos/FernleafSystems/WP_Plugin-Shield/tests/Integration/MFA/PasskeyProviderFlowIntegrationTest.php)
- [tests/Integration/MFA/PasskeyActionsIntegrationTest.php](/D:/Work/Dev/Repos/FernleafSystems/WP_Plugin-Shield/tests/Integration/MFA/PasskeyActionsIntegrationTest.php)
- [tests/Integration/MFA/PasskeyWebauthnLibAdapterStartIntegrationTest.php](/D:/Work/Dev/Repos/FernleafSystems/WP_Plugin-Shield/tests/Integration/MFA/PasskeyWebauthnLibAdapterStartIntegrationTest.php)
- [tests/Integration/MFA/Support/PasskeyFixtureLoader.php](/D:/Work/Dev/Repos/FernleafSystems/WP_Plugin-Shield/tests/Integration/MFA/Support/PasskeyFixtureLoader.php)
- [tests/fixtures/passkeys/README.md](/D:/Work/Dev/Repos/FernleafSystems/WP_Plugin-Shield/tests/fixtures/passkeys/README.md)
- [tests/fixtures/passkeys/fixture_ceremony.json](/D:/Work/Dev/Repos/FernleafSystems/WP_Plugin-Shield/tests/fixtures/passkeys/fixture_ceremony.json)

## Bottom Line

Commit `726ab4df` did not replace the vulnerable library.

It did the prerequisite work to make that replacement realistic:

- isolated the current WebAuthn implementation behind an internal adapter
- kept current passkey behavior and storage stable
- established replayable passkey verification tests
- protected local sensitive fixture handling

The recommended next move is to implement a new adapter for `madwizard/webauthn` and use the existing passkey test strategy to validate the migration.

## External Reference Links

- Advisory: https://github.com/advisories/GHSA-f7pm-6hr8-7ggm
- Current dependency: https://packagist.org/packages/web-auth/webauthn-lib
- Recommended target: https://packagist.org/packages/madwizard/webauthn
- `madwizard` docs: https://madwizard-thomas.github.io/webauthn/
- Fallback target: https://packagist.org/packages/lbuchs/webauthn
- `lbuchs` source: https://github.com/lbuchs/WebAuthn
