const { test, expect } = require( '@playwright/test' );
const { openShieldRoute } = require( './support/shield-browser' );

test( 'preloaded IP analysis offcanvas opens and switches inline tabs', async ( { page } ) => {
	await openShieldRoute( page, {
		nav: 'ips',
		nav_sub: 'rules',
		analyse_ip: '198.51.100.20',
	} );

	const offcanvas = page.locator( '#AptoOffcanvas.show' );
	await expect( offcanvas ).toBeVisible();
	await expect( offcanvas.locator( '#AptoOffcanvasLabel' ) ).toBeVisible();

	const inlineTabs = offcanvas.locator( '[data-investigate-panel-tabs="1"] [data-investigate-panel-tab="1"]' );
	await expect( inlineTabs.first() ).toBeVisible();
	await expect( inlineTabs ).toHaveCount( 5 );

	const targetTab = inlineTabs.nth( 2 );
	const targetLabel = await targetTab.textContent();
	await targetTab.click();

	await expect( targetTab ).toHaveClass( /is-active/ );
	await expect(
		offcanvas.locator( '.shield-options-rail-nav .nav-link.active' )
	).toContainText( targetLabel ? targetLabel.trim() : '' );
} );
