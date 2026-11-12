const { test, expect } = require( './support/shield-test' );
const { expectNoAxeViolations } = require( './support/accessibility' );
const { openShieldRoute } = require( './support/shield-browser' );

/**
 * The admin screens' own controls, by keyboard and to assistive technology: Configure's goal tools and save bar,
 * the Actions Queue and its results, the search dialog, the tool pages and the setup guide.
 */

test( 'a Configure goal names its filter and views, and its save bar is announced and reachable', async ( { page } ) => {
	await openShieldRoute( page, { nav: 'zones', nav_sub: 'overview', zone: 'login' } );

	const goal = page.locator( '[data-configure-diagnosis="1"]' ).first();
	await expect( goal ).toBeVisible( { timeout: 20_000 } );

	// The filter sits on the goal's title line, with a name of its own.
	const filter = goal.locator( '[data-configure-zone-filter="1"]' );
	await expect( filter ).toHaveAccessibleName( /\S/ );
	await expect( goal.locator( '[data-configure-zone-show="all"]' ) ).toHaveAttribute( 'aria-pressed', 'true' );
	await expect( goal.locator( '[data-configure-zone-layout]' ) ).toHaveCount( 0 );

	// Changing a setting brings up the save bar, a named region whose count is announced.
	const toggle = goal.locator( '.shield-option-item input[type="checkbox"]:visible' ).first();
	await expect( toggle ).toBeVisible( { timeout: 20_000 } );
	await toggle.focus();
	await toggle.press( 'Space' );
	const bar = goal.locator( '[data-configure-savebar="1"]' );
	await expect( bar ).toBeVisible();
	await expect( bar ).toHaveAttribute( 'role', 'region' );
	await expect( bar ).toHaveAccessibleName( /\S/ );
	await expect( bar.locator( '.shield-actionbar__copy' ) ).toHaveAttribute( 'aria-live', 'polite' );
	await expectNoAxeViolations( page, '#PageContainer-Apto' );

	await bar.locator( '[data-configure-savebar-discard="1"]' ).focus();
	await expect( bar.locator( '[data-configure-savebar-discard="1"]' ) ).toBeFocused();
	await page.keyboard.press( 'Enter' );
	await expect( bar ).toBeHidden();
} );

test( 'the Actions Queue opens results by keyboard as a dialog and returns focus on Escape', async ( { page } ) => {
	await openShieldRoute( page, { nav: 'scans', nav_sub: 'overview' } );

	const landing = page.locator( '[data-actions-landing="1"]' );
	await expect( landing ).toBeVisible();
	await expectNoAxeViolations( page, '#PageContainer-Apto' );

	const row = landing.locator( 'button.actions-queue-row.is-actionable[data-drill-target="detail"]' ).first();
	test.skip( await row.count() === 0, 'No issue group to open on this site' );
	await row.focus();
	await page.keyboard.press( 'Enter' );

	const results = landing.locator( '[data-drill-layer-key="detail"]' );
	await expect( results ).toHaveAttribute( 'role', 'dialog' );
	await expect( results ).toHaveAttribute( 'aria-modal', 'true' );
	await expect( results.locator( '[data-results-overlay-close="1"]' ) ).toHaveAccessibleName( /\S/ );

	await page.keyboard.press( 'Escape' );
	await expect( results ).not.toHaveAttribute( 'aria-modal', 'true' );
} );

test( 'the search dialog is one named box, its hints described, its results reachable by Tab', async ( { page } ) => {
	await openShieldRoute( page, { nav: 'dashboard', nav_sub: 'overview' } );

	await page.keyboard.press( 'Control+k' );
	const modal = page.locator( '#ModalSuperSearchBox.show' );
	await expect( modal ).toBeVisible();
	const input = page.locator( '#ModalSuperSearchInput' );
	await expect( input ).toBeFocused();
	await expect( input ).toHaveAccessibleName( /\S/ );
	await expect( input ).toHaveAccessibleDescription( /\S/ );
	await expect( modal.locator( '[data-super-search-hints="1"]' ) ).toBeVisible();
	await expectNoAxeViolations( page, '#ModalSuperSearchBox' );

	await input.fill( 'email' );
	const firstResult = modal.locator( '[data-super-search-body="1"] a.shield-search-results__item' ).first();
	await expect( firstResult ).toBeVisible( { timeout: 20_000 } );
	await expect( modal.locator( '[data-super-search-hints="1"]' ) ).toBeHidden();
	await expect( modal.locator( '[data-super-search-body="1"]' ) ).toHaveAttribute( 'aria-busy', 'false' );
	await expectNoAxeViolations( page, '#ModalSuperSearchBox' );

	// From the box, Tab goes past the close button to the first result.
	await page.keyboard.press( 'Tab' );
	await page.keyboard.press( 'Tab' );
	await expect( firstResult ).toBeFocused();

	await page.keyboard.press( 'Escape' );
	await expect( modal ).toBeHidden();
} );

for ( const tool of [ 'blockdown', 'whitelabel', 'loginhide' ] ) {
	test( `the ${tool} tool page passes axe`, async ( { page } ) => {
		await openShieldRoute( page, { nav: 'tools', nav_sub: tool } );
		await expect( page.locator( '#PageMainBody_Inner-Apto' ) ).toBeVisible();
		await expectNoAxeViolations( page, '#PageContainer-Apto' );
	} );
}

test( 'the Investigate lookup and each log tab pass axe', async ( { page } ) => {
	await openShieldRoute( page, { nav: 'activity', nav_sub: 'overview' } );
	const landing = page.locator( '[data-investigate-landing="1"]' );
	await expect( landing ).toBeVisible();
	await expectNoAxeViolations( page, '#PageContainer-Apto' );

	for ( const tab of [ 'activity', 'requests', 'ip_rules', 'sessions' ] ) {
		await landing.locator( `[data-investigate-tab="${tab}"]` ).click();
		const panel = landing.locator( `[data-investigate-tabpanel="${tab}"]` );
		await expect( panel ).toBeVisible();
		await expect( panel.locator( 'table.dataTable' ) ).toBeVisible( { timeout: 20_000 } );
		await expectNoAxeViolations( page, '#PageContainer-Apto' );
	}
} );

test( 'the setup guide, opened, shows one step and passes axe', async ( { page } ) => {
	await openShieldRoute( page, { nav: 'dashboard', nav_sub: 'overview', setup_guide: 'open' } );
	const guide = page.locator( '#ShieldSetupGuide' );
	test.skip( await guide.count() === 0, 'The setup guide is finished on this site' );
	await expect( guide.locator( '[data-setup-guide-toggle="1"]' ) ).toHaveAttribute( 'aria-expanded', 'true' );
	// It opens on one step: the next to do, or that setup is complete.
	await expect( guide.locator( '[data-setup-guide-pane]:not([hidden])' ) ).toHaveCount( 1 );
	await expectNoAxeViolations( page, '#ShieldSetupGuide' );
} );
