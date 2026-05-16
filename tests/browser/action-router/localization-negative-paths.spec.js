const { AxeBuilder, expect, openShieldRoute, test } = require( './support/shield-test' );

function requestParams( request ) {
	return new URLSearchParams( request.postData() || '' );
}

function isShieldActionRequest( request, executeSlug, expectedPayload = {} ) {
	if ( request.method() !== 'POST' || !request.url().includes( '/admin-ajax.php' ) ) {
		return false;
	}

	const params = requestParams( request );
	if ( params.get( 'action' ) !== 'shield_action' || params.get( 'ex' ) !== executeSlug ) {
		return false;
	}

	return Object.entries( expectedPayload ).every( ( [ key, value ] ) => params.get( key ) === String( value ) );
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
	const runtimeErrors = [];
	page.on( 'pageerror', ( error ) => runtimeErrors.push( error.message ) );
	page.on( 'console', ( message ) => {
		if ( message.type() === 'error' ) {
			runtimeErrors.push( message.text() );
		}
	} );
	return runtimeErrors;
}

function formatAxeViolations( violations ) {
	return violations.map( ( violation ) => {
		const targets = violation.nodes
		.flatMap( ( node ) => node.target || [] )
		.slice( 0, 5 )
		.join( ', ' );

		return `${violation.id}: ${targets}`;
	} ).join( '\n' );
}

async function expectNoAxeViolations( page, selector ) {
	const results = await new AxeBuilder( { page } )
	.include( selector )
	.analyze();

	expect( results.violations, formatAxeViolations( results.violations ) ).toEqual( [] );
}

async function expectVisibleAccessibleDialog( page ) {
	const dialog = page.locator( '[data-shield-accessible-dialog="1"][aria-modal="true"]:not([aria-hidden="true"])' );
	await expect( dialog ).toBeVisible();
	await expect( dialog ).toHaveAttribute( 'role', 'dialog' );
	await expect( dialog ).toHaveAccessibleName( /\S/ );
	await expect( dialog.locator( '.shield-accessible-dialog__confirm' ) ).toHaveAccessibleName( /\S/ );
	await expect( dialog.locator( '.shield-accessible-dialog__cancel' ) ).toHaveAccessibleName( /\S/ );
	await expect.poll( async () => dialog.evaluate( ( node ) => node.contains( document.activeElement ) ) ).toBe( true );
	await expectNoAxeViolations( page, '[data-shield-accessible-dialog="1"]' );
	return dialog;
}

async function expectDialogClosed( page ) {
	await expect(
		page.locator( '[data-shield-accessible-dialog="1"][aria-modal="true"]:not([aria-hidden="true"])' )
	).toHaveCount( 0 );
}

async function blankPluginAdminDialogStrings( page ) {
	await page.evaluate( () => {
		window.shield_vars_main = window.shield_vars_main || {};
		window.shield_vars_main.strings = {};
		if ( window.shieldStrings && typeof window.shieldStrings === 'object' ) {
			window.shieldStrings._base_data = {};
		}
	} );
}

async function blankWpAdminDialogStrings( page ) {
	await page.evaluate( () => {
		window.shield_vars_wpadmin = window.shield_vars_wpadmin || {};
		window.shield_vars_wpadmin.strings = {};
	} );
}

test( 'plugin admin confirm remains accessible when main localized dialog strings are empty', async ( { page, fixtureApi } ) => {
	const nativeDialogs = installNativeDialogGuard( page );
	const runtimeErrors = installRuntimeErrorGuard( page );
	const deleteRequests = [];
	page.on( 'request', ( request ) => {
		if ( isShieldActionRequest( request, 'ip_rule_delete' ) ) {
			deleteRequests.push( request.url() );
		}
	} );

	await fixtureApi.withIpRulesTableFixture( async ( fixture ) => {
		await openShieldRoute( page, {
			nav: 'ips',
			nav_sub: 'rules',
		} );
		await blankPluginAdminDialogStrings( page );

		const deleteAction = page.locator(
			`#ShieldTable-IpRules td.ip_linked button.ip_delete[data-rid="${fixture.rule_id}"]`
		).first();
		await expect( deleteAction ).toBeVisible( { timeout: 20_000 } );
		await expect( deleteAction ).toBeEnabled();

		await deleteAction.focus();
		await page.keyboard.press( 'Enter' );
		const dialog = await expectVisibleAccessibleDialog( page );

		await dialog.locator( '.shield-accessible-dialog__cancel' ).click();
		await expectDialogClosed( page );
		await expect( deleteAction ).toBeFocused();

		expect( deleteRequests ).toEqual( [] );
		expect( nativeDialogs ).toEqual( [] );
		expect( runtimeErrors ).toEqual( [] );
	} );
} );

test( 'wp admin confirm remains accessible when wpadmin localized dialog strings are empty', async ( { page } ) => {
	const nativeDialogs = installNativeDialogGuard( page );
	const runtimeErrors = installRuntimeErrorGuard( page );
	await page.goto( '/wp-admin/index.php', { waitUntil: 'load' } );
	await expect.poll( () => page.evaluate( () => Boolean( window.shieldServices?.dialog && window.shield_vars_wpadmin ) ) )
	.toBe( true );
	await blankWpAdminDialogStrings( page );

	await page.evaluate( () => {
		const launcher = document.createElement( 'button' );
		launcher.id = 'shi-282-wpadmin-dialog-launcher';
		launcher.type = 'button';
		launcher.setAttribute( 'aria-label', 'SHI-282 launcher' );
		document.body.appendChild( launcher );
		launcher.focus();
		window.__shi282WpAdminConfirmResult = 'pending';
		window.shieldServices.dialog().confirm( {
			message: 'SHI-282 prompt',
			launcher,
		} ).then( ( result ) => {
			window.__shi282WpAdminConfirmResult = result;
		} );
	} );

	const dialog = await expectVisibleAccessibleDialog( page );
	await dialog.locator( '.shield-accessible-dialog__cancel' ).click();
	await expectDialogClosed( page );
	await expect( page.locator( '#shi-282-wpadmin-dialog-launcher' ) ).toBeFocused();
	await expect.poll( () => page.evaluate( () => window.__shi282WpAdminConfirmResult ) ).toBe( false );

	expect( nativeDialogs ).toEqual( [] );
	expect( runtimeErrors ).toEqual( [] );
} );
