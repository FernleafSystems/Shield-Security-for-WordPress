const { AxeBuilder, test, expect } = require( './support/shield-test' );
const { dismissBlockingDialogs, openShieldRoute } = require( './support/shield-browser' );
const {
	expectAccessibleMessageDialog,
	expectConnectedNonEmptyReference,
	expectFocusWithin,
	expectOptionalDescription,
} = require( './support/modal-accessibility' );
const {
	collectRuntimeErrors,
	expectNoRuntimeErrors,
	expectShieldAjaxSuccess,
	parseShieldAjaxJson,
	waitForShieldAjaxAction,
} = require( './support/security-assertions' );

const ACCESSIBLE_DIALOG_ACTIVE_SELECTOR = '[data-shield-accessible-dialog="1"][aria-modal="true"]:not([aria-hidden="true"])';

test.setTimeout( 180_000 );

function assertSecurityAdminContract( contract, scenario ) {
	expect( contract ).toEqual( expect.objectContaining( {
		scenario,
		scenarios: expect.arrayContaining( [ scenario ] ),
		routes: expect.any( Object ),
		configure_focus: expect.any( Object ),
		action_slugs: expect.any( Object ),
		render_slugs: expect.any( Object ),
		option_keys: expect.any( Array ),
		options: expect.any( Object ),
		pins: expect.any( Object ),
		timeout: expect.any( Object ),
		selectors: expect.any( Object ),
		expected: expect.any( Object ),
	} ) );
	expect( contract.action_slugs.sec_admin_login ).toBe( 'sec_admin_login' );
	expect( contract.action_slugs.sec_admin_check ).toBe( 'sec_admin_check' );
	expect( contract.action_slugs.sec_admin_auth_clear ).toBe( 'sec_admin_auth_clear' );
	expect( contract.action_slugs.module_options_save ).toBe( 'mod_options_save' );
	expect( contract.out_of_scope_action_slugs.secadmin_remove_confirm ).toBe( 'secadmin_remove_confirm' );
	expect( contract.out_of_scope_action_slugs.req_email_remove ).toBe( 'req_email_remove' );
	return contract;
}

async function expectRestrictedOverlay( page, contract ) {
	const overlay = page.locator( contract.selectors.overlay );
	await expect( overlay ).toBeVisible( { timeout: 30_000 } );
	await expect( overlay ).toHaveAttribute( 'role', 'dialog' );
	await expect( overlay ).toHaveAttribute( 'tabindex', '-1' );
	await expectConnectedNonEmptyReference( page, overlay, 'aria-labelledby' );
	await expectConnectedNonEmptyReference( page, overlay, 'aria-describedby' );

	await expect( page.locator( contract.selectors.overlay_form ) ).toBeVisible();
	await expect( page.locator( contract.selectors.overlay_pin_input ) ).toBeVisible();
	return overlay;
}

async function expectSecurityAdminLocalisation( page, contract ) {
	const component = await page.evaluate( () => window.shield_vars_main?.comps?.sec_admin ?? null );
	expect( component ).toEqual( expect.objectContaining( {
		ajax: expect.any( Object ),
		flags: expect.any( Object ),
		vars: expect.any( Object ),
	} ) );
	expect( component.ajax.sec_admin_login.ex ).toBe( contract.action_slugs.sec_admin_login );
	expect( component.ajax.sec_admin_check.ex ).toBe( contract.action_slugs.sec_admin_check );
	expect( component.ajax.req_email_remove.ex ).toBe( contract.out_of_scope_action_slugs.req_email_remove );
	expect( typeof component.flags.restrict_options ).toBe( 'boolean' );
	expect( typeof component.flags.run_checks ).toBe( 'boolean' );
	expect( typeof component.vars.time_remaining ).toBe( 'number' );
	expect( await page.evaluate( () => Boolean( window.shieldAppMain?.components?.sec_admin ) ) ).toBe( true );
}

async function openFocusedConfigureForm( page, contract ) {
	await openShieldRoute( page, {
		...contract.routes.configure,
		zone: contract.configure_focus.zone_key,
		row_key: contract.configure_focus.row_key,
		config_item: contract.configure_focus.config_item,
	} );
	await dismissBlockingDialogs( page );

	const row = page.locator( contract.selectors.configure_row );
	await expect( row ).toBeVisible( { timeout: 30_000 } );
	const form = row.locator( contract.selectors.options_form ).first();
	await expect( form ).toBeVisible( { timeout: 30_000 } );
	return { row, form };
}

async function submitOverlayPin( page, contract, pin ) {
	const response = waitForShieldAjaxAction( page, contract.action_slugs.sec_admin_login );
	await page.locator( contract.selectors.overlay_pin_input ).fill( pin );
	await page.locator( contract.selectors.overlay_form ).locator( 'button[type="submit"]' ).click();
	const payload = parseShieldAjaxJson( await ( await response ).text() );
	return payload;
}

async function sendSecurityAdminAjax( page, ajaxKey, extraData = {} ) {
	const response = await page.evaluate( async ( { currentAjaxKey, currentExtraData } ) => {
		const requestData = window.shield_vars_main?.comps?.sec_admin?.ajax?.[ currentAjaxKey ];
		if ( !requestData || typeof requestData.ajaxurl !== 'string' ) {
			throw new Error( `Missing Security Admin AJAX request data for ${ currentAjaxKey }.` );
		}
		if ( !new URL( requestData.ajaxurl, window.location.href ).pathname.endsWith( '/admin-ajax.php' ) ) {
			throw new Error( `Security Admin AJAX request data for ${ currentAjaxKey } does not target admin-ajax.php.` );
		}

		const raw = await fetch( requestData.ajaxurl, {
			method: 'POST',
			credentials: 'same-origin',
			headers: {
				'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8',
				'X-Requested-With': 'XMLHttpRequest',
			},
			body: new URLSearchParams( {
				...requestData,
				...currentExtraData,
				apto_wrap_response: '1',
			} ),
		} );

		return {
			ok: raw.ok,
			body: await raw.text(),
		};
	}, {
		currentAjaxKey: ajaxKey,
		currentExtraData: extraData,
	} );

	expect( response.ok ).toBe( true );
	return parseShieldAjaxJson( response.body );
}

test( 'Security Admin zone contract locks protected routes and restores cleanup state', async ( { page, fixtureApi } ) => {
	let originalOptions = {};
	await fixtureApi.withSecurityAdminFixture( 'locked', async ( rawContract ) => {
		const contract = assertSecurityAdminContract( rawContract, 'locked' );
		originalOptions = contract.original_options;
		const runtimeErrors = collectRuntimeErrors( page );
		expect( contract.current.enabled ).toBe( contract.expected.enabled );
		expect( contract.current.session_active ).toBe( contract.expected.session_active );
		expect( contract.current.admin_access_key_hash_format ).toBe( contract.expected.pin_hash_format );

		await openShieldRoute( page, contract.routes.protected );
		await expectRestrictedOverlay( page, contract );
		await expectSecurityAdminLocalisation( page, contract );

		const usersMenu = page.locator( '#menu-users' );
		await usersMenu.hover();
		const usersLink = page.locator( '#menu-users .wp-submenu a[href$="users.php"]' ).first();
		await expect( usersLink ).toBeVisible();

		await Promise.all( [
			page.waitForURL( /\/wp-admin\/users\.php(?:\?|$)/, { timeout: 20_000 } ),
			usersLink.click(),
		] );
		await expect( page.locator( 'body.users-php' ) ).toBeVisible();
		await expectNoRuntimeErrors( runtimeErrors, 'security admin locked zone contract' );
	} );

	const inspection = await fixtureApi.inspectSecurityAdminFixture();
	expect( inspection.fixture_state_present ).toBe( false );
	expect( inspection.current.selected_options ).toEqual( originalOptions );
} );

test( 'Security Admin PIN is set through configure and verified through login', async ( { page, fixtureApi } ) => {
	await fixtureApi.withSecurityAdminFixture( 'pin-unset', async ( rawContract ) => {
		const contract = assertSecurityAdminContract( rawContract, 'pin-unset' );

		const { row, form } = await openFocusedConfigureForm( page, contract );
		await form.locator( contract.selectors.pin_option ).fill( contract.pins.new );
		await form.locator( contract.selectors.pin_option_confirm ).fill( contract.pins.new );

		const saveResponse = waitForShieldAjaxAction( page, contract.action_slugs.module_options_save );
		await row.locator( '.shield-detail-expansion__btn-save' ).click();
		const savePayload = await expectShieldAjaxSuccess( await saveResponse );
		expect( savePayload.data.page_reload ).toBe( true );

		const afterSave = await fixtureApi.inspectSecurityAdminFixture();
		expect( afterSave.current.enabled ).toBe( true );
		expect( afterSave.current.admin_access_key_hash_format ).toBe( 'md5' );
		expect( afterSave.current.session_active ).toBe( false );

		await openShieldRoute( page, contract.routes.protected );
		await expectRestrictedOverlay( page, contract );
		const loginPayload = await submitOverlayPin( page, contract, contract.pins.new );
		expect( loginPayload.success ).toBe( true );
		expect( loginPayload.data.page_reload ).toBe( true );

		const afterLogin = await fixtureApi.inspectSecurityAdminFixture();
		expect( afterLogin.current.session_active ).toBe( true );
		expect( afterLogin.current.time_remaining ).toBeGreaterThan( 0 );
		expect( afterLogin.current.admin_access_key_hash_format ).toBe( 'wp_hash' );
	} );
} );

test( 'Security Admin rejects incorrect PIN without granting session state', async ( { page, fixtureApi } ) => {
	await fixtureApi.withSecurityAdminFixture( 'locked', async ( rawContract ) => {
		const contract = assertSecurityAdminContract( rawContract, 'locked' );

		await openShieldRoute( page, contract.routes.protected );
		await expectRestrictedOverlay( page, contract );
		const loginPayload = await submitOverlayPin( page, contract, contract.pins.invalid );
		expect( loginPayload.success ).toBe( false );

		const inspection = await fixtureApi.inspectSecurityAdminFixture();
		expect( inspection.current.session_active ).toBe( false );
		expect( inspection.current.secadmin_at ).toBe( 0 );
		expect( inspection.current.this_req_is_security_admin ).toBe( false );

		await openShieldRoute( page, contract.routes.protected );
		await expectRestrictedOverlay( page, contract );
	} );
} );

test( 'Security Admin timeout uses expired PHP session state and accessible browser handling', async ( { page, fixtureApi } ) => {
	const nativeDialogs = [];
	page.on( 'dialog', async ( dialog ) => {
		nativeDialogs.push( dialog.type() );
		await dialog.dismiss().catch( () => null );
	} );

	await fixtureApi.withSecurityAdminFixture( 'expired-session', async ( rawContract ) => {
		const contract = assertSecurityAdminContract( rawContract, 'expired-session' );
		const inspection = await fixtureApi.inspectSecurityAdminFixture();
		expect( inspection.current.session_active ).toBe( contract.expected.session_active );
		if ( contract.expected.time_remaining_is_zero ) {
			expect( inspection.current.time_remaining ).toBe( 0 );
		}
		expect( inspection.current.secadmin_at ).toBeGreaterThan( 0 );

		await openShieldRoute( page, contract.routes.protected );
		await expectRestrictedOverlay( page, contract );
		await expectSecurityAdminLocalisation( page, contract );

		const checkPayload = await sendSecurityAdminAjax( page, 'sec_admin_check' );
		expect( checkPayload.success ).toBe( false );
		expect( checkPayload.data.time_remaining ).toBe( 0 );

		await page.evaluate( ( payload ) => {
			window.shieldAppMain.components.sec_admin.handleSecAdminCheck( payload );
		}, checkPayload );

		const dialog = await expectAccessibleMessageDialog( page );
		await expectOptionalDescription( page, dialog );
		await expectFocusWithin( dialog );
		const results = await new AxeBuilder( { page } )
			.include( ACCESSIBLE_DIALOG_ACTIVE_SELECTOR )
			.analyze();
		expect( results.violations, JSON.stringify( results.violations, null, 2 ) ).toEqual( [] );
		expect( nativeDialogs ).toEqual( [] );
	} );
} );

test( 'Security Admin end-session action clears active session and relocks protected surface', async ( { page, fixtureApi } ) => {
	const original = await fixtureApi.inspectSecurityAdminFixture();
	await fixtureApi.withSecurityAdminFixture( 'active-session', async ( rawContract ) => {
		const contract = assertSecurityAdminContract( rawContract, 'active-session' );
		const before = await fixtureApi.inspectSecurityAdminFixture();
		expect( before.current.session_active ).toBe( contract.expected.session_active );
		expect( before.current.time_remaining ).toBeGreaterThan( 0 );

		await openShieldRoute( page, contract.routes.protected );
		await page.locator( contract.selectors.page_action_menu ).click();
		const endSession = page.locator( contract.selectors.end_session_action ).first();
		await expect( endSession ).toBeVisible( { timeout: 30_000 } );
		await Promise.all( [
			page.waitForLoadState( 'domcontentloaded' ),
			endSession.click(),
		] );

		const after = await fixtureApi.inspectSecurityAdminFixture();
		expect( after.current.session_active ).toBe( false );
		expect( after.current.secadmin_at ).toBe( 0 );
		expect( after.current.this_req_is_security_admin ).toBe( false );

		await openShieldRoute( page, contract.routes.protected );
		await expectRestrictedOverlay( page, contract );
	} );

	const restored = await fixtureApi.inspectSecurityAdminFixture();
	expect( restored.fixture_state_present ).toBe( false );
	expect( restored.current.secadmin_at ).toBe( original.current.secadmin_at );
	expect( restored.current.session_active ).toBe( original.current.session_active );
	expect( restored.current.this_req_is_security_admin ).toBe( original.current.this_req_is_security_admin );
	expect( restored.current.selected_options ).toEqual( original.current.selected_options );
} );
