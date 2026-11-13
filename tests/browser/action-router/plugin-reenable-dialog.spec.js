const { openShieldRoute, test, expect } = require( './support/shield-test' );
const { expectNamedDialog, expectFocusWithin } = require( './support/modal-accessibility' );
const { expectNoAxeViolationsInDialog } = require( './support/security-assertions' );

// With all protection switched off, the notice offers one switch to turn it back on rather than a settings form.
test( 'disabled-protection notice turns protection back on from a one-switch dialog', async ( { page, fixtureApi } ) => {
	await fixtureApi.withPluginDisabledFixture( async () => {
		await openShieldRoute( page, { nav: 'dashboard', nav_sub: 'overview' } );
		const notice = page.locator( '[data-notice_id="plugin_globally_disabled"]' );
		const launcher = notice.locator( 'button[data-enable-protection]' );
		await expect( launcher ).toBeVisible();

		await launcher.click();
		const dialog = page.locator( '#ShieldMainAccessibleDialog' );
		await expectNamedDialog( page, dialog );
		await expectFocusWithin( dialog );
		await expect( page.locator( '.offcanvas.show' ) ).toHaveCount( 0 );
		await expectNoAxeViolationsInDialog( page, { dialog: 'ShieldMainAccessibleDialog' } );

		const save = dialog.getByRole( 'button', { name: 'Save', exact: true } );
		await expect( save ).toBeDisabled();
		await dialog.getByRole( 'switch' ).check();
		await expect( save ).toBeEnabled();
		await Promise.all( [
			page.waitForEvent( 'load' ),
			save.click(),
		] );

		await expect( page.locator( '[data-notice_id="plugin_globally_disabled"]' ) ).toHaveCount( 0 );
		expect( ( await fixtureApi.inspectPluginDisabledFixture() ).state.plugin_enabled ).toBe( true );
	} );
} );
