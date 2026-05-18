const { test, expect, openShieldRoute } = require( './support/shield-test' );
const { expectModalHiddenWithoutAriaModal } = require( './support/modal-accessibility' );

const ACTIVE_DIALOG = '[data-shield-accessible-dialog="1"][aria-modal="true"]:not([aria-hidden="true"])';
const DIALOG = '[data-shield-accessible-dialog="1"]';

function requestParams( request ) {
	return new URLSearchParams( request.postData() || '' );
}

function isShieldActionRequest( request, executeSlug ) {
	if ( request.method() !== 'POST' || !request.url().includes( '/admin-ajax.php' ) ) {
		return false;
	}

	const params = requestParams( request );
	return params.get( 'action' ) === 'shield_action'
		&& params.get( 'ex' ) === executeSlug;
}

function installNativeDialogGuard( page ) {
	const nativeDialogs = [];
	page.on( 'dialog', async ( dialog ) => {
		nativeDialogs.push( dialog.type() );
		await dialog.dismiss().catch( () => null );
	} );
	return nativeDialogs;
}

function installRuntimeErrorGuard( page ) {
	const errors = [];
	page.on( 'pageerror', ( error ) => {
		errors.push( error.message );
	} );
	return errors;
}

async function expectNamedDialog( page, dialog ) {
	await expect( dialog ).toHaveAttribute( 'role', 'dialog' );
	await expect( dialog ).toHaveAttribute( 'aria-modal', 'true' );
	const labelID = await dialog.getAttribute( 'aria-labelledby' );
	expect( labelID || '' ).not.toHaveLength( 0 );
	await expect( page.locator( `#${ labelID }` ) ).toHaveAccessibleName( /\S/ );
}

test( 'license clear confirm dispatches stable action and clears pro state', async ( { page, fixtureApi } ) => {
	const nativeDialogs = installNativeDialogGuard( page );
	const runtimeErrors = installRuntimeErrorGuard( page );

	await fixtureApi.withLicenseClearFixture( async ( fixture ) => {
		await openShieldRoute( page, fixture.route );

		const clearAction = page.locator( fixture.selectors.clear ).first();
		await expect( clearAction ).toBeVisible( { timeout: 20_000 } );
		await expect( clearAction ).toBeEnabled();
		await expect( clearAction ).toHaveRole( 'button' );
		await expect( clearAction ).toHaveAccessibleName( /\S/ );

		let matchingRequests = 0;
		page.on( 'request', ( request ) => {
			if ( isShieldActionRequest( request, fixture.action_slug ) ) {
				matchingRequests++;
			}
		} );

		await clearAction.click();
		let dialog = page.locator( ACTIVE_DIALOG );
		await expect( dialog ).toBeVisible();
		await expectNamedDialog( page, dialog );
		await dialog.locator( '.shield-accessible-dialog__cancel' ).click();
		await expectModalHiddenWithoutAriaModal( page, DIALOG );
		await expect( clearAction ).toBeFocused();
		await page.waitForTimeout( 250 );
		expect( matchingRequests ).toBe( 0 );

		let busyDuringRequest = null;
		await page.route( '**/wp-admin/admin-ajax.php', async ( route ) => {
			if ( isShieldActionRequest( route.request(), fixture.action_slug ) ) {
				busyDuringRequest = await page.locator( fixture.selectors.page )
					.getAttribute( 'aria-busy' )
					.catch( () => null );
			}
			await route.continue();
		} );

		await clearAction.click();
		dialog = page.locator( ACTIVE_DIALOG );
		await expect( dialog ).toBeVisible();
		await expectNamedDialog( page, dialog );

		const clearResponse = page.waitForResponse(
			( response ) => response.ok() && isShieldActionRequest( response.request(), fixture.action_slug ),
			{ timeout: 20_000 }
		);
		await dialog.locator( '.shield-accessible-dialog__confirm' ).click();
		await clearResponse;

		expect( busyDuringRequest ).toBe( 'true' );
		await expect( page.locator( fixture.selectors.page ) ).not.toHaveAttribute( 'aria-busy', 'true' );
		await page.unroute( '**/wp-admin/admin-ajax.php' );

		await expect.poll( async () => {
			const inspected = await fixtureApi.inspectLicenseClearFixture();
			return inspected.state.license_data_empty;
		}, { timeout: 20_000 } ).toBe( true );

		const inspected = await fixtureApi.inspectLicenseClearFixture();
		expect( inspected.state.license_active ).toBe( false );
		expect( inspected.state.has_valid_working_license ).toBe( false );
		expect( inspected.state.is_premium_active ).toBe( false );
		expect( inspected.state.can_reports_local ).toBe( false );
		expect( inspected.state.can_site_blockdown ).toBe( false );
		expect( inspected.state.can_whitelabel ).toBe( false );
		expect( inspected.state.license_deactivated_at ).toBeGreaterThanOrEqual(
			Number( fixture.state.license_activated_at || 0 )
		);
		expect( matchingRequests ).toBe( 1 );
		expect( nativeDialogs ).toEqual( [] );
		expect( runtimeErrors ).toEqual( [] );
	} );
} );
