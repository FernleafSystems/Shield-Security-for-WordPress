const { test, expect } = require( '@playwright/test' );
const { openShieldRoute, selectSelect2Option } = require( './support/shield-browser' );

test( 'user investigate route loads subject context through the Select2 lookup flow', async ( { page } ) => {
	await openShieldRoute( page, {
		nav: 'activity',
		nav_sub: 'by_user',
	} );

	await selectSelect2Option(
		page,
		'user_lookup',
		'admin',
		/admin/i,
		( url ) => url.searchParams.get( 'nav' ) === 'activity'
			&& url.searchParams.get( 'nav_sub' ) === 'by_user'
			&& !!url.searchParams.get( 'user_lookup' )
	);

	await expect( page.locator( '[data-investigate-subject-header="1"]' ) ).toBeVisible();
	await expect( page.locator( '#tab-navlink-user-overview.active' ) ).toBeVisible();
	await expect( page.locator( '#tabInvestigateUserOverview.active.show' ) ).toBeVisible();
});

test( 'plugin investigate route loads the active Shield plugin through the Select2 lookup flow', async ( { page } ) => {
	await openShieldRoute( page, {
		nav: 'activity',
		nav_sub: 'by_plugin',
	} );

	await selectSelect2Option(
		page,
		'plugin_slug',
		'shield',
		/shield/i,
		( url ) => url.searchParams.get( 'nav' ) === 'activity'
			&& url.searchParams.get( 'nav_sub' ) === 'by_plugin'
			&& url.searchParams.get( 'plugin_slug' ) === 'wp-simple-firewall/icwp-wpsf.php'
	);

	await expect( page.locator( '[data-investigate-subject-header="1"]' ) ).toBeVisible();
	await expect( page.locator( '#tab-navlink-plugin-overview.active' ) ).toBeVisible();
	await expect( page.locator( '#tabInvestigatePluginOverview.active.show' ) ).toBeVisible();
} );
