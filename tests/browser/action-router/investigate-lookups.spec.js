const { test, expect } = require( '@playwright/test' );
const { openShieldRoute, selectSelect2Option } = require( './support/shield-browser' );

test( 'investigate landing drills into a subject, supports lookup, and drills back out', async ( { page } ) => {
	await openShieldRoute( page, {
		nav: 'activity',
		nav_sub: 'overview',
	} );

	const userTile = page.locator( '[data-drill-target="panel"][data-investigate-subject="user"]' );
	await expect( userTile ).toBeVisible();

	await Promise.all( [
		page.waitForURL(
			( url ) => url.searchParams.get( 'nav' ) === 'activity'
				&& url.searchParams.get( 'nav_sub' ) === 'overview'
				&& url.searchParams.get( 'subject' ) === 'user',
			{ timeout: 20_000 }
		),
		userTile.click(),
	] );

	const panel = page.locator( '[data-investigate-panel="1"]' );
	await expect( panel ).toHaveAttribute( 'data-investigate-panel-subject', 'user' );
	await expect( panel ).toHaveAttribute( 'data-investigate-panel-loaded', '1' );
	await expect( page.locator( '[data-investigate-subject-header="1"]' ) ).toBeVisible();

	await selectSelect2Option(
		page,
		'user_lookup',
		'admin',
		/admin/i,
		( url ) => url.searchParams.get( 'nav' ) === 'activity'
			&& url.searchParams.get( 'nav_sub' ) === 'overview'
			&& url.searchParams.get( 'subject' ) === 'user'
			&& !!url.searchParams.get( 'user_lookup' )
	);

	await expect( panel ).toHaveAttribute( 'data-investigate-panel-subject', 'user' );
	await expect( panel ).toHaveAttribute( 'data-investigate-panel-loaded', '1' );
	await expect( page.locator( '#tab-navlink-user-overview.active' ) ).toBeVisible();
	await expect( page.locator( '#tabInvestigateUserOverview.active.show' ) ).toBeVisible();

	await Promise.all( [
		page.waitForURL(
			( url ) => url.searchParams.get( 'nav' ) === 'activity'
				&& url.searchParams.get( 'nav_sub' ) === 'overview'
				&& !url.searchParams.get( 'subject' )
				&& !url.searchParams.get( 'user_lookup' ),
			{ timeout: 20_000 }
		),
		page.locator( '[data-drill-layer="0"] [data-drill-strip="1"]' ).click(),
	] );

	await expect( page.locator( '[data-drill-target="panel"][data-investigate-subject="user"]' ) ).toBeVisible();
	await expect( panel ).toHaveAttribute( 'data-investigate-panel-subject', '' );
	await expect( panel ).toHaveAttribute( 'data-investigate-panel-loaded', '0' );
} );

test( 'investigate landing deep link opens the IP panel immediately', async ( { page } ) => {
	await openShieldRoute( page, {
		nav: 'activity',
		nav_sub: 'overview',
		subject: 'ip',
		analyse_ip: '203.0.113.88',
	} );

	const panel = page.locator( '[data-investigate-panel="1"]' );
	await expect( panel ).toHaveAttribute( 'data-investigate-panel-subject', 'ip' );
	await expect( panel ).toHaveAttribute( 'data-investigate-panel-loaded', '1' );
	await expect( page.locator( '[data-investigate-subject-header="1"]' ) ).toBeVisible();
	await expect( page.locator( '[data-drill-layer="0"]' ) ).toHaveClass( /drill-layer--compact/ );
} );
