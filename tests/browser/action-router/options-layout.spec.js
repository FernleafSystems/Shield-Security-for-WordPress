const { test, expect } = require( './support/shield-test' );
const { expectNoAxeViolations } = require( './support/accessibility' );
const { openShieldRoute } = require( './support/shield-browser' );

// Settings opened in a side panel are laid out as their Configure topic's panel, like Configure itself.
test( 'Integrations opens its Configure panel in an accessible side panel', async ( { page } ) => {
	await openShieldRoute( page, { nav: 'zones', nav_sub: 'overview' } );
	await page.locator( '#NavSideBar button[data-configure_panel="integrations:integrations"]' ).click();
	const canvas = page.locator( '#AptoOffcanvas' );
	await expect( canvas ).toHaveClass( /(^|\s)show(\s|$)/ );
	const form = canvas.locator( 'form.options_form_for--panel' );
	await expect( form ).toBeVisible();
	await expect( form.locator( '[data-option-key="enable_mainwp"]' ) ).toBeVisible();
	await expect( form.locator( '.shield-options-rail-save button[type="submit"]' ) ).toBeVisible();
	await expectNoAxeViolations( page, '#AptoOffcanvas' );
} );

test( 'Run Scan opens file areas beneath the file scanning switch', async ( { page } ) => {
	await openShieldRoute( page, { nav: 'scans', nav_sub: 'run', step: 'choose' } );
	await page.locator( '.shield-scan-choose__configure' ).click();
	const form = page.locator( '#AptoOffcanvas form.options_form_for--panel' );
	await expect( form ).toBeVisible();
	await expect( form.locator( '[data-option-key="enable_core_file_integrity_scan"]' ) ).toBeVisible();
	await expect( form.locator( 'input[name="file_scan_areas[]"]' ).first() ).toBeVisible();
	// WordPress core files can be chosen on every plan.
	await expect( form.locator( 'input[name="file_scan_areas[]"][value="wp"]' ) ).toBeEnabled();
	expect( ( await form.locator( 'input[name="all_opts_keys"]' ).inputValue() ).split( ',' ).sort() )
		.toEqual( [ 'enable_core_file_integrity_scan', 'file_scan_areas' ] );
} );

test( 'Reports settings show the Configure panels for reports and instant alerts as a component list', async ( { page } ) => {
	await openShieldRoute( page, {
		nav: 'reports',
		nav_sub: 'overview',
		workspace: 'settings',
	} );

	const settings = page.locator( '[data-reports-tabpanel="settings"]' );
	await expect( settings.locator( '[data-configure-diagnosis="1"]' ) ).toBeVisible();
	// The list opens on its first component; choosing the other shows it instead.
	await expect( settings.locator( '[data-configure-row-key="reports"] form.options_form_for--panel' ) ).toBeVisible( { timeout: 20_000 } );
	await expect( settings.locator( '[data-option-key="block_send_email_address"]' ) ).toBeVisible();
	await settings.locator( '[data-configure-row-key="instant_alerts"] .shield-detail-row' ).first().click();
	await expect( settings.locator( '[data-configure-row-key="instant_alerts"] form.options_form_for--panel' ) ).toBeVisible( { timeout: 20_000 } );
} );
