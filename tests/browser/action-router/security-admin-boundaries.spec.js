const { test, expect } = require( './support/shield-test' );
const { dismissBlockingDialogs, openShieldRoute } = require( './support/shield-browser' );
const { expectConnectedNonEmptyReference } = require( './support/modal-accessibility' );
const { parseShieldAjaxJson, waitForShieldAjaxAction } = require( './support/security-assertions' );
const {
	assertSecurityAdminContract,
	expectPinInputIgnoredByPasswordManagers,
	expectSecurityAdminLocalisation,
	sendSecurityAdminAjax,
} = require( './support/security-admin-assertions' );

const ACCESSIBLE_DIALOG_ACTIVE_SELECTOR = '[data-shield-accessible-dialog="1"][aria-modal="true"]:not([aria-hidden="true"])';

test.setTimeout( 180_000 );

async function openWpGeneralOptions( page ) {
	await page.goto( '/wp-admin/options-general.php', { waitUntil: 'load' } );
	await dismissBlockingDialogs( page );
}

async function submitUnlockPin( page, contract, pinInput, pin ) {
	const response = waitForShieldAjaxAction( page, contract.action_slugs.sec_admin_login );
	await pinInput.fill( pin );
	await pinInput.press( 'Enter' );
	return parseShieldAjaxJson( await ( await response ).text() );
}

test( 'Security Admin direct-disable boundary clears state and unlocks protected surface', async ( { page, fixtureApi } ) => {
	await fixtureApi.withSecurityAdminFixture( 'direct-disable-ready', async ( rawContract ) => {
		const contract = assertSecurityAdminContract( rawContract, 'direct-disable-ready' );
		expect( contract.boundary_actions.direct_disable.action_data.ex ).toBe( contract.boundary_action_slugs.secadmin_remove_confirm );
		expect( contract.boundary_actions.direct_disable.action_data.exnonce ).toEqual( expect.any( String ) );
		expect( contract.current.enabled ).toBe( true );
		expect( contract.current.session_active ).toBe( true );
		expect( contract.current.selected_options.sec_admin_users ).toEqual( [ 'shield_fixture_registered_admin' ] );

		await openShieldRoute( page, {
			...contract.routes.configure,
			zone: contract.configure_focus.zone_key,
			row_key: contract.configure_focus.row_key,
			config_item: contract.configure_focus.config_item,
		} );
		const directDisable = page.locator( `a[href*="${contract.boundary_action_slugs.secadmin_remove_confirm}"]` ).first();
		await expect( directDisable ).toBeVisible( { timeout: 30_000 } );
		const href = await directDisable.getAttribute( 'href' );
		expect( href ).toEqual( expect.stringContaining( contract.boundary_action_slugs.secadmin_remove_confirm ) );

		await page.goto( href, { waitUntil: 'load' } );
		const afterDisable = await fixtureApi.inspectSecurityAdminFixture();
		expect( afterDisable.current.enabled ).toBe( false );
		expect( afterDisable.current.admin_access_key_present ).toBe( false );
		expect( afterDisable.current.selected_options.sec_admin_users ).toEqual( [] );
		expect( afterDisable.current.session_active ).toBe( false );

		await openShieldRoute( page, contract.routes.protected );
		await expect( page.locator( contract.selectors.overlay ) ).not.toBeVisible();
	} );
} );

test( 'Security Admin email override boundary exposes only the allowed local request path', async ( { page, fixtureApi } ) => {
	await fixtureApi.withSecurityAdminFixture( 'email-override-enabled', async ( rawContract ) => {
		const contract = assertSecurityAdminContract( rawContract, 'email-override-enabled' );
		expect( contract.expected.email_override_allowed ).toBe( true );

		await openShieldRoute( page, contract.routes.protected );
		await expect( page.locator( contract.selectors.overlay ) ).toBeVisible( { timeout: 30_000 } );
		await expect( page.locator( contract.selectors.email_override ) ).toBeVisible();
		const localisation = await expectSecurityAdminLocalisation( page, contract );
		expect( localisation.ajax.req_email_remove.exnonce ).toEqual( expect.any( String ) );

		const payload = await sendSecurityAdminAjax( page, 'req_email_remove' );
		expect( payload.success ).toBe( true );
		expect( payload.data ).toEqual( expect.any( Object ) );
	} );

	await fixtureApi.withSecurityAdminFixture( 'email-override-disabled', async ( rawContract ) => {
		const contract = assertSecurityAdminContract( rawContract, 'email-override-disabled' );
		expect( contract.expected.email_override_allowed ).toBe( false );

		await openShieldRoute( page, contract.routes.protected );
		await expect( page.locator( contract.selectors.overlay ) ).toBeVisible( { timeout: 30_000 } );
		await expect( page.locator( contract.selectors.email_override ) ).toHaveCount( 0 );
		await expectSecurityAdminLocalisation( page, contract );
	} );
} );

test( 'Security Admin restriction zones disable WP options only for non-Security-Admin state', async ( { page, fixtureApi } ) => {
	await fixtureApi.withSecurityAdminFixture( 'restriction-zones-locked', async ( rawContract ) => {
		const contract = assertSecurityAdminContract( rawContract, 'restriction-zones-locked' );
		expect( contract.expected.restrict_options ).toBe( true );

		await openWpGeneralOptions( page );
		const blogname = page.locator( contract.selectors.wp_option_blogname );
		await expect( blogname ).toBeVisible( { timeout: 30_000 } );
		await expect( blogname ).toBeDisabled();

		// The PIN dialog opened from a restricted option's unlock link.
		const unlockLink = page.locator( contract.selectors.unlock_link ).first();
		const dialog = page.locator( ACCESSIBLE_DIALOG_ACTIVE_SELECTOR );
		const pinInput = dialog.locator( contract.selectors.login_box_pin_input );
		await unlockLink.click();
		await expect( dialog ).toBeVisible();
		await expect( pinInput ).toBeFocused();
		await expectPinInputIgnoredByPasswordManagers( pinInput );

		await dialog.locator( contract.selectors.unlock_cancel ).click();
		await expect( dialog ).toHaveCount( 0 );
		await expect( unlockLink ).toBeFocused();

		// A wrong PIN keeps the dialog open, explains why, and clears the field for another try.
		await unlockLink.click();
		const rejected = await submitUnlockPin( page, contract, pinInput, contract.pins.invalid );
		expect( rejected.success ).toBe( false );
		expect( rejected.data.page_reload ).toBe( false );
		await expect( dialog ).toBeVisible();
		await expect( pinInput ).toHaveValue( '' );
		await expect( pinInput ).toHaveAttribute( 'aria-invalid', 'true' );
		await expect( pinInput ).toBeFocused();
		await expectConnectedNonEmptyReference( page, pinInput, 'aria-describedby' );

		// A correct PIN reloads the same page, where Shield (initialised on load) no longer restricts the options.
		const reloaded = page.waitForEvent( 'load' );
		const accepted = await submitUnlockPin( page, contract, pinInput, contract.pins.valid );
		expect( accepted.success ).toBe( true );
		await reloaded;
		await expect( page ).toHaveURL( /\/wp-admin\/options-general\.php$/ );
		await expect( page.locator( contract.selectors.unlock_link ) ).toHaveCount( 0 );
		await expect( blogname ).toBeEnabled();
		expect( ( await fixtureApi.inspectSecurityAdminFixture() ).current.session_active ).toBe( true );
	} );

	await fixtureApi.withSecurityAdminFixture( 'restriction-zones-active-admin', async ( rawContract ) => {
		const contract = assertSecurityAdminContract( rawContract, 'restriction-zones-active-admin' );
		expect( contract.expected.restrict_options ).toBe( false );

		await openWpGeneralOptions( page );
		const blogname = page.locator( contract.selectors.wp_option_blogname );
		await expect( blogname ).toBeVisible( { timeout: 30_000 } );
		await expect( blogname ).toBeEnabled();
	} );
} );

test( 'Security Admin persistent admins unlock protected surface without temporary session', async ( { page, fixtureApi } ) => {
	await fixtureApi.withSecurityAdminFixture( 'persistent-admin', async ( rawContract ) => {
		const contract = assertSecurityAdminContract( rawContract, 'persistent-admin' );
		expect( contract.expected.registered_sec_admin ).toBe( true );
		expect( contract.current.selected_options.sec_admin_users ).toContain( 'admin' );
		expect( contract.current.session_active ).toBe( false );
		expect( contract.current.secadmin_at ).toBe( 0 );

		await openShieldRoute( page, contract.routes.protected );
		await expect( page.locator( contract.selectors.overlay ) ).not.toBeVisible();

		const afterRoute = await fixtureApi.inspectSecurityAdminFixture();
		expect( afterRoute.current.session_active ).toBe( false );
		expect( afterRoute.current.secadmin_at ).toBe( 0 );
	} );
} );
