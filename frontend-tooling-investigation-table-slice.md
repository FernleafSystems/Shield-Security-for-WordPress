# Frontend Tooling: Investigation Table Slice

## Objective

Add the smallest viable ESLint + TypeScript `checkJs` setup for the shared investigation table bootstrap without widening enforcement across the whole frontend.

This first slice is intentionally small and rollback-friendly. It targets the shared runtime seam that recently broke multiple IP analysis tables when `InvestigationTable` still called a method that no longer existed.

## First Slice

Checked JS files:

- `assets/js/components/tables/InvestigationTable.js`
- `assets/js/components/tables/ShieldTableBase.js`

Support files added for this slice:

- `eslint.config.js`
- `tsconfig.checkjs.json`
- `assets/js/components/BaseComponent.d.ts`
- `assets/js/components/services/AjaxService.d.ts`
- `assets/js/components/ui/OffCanvasService.d.ts`
- `assets/js/util/ObjectOps.d.ts`
- `assets/js/components/tables/investigation-tables.globals.d.ts`
- `tools/check-js-policy.cjs`

## Tooling Added

Packages added:

- `eslint`
  Provides the slice-local linting used by `npm run test:js`.
- `@eslint/js`
  Supplies the base recommended rule set for the flat ESLint config.
- `typescript`
  Provides TypeScript 6 `checkJs` type-checking inside `npm run test:js` without converting the selected JS files to `.ts`.
- `@types/jquery`
  Supplies the jQuery types required by the checked table files and DataTables imports.

`package.json` commands:

- `npm run check:js-policy`
- `npm run lint:js`
- `npm run typecheck:js`
- `npm run test:js`

Recommended command:

- `npm run test:js`

`npm run test:js` is the single "is this JavaScript slice valid?" command for this first pass. It runs the checker-only policy guard, linting, TypeScript `checkJs`, and the existing webpack build for the same slice.
The policy guard also fails if `test:js` is widened to include browser/runtime execution or if someone starts introducing compiled TypeScript behavior into `assets/js`.

## Why This Slice First

- `InvestigationTable` and `ShieldTableBase` sit on a shared table/bootstrap seam.
- That seam is directly implicated in the recent datatable regression.
- The slice is small enough to type-check cleanly without dragging the rest of `assets/js` into cleanup.
- The existing webpack/Babel pipeline already builds this code successfully, so this pass adds checking rather than replacing the build.

Why not rely on tests alone?

- The missing-method regression that motivated this pass is exactly the kind of issue that can survive a normal bundle build and non-browser tests.
- TypeScript `checkJs` catches that class of missing-method problem earlier and more directly than a browser test.
- Browser/runtime coverage is still useful, but it stays a separate verification lane. `npm run test:js` is deliberately static-only so it remains fast, local, and checker-focused.

## Regression Coverage

Current branch status:

- The stale `applyBehaviorDatatableConfig()` call is already absent from `assets/js/components/tables/InvestigationTable.js`.
- Earlier on this branch, commit `220b89721` removed that stale call before this tooling pass.

Would this setup have helped catch that regression?

- Yes.
- A proof run against `HEAD^` of `assets/js/components/tables/InvestigationTable.js` reported:
  `Property 'applyBehaviorDatatableConfig' does not exist on type 'InvestigationTable'.`

That is the exact missing-method failure that previously reached the browser runtime.

## What This Slice Does Not Protect Yet

- It does not lint or type-check `UiContentActivator.js`.
- It does not lint or type-check `DataTableVisibilityAdjuster.js`.
- It does not cover scan-results table behavior, page controllers, or the wider dynamic activation path.
- It does not compile TypeScript into the webpack build. This slice uses `tsc --noEmit` only.
- `npm run test:js` does not mean whole-frontend coverage yet. In this first slice it validates only the investigation-table seam defined in `eslint.config.js`, `tsconfig.checkjs.json`, and the checker-only policy script.

## How To Extend Safely

1. Add one adjacent shared seam at a time.
2. Prefer `allowJs` + `checkJs` first.
3. Add only the declaration files needed to fence the chosen slice.
4. Reuse the existing build and browser/runtime lanes instead of introducing a parallel toolchain.
5. Prove the new enforcement scope with targeted searches before widening it further.

Recommended next slice:

- `assets/js/components/ui/UiContentActivator.js`
- `assets/js/components/tables/DataTableVisibilityAdjuster.js`

## Anti-Patterns

- Do not treat this as a whole-frontend TypeScript migration.
- Do not rename lots of files to `.ts` by default.
- Do not replace the existing webpack/Babel pipeline.
- Do not widen ESLint or TypeScript enforcement across `assets/js` without a deliberate follow-up slice.
- Do not use broad fallback declarations that silently turn the whole frontend into `any`.
- Do not treat `tsconfig.checkjs.json` as a build config. It exists only for `checkJs` validation.
- Do not add `.ts` or `.tsx` sources under `assets/js` without explicitly changing the policy script and build strategy on purpose.
