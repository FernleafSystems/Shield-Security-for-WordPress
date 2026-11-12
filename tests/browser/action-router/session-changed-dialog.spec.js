const {
	test,
	expect,
	submitWordPressLogin,
	waitForShieldPage,
} = require( './support/shield-test' );
const { openShieldRoute } = require( './support/shield-browser' );
const { expectAccessibleMessageDialog } = require( './support/modal-accessibility' );
const {
	liveTrafficToggleRequest,
	parseShieldAjaxJson,
} = require( './support/security-assertions' );

const DIALOG_TITLE_SELECTOR = '.shield-accessible-dialog__title';
const CONFIRM_BUTTON_SELECTOR = '.shield-accessible-dialog__confirm';

test.setTimeout( 180_000 );

/**
 * The user logs in again in another tab of the same browser. The browser now holds a new login session, while
 * the pages already open still carry the action nonces of the old one.
 */
async function logInAgainInAnotherTab( context ) {
	const loginPage = await context.newPage();
	try {
		await loginPage.goto( '/wp-login.php?reauth=1', { waitUntil: 'load' } );
		// WordPress knows who was logged in, so it fills in the username and, a moment after the page loads,
		// empties the password field and puts the cursor in it. A password typed before then is wiped.
		await expect( loginPage.locator( '#user_pass' ) ).toBeFocused();
		await submitWordPressLogin( loginPage );
	}
	finally {
		await loginPage.close();
	}
}

test( 'a page left open across a new login asks for a reload in a dialog, and works again once reloaded', async ( { page, context, fixtureApi } ) => {
	await fixtureApi.withLiveTrafficToggleFixture( async ( contract ) => {
		await openShieldRoute( page, contract.route );

		const toggle = page.locator( contract.selectors.toggle );
		await expect( toggle ).toBeEnabled();
		await expect( toggle ).not.toBeChecked();

		await logInAgainInAnotherTab( context );

		const refusedResponse = page.waitForResponse(
			( response ) => liveTrafficToggleRequest( contract.action_slug, 'Y' )( response.request() )
		);
		await toggle.check();
		const refused = await refusedResponse;
		const refusedPayload = parseShieldAjaxJson( await refused.text() );

		expect( refused.status() ).toBe( 401 );
		expect( refusedPayload ).toHaveProperty( 'success', false );
		expect( refusedPayload.data.session_changed ).toBe( true );
		expect( refusedPayload.data.error_code ).toBe( 'session_changed' );

		const dialog = await expectAccessibleMessageDialog( page );
		await expect( dialog.locator( DIALOG_TITLE_SELECTOR ) ).toBeVisible();
		await expect( dialog.locator( DIALOG_TITLE_SELECTOR ) ).toHaveText( refusedPayload.data.dialog.title );
		await expect( dialog ).toContainText( refusedPayload.data.message );
		const reload = dialog.locator( CONFIRM_BUTTON_SELECTOR );
		await expect( reload ).toHaveText( refusedPayload.data.dialog.confirm_label );

		const inspected = await fixtureApi.inspectLiveTrafficToggleFixture();
		expect( inspected.state.enable_live_log, 'The refused request must not have changed anything.' ).toBe( 'N' );

		await Promise.all( [
			page.waitForEvent( 'load' ),
			reload.click(),
		] );
		await waitForShieldPage( page );

		// The reloaded page carries nonces for the new session, so the same action now goes through.
		const reloadedToggle = page.locator( contract.selectors.toggle );
		await expect( reloadedToggle ).toBeEnabled();
		await expect( reloadedToggle ).not.toBeChecked();
		const acceptedResponse = page.waitForResponse(
			( response ) => liveTrafficToggleRequest( contract.action_slug, 'Y' )( response.request() )
		);
		await reloadedToggle.check();
		const acceptedPayload = parseShieldAjaxJson( await ( await acceptedResponse ).text() );

		expect( acceptedPayload ).toHaveProperty( 'success', true );
		expect( acceptedPayload.data.is_enabled ).toBe( true );
		expect( ( await fixtureApi.inspectLiveTrafficToggleFixture() ).state.enable_live_log ).toBe( 'Y' );
	} );
} );
