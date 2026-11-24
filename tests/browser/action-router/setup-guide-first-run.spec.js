const { test, expect, openShieldRoute } = require( './support/shield-test' );

const guide = ( page ) => page.locator( '#ShieldSetupGuide' );
const toggle = ( page ) => guide( page ).locator( '[data-setup-guide-toggle="1"]' );
const backdrop = ( page ) => page.locator( '.shield-setup-guide-backdrop' );
const openDashboard = ( page ) => openShieldRoute( page, { nav: 'dashboard', nav_sub: 'overview' } );

test( 'on a new install the guide opens over the dashboard until it is first minimised', async ( { page, fixtureApi } ) => {
	await fixtureApi.withSetupGuideStateFixture( 'first_run', async () => {
		await openDashboard( page );
		await expect( toggle( page ) ).toHaveAttribute( 'aria-expanded', 'true' );
		await expect( backdrop( page ) ).toBeVisible();
		await expect( guide( page ).locator( '[data-setup-guide-pane]:not([hidden])' ) ).toHaveCount( 1 );

		// Elsewhere it stays the bar it always was.
		await openShieldRoute( page, { nav: 'reports', nav_sub: 'overview' } );
		await expect( toggle( page ) ).toHaveAttribute( 'aria-expanded', 'false' );

		await openDashboard( page );
		await guide( page ).locator( '.shield-setup-guide__leave > [data-setup-guide-minimise="1"]' ).click();
		await expect( toggle( page ) ).toHaveAttribute( 'aria-expanded', 'false' );
		await expect( backdrop( page ) ).toBeHidden();
		await expect( toggle( page ) ).toBeVisible();

		// Minimised once, it no longer opens by itself, and opens when asked.
		await expect( guide( page ) ).toHaveAttribute( 'data-minimised', '1' );
		await openDashboard( page );
		await expect( toggle( page ) ).toHaveAttribute( 'aria-expanded', 'false' );
		await toggle( page ).click();
		await expect( toggle( page ) ).toHaveAttribute( 'aria-expanded', 'true' );
	} );
} );

test( 'the opened guide is minimised by Escape and by a click on the page behind it', async ( { page, fixtureApi } ) => {
	await fixtureApi.withSetupGuideStateFixture( 'first_run', async () => {
		await openDashboard( page );
		await expect( toggle( page ) ).toHaveAttribute( 'aria-expanded', 'true' );
		await page.keyboard.press( 'Escape' );
		await expect( toggle( page ) ).toHaveAttribute( 'aria-expanded', 'false' );

		await toggle( page ).click();
		await expect( backdrop( page ) ).toBeVisible();
		// The dimmed page shows between the admin bar and the top of the guide.
		await backdrop( page ).click( { position: { x: 600, y: 42 } } );
		await expect( toggle( page ) ).toHaveAttribute( 'aria-expanded', 'false' );
	} );
} );

test( 'closing the guide from its menu takes it away for good', async ( { page, fixtureApi } ) => {
	await fixtureApi.withSetupGuideStateFixture( 'first_run', async () => {
		await openDashboard( page );
		await guide( page ).locator( '.shield-setup-guide__leave .dropdown-toggle' ).click();
		await guide( page ).locator( '.shield-setup-guide__leave [data-setup-guide-dismiss="1"]' ).click();
		await expect( guide( page ) ).toHaveCount( 0 );
		await expect( backdrop( page ) ).toHaveCount( 0 );

		await openDashboard( page );
		await expect( guide( page ) ).toHaveCount( 0 );
	} );
} );

test( 'the report email can only be saved once the box holds an address', async ( { page, fixtureApi } ) => {
	await fixtureApi.withSetupGuideStateFixture( 'first_run', async () => {
		await openDashboard( page );
		const pane = guide( page ).locator( '[data-setup-guide-pane="report_email"]' );
		const input = pane.locator( '[data-report-email-input="1"]' );
		const save = pane.locator( '[data-report-email-save="1"]' );

		await expect( input ).toHaveValue( '' );
		await expect( save ).toBeDisabled();
		await input.fill( 'not-an-address' );
		await expect( save ).toBeDisabled();
		await input.fill( 'agency@example.test' );
		await expect( save ).toBeEnabled();
	} );
} );

test( 'the recommended protection level starts chosen and makes nothing less strict', async ( { page, fixtureApi } ) => {
	await fixtureApi.withSetupGuideStateFixture( 'first_run', async () => {
		await openDashboard( page );
		await guide( page ).locator( '[data-setup-guide-step="apply_security_profile"]' ).click();
		const pane = guide( page ).locator( '[data-setup-guide-pane="apply_security_profile"]' );

		const chosen = pane.locator( '[data-protection-level][aria-pressed="true"]' );
		await expect( chosen ).toHaveCount( 1 );
		const level = await chosen.getAttribute( 'data-protection-level' );
		await expect( pane.locator( '[data-protection-levels-choice="1"]' ) ).toHaveValue( level );
		await expect( pane.locator( `[data-protection-level-comparison="${level}"] .protection-levels__row.is-looser` ) ).toHaveCount( 0 );
		await expect( pane.locator( '[data-protection-levels-apply="1"]' ) ).toBeEnabled();
	} );
} );

test( 'a skipped step is counted as skipped, and the guide ends on a summary once every step is decided', async ( { page, fixtureApi } ) => {
	await fixtureApi.withSetupGuideStateFixture( 'first_run', async () => {
		await openDashboard( page );
		const steps = guide( page ).locator( '[data-setup-guide-step]' );
		const total = await steps.count();
		// The summary is in the list with the steps, and can't be opened while a step is still to do.
		const summaryEntry = guide( page ).locator( '[data-setup-guide-summary-nav="1"]' );
		await expect( summaryEntry ).toBeDisabled();

		const pane = guide( page ).locator( '[data-setup-guide-pane]:not([hidden])' );

		// Where reports go has to be chosen: that step can be saved, and not skipped.
		await expect( pane ).toHaveAttribute( 'data-setup-guide-pane', 'report_email' );
		await expect( pane.locator( '[data-setup-guide-skip]' ) ).toHaveCount( 0 );
		await pane.locator( '[type="submit"].btn-link' ).click();
		await expect( guide( page ).locator( '[data-setup-guide-step="report_email"]' ) ).toHaveClass( /is-done/ );
		// A saved step is drawn again from the site's settings, as a finished step: its form goes.
		await expect( guide( page ).locator( '[data-setup-guide-pane="report_email"] form' ) ).toHaveCount( 0 );

		// Every other step's skip link sits in the same row as the step's main button.
		await expect( pane.locator( '.shield-setup-guide__actions [type="submit"]' ).first() ).toBeVisible();
		await expect( pane.locator( '.shield-setup-guide__actions [data-setup-guide-skip]' ) ).toBeVisible();

		for ( let skipped = 1; skipped < total; skipped++ ) {
			await guide( page ).locator( '[data-setup-guide-pane]:not([hidden]) [data-setup-guide-skip]' ).click();
			await expect( guide( page ).locator( '[data-setup-guide-step].is-skipped' ) ).toHaveCount( skipped );
		}
		await expect( guide( page ).locator( '[data-setup-guide-step].is-done' ) ).toHaveCount( 1 );

		const summary = guide( page ).locator( '[data-setup-guide-pane="all_done"]' );
		await expect( summary ).toBeVisible();
		await expect( summaryEntry ).toHaveAttribute( 'aria-current', 'step' );
		// One row for each step, in its state, with a way back to it.
		await expect( summary.locator( '[data-setup-guide-summary="1"] tr' ) ).toHaveCount( total );
		await expect( summary.locator( 'tr.is-done' ) ).toHaveCount( 1 );
		await expect( summary.locator( 'tr.is-skipped [data-setup-guide-goto]' ) ).toHaveCount( total - 1 );

		// A skipped step is one click away from being set up after all.
		await summary.locator( 'tr.is-skipped [data-setup-guide-goto]' ).first().click();
		await expect( guide( page ).locator( '[data-setup-guide-pane]:not([hidden]) form.merlin-form' ).first() ).toBeVisible();

		// With nothing left undecided the guide no longer shows by itself.
		await openDashboard( page );
		await expect( guide( page ) ).toHaveCount( 0 );
	} );
} );
