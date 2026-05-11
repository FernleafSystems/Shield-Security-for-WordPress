const { AxeBuilder, test, expect } = require( './support/shield-test' );
const { fetchShieldRenderedHtml, openShieldRoute } = require( './support/shield-browser' );
const {
	expectFocusWithin,
	expectNamedDialog,
	expectOptionalDescription,
} = require( './support/modal-accessibility' );

const ACCESSIBLE_DIALOG_ACTIVE_SELECTOR = '[data-shield-accessible-dialog="1"][aria-modal="true"]:not([aria-hidden="true"])';
const ACCESSIBLE_DIALOG_TITLE_SELECTOR = '.shield-accessible-dialog__title';
const ACCESSIBLE_DIALOG_MESSAGE_SELECTOR = '.shield-accessible-dialog__message';
const ACCESSIBLE_DIALOG_CONFIRM_SELECTOR = '.shield-accessible-dialog__confirm';

test( 'Security Admin overlay scope preserves WordPress submenu navigation', async ( { page } ) => {
	await openShieldRoute( page, {
		nav: 'dashboard',
		nav_sub: 'overview',
	} );

	const restrictedHtml = await fetchShieldRenderedHtml( page, 'admin_plugin_page_security_admin_restricted' );

	expect( restrictedHtml ).toContain( 'SecurityAdminOverlay' );

	await page.evaluate( ( html ) => {
		const body = document.getElementById( 'PageMainBody_Inner-Apto' );
		if ( body ) {
			body.innerHTML = html;
		}
	}, restrictedHtml );

	await expect( page.locator( '#SecurityAdminOverlay' ) ).toBeVisible();

	const usersMenu = page.locator( '#menu-users' );
	await usersMenu.hover();

	const usersLink = page.locator( '#menu-users .wp-submenu a[href$="users.php"]' ).first();
	await expect( usersLink ).toBeVisible();

	await Promise.all( [
		page.waitForURL( /\/wp-admin\/users\.php(?:\?|$)/, { timeout: 20_000 } ),
		usersLink.click(),
	] );

	await expect( page.locator( 'body.users-php' ) ).toBeVisible();
} );

test( 'Security Admin timeout displays the planned reload dialog', async ( { page, fixtureApi } ) => {
	const nativeDialogs = [];
	page.on( 'dialog', async ( dialog ) => {
		nativeDialogs.push( dialog.type() );
		await dialog.dismiss().catch( () => null );
	} );

	await fixtureApi.withSecurityAdminFixture( async () => {
		await openShieldRoute( page, {
			nav: 'dashboard',
			nav_sub: 'overview',
		} );

		expect( await page.evaluate( () => Boolean( window.shieldAppMain?.components?.sec_admin ) ) ).toBe( true );
		await page.evaluate( () => {
			window.shieldAppMain.components.sec_admin.handleSecAdminCheck( {
				success: false,
				data: {
					success: false,
				},
			} );
		} );

		const dialog = page.locator( ACCESSIBLE_DIALOG_ACTIVE_SELECTOR );
		await expect( dialog ).toBeVisible( { timeout: 5_000 } );
		await expectNamedDialog( page, dialog );
		await expectOptionalDescription( page, dialog );
		await expectFocusWithin( dialog );

		const title = dialog.locator( ACCESSIBLE_DIALOG_TITLE_SELECTOR );
		await expect( title ).toBeVisible();
		await expect( title ).not.toHaveClass( /__title--hidden/ );
		await expect( title ).toHaveText( 'Session Expired' );
		await expect( dialog.locator( ACCESSIBLE_DIALOG_MESSAGE_SELECTOR ) ).toHaveText(
			'Your Security Admin session has timed out. Reload to re-authenticate.'
		);
		await expect( dialog.locator( ACCESSIBLE_DIALOG_CONFIRM_SELECTOR ) ).toHaveText( 'Reload' );

		const results = await new AxeBuilder( { page } )
			.include( ACCESSIBLE_DIALOG_ACTIVE_SELECTOR )
			.analyze();
		expect( results.violations, JSON.stringify( results.violations, null, 2 ) ).toEqual( [] );
		expect( nativeDialogs ).toEqual( [] );
	} );
} );
