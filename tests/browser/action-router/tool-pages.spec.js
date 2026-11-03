const { openShieldRoute, test, expect } = require( './support/shield-test' );
const { expectNoAxeViolations } = require( './support/accessibility' );

/**
 * Site Lockdown, White Label and Hide Login are pages of their own: each opens on where things stand (for
 * White Label, its own on/off switch), lays out its settings, and passes the accessibility checks.
 */

test( 'site lockdown shows where things stand, who still gets in, and the confirmations', async ( { page } ) => {
	await openShieldRoute( page, { nav: 'tools', nav_sub: 'blockdown' } );
	const tool = page.locator( '[data-shield-lockdown="1"]' );
	await expect( tool.locator( '.shield-tool-status' ) ).toBeVisible();
	await expect( tool.locator( '[data-lockdown-confirm="1"]' ) ).toHaveCount( 4 );
	await expectNoAxeViolations( page, '[data-shield-lockdown="1"]' );
} );

test( 'white label heads its settings with its own switch, beside a preview that follows the form', async ( { page } ) => {
	await openShieldRoute( page, { nav: 'tools', nav_sub: 'whitelabel' } );
	const tool = page.locator( '[data-shield-whitelabel="1"]' );
	await expect( tool.locator( 'form.options_form_for--panel' ) ).toBeVisible();
	await expect( tool.locator( '.shield-tool-status' ) ).toHaveCount( 0 );
	await expect( tool.locator( '.shield-panel-block' ).first().locator( '[data-option-key="whitelabel_enable"] input[type="checkbox"]' ) ).toBeVisible();
	await tool.locator( 'input[name="wl_pluginnamemain"]' ).fill( 'Acme Guard' );
	await expect( tool.locator( '[data-whitelabel-preview="1"] [data-wl-preview="wl_pluginnamemain"]' ) ).toHaveText( 'Acme Guard' );
	await expectNoAxeViolations( page, '[data-shield-whitelabel="1"]' );
} );

test( 'hide login warns plainly that it is not a security setting', async ( { page } ) => {
	await openShieldRoute( page, { nav: 'tools', nav_sub: 'loginhide' } );
	const tool = page.locator( '[data-shield-loginhide="1"]' );
	await expect( tool.locator( '.shield-tool-warning' ) ).toBeVisible();
	await expect( tool.locator( '.shield-option-prefixed input[name="rename_wplogin_path"]' ) ).toBeVisible();
	await expectNoAxeViolations( page, '[data-shield-loginhide="1"]' );
} );
