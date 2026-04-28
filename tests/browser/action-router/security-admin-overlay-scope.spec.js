const { test, expect } = require( './support/shield-test' );
const { fetchShieldRenderedHtml, openShieldRoute } = require( './support/shield-browser' );

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
