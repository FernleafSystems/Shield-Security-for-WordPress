const { openShieldRoute, test, expect } = require( './support/shield-test' );

/**
 * Ignoring maintenance items from the Actions Queue landing refreshes the queue in place: the section that
 * was open stays open, and a group of like items can be ignored all at once, in one request. A group whose
 * items are all ignored stays in its lane, faded, with its items behind "N ignored" so they can be restored.
 */

const section = ( page, key ) => page.locator( `[data-actions-queue-section-key="${key}"]` );

async function openReviewSection( page ) {
	await openShieldRoute( page, { nav: 'scans', nav_sub: 'overview' } );
	const sectionsView = page.locator( '[data-actions-queue-view="sections"]' );
	if ( await sectionsView.getAttribute( 'aria-pressed' ) !== 'true' ) {
		await sectionsView.click();
	}
	const head = section( page, 'review' ).locator( '[data-actions-queue-section-toggle="1"]' );
	if ( await head.getAttribute( 'aria-expanded' ) !== 'true' ) {
		await head.click();
	}
	await expect( head ).toHaveAttribute( 'aria-expanded', 'true' );
	// Review next's boxes start folded; open each to reach its items.
	const toggles = section( page, 'review' ).locator( '[data-actions-queue-box-toggle][aria-expanded="false"]' );
	while ( await toggles.count() > 0 ) {
		await toggles.first().click();
	}
	return head;
}

test( 'ignoring an item refreshes the queue in place and keeps Review next open', async ( { page, fixtureApi } ) => {
	await fixtureApi.withActionsQueueFixture( 'direct_table', async () => {
		const head = await openReviewSection( page );
		const ignores = section( page, 'review' ).locator( '[data-actions-queue-maintenance-kind="ignore"]' );
		const before = await ignores.count();
		test.skip( before === 0, 'This site has no maintenance items to ignore.' );

		const navigations = [];
		page.on( 'framenavigated', ( frame ) => frame === page.mainFrame() && navigations.push( frame.url() ) );

		const ignore = ignores.first();
		await expect( ignore ).toHaveAccessibleName( /\S/ );
		await ignore.click();
		await expect.poll( async () => ignores.count(), { timeout: 20_000 } ).toBe( before - 1 );
		await expect( head ).toHaveAttribute( 'aria-expanded', 'true' );
		// The boxes that were open stay open after the queue refreshes; ignored items stay behind their own button.
		await expect( section( page, 'review' ).locator(
			'[data-actions-queue-box-toggle]:not([data-actions-queue-box-toggle^="ignored:"])[aria-expanded="false"]'
		) ).toHaveCount( 0 );
		expect( navigations ).toEqual( [] );
	} );
} );

test( 'a group of like items can be ignored all at once', async ( { page, fixtureApi } ) => {
	await fixtureApi.withActionsQueueFixture( 'direct_table', async () => {
		await openReviewSection( page );
		const ignoreAll = section( page, 'review' ).locator( '[data-actions-queue-maintenance-all="ignore"]' ).first();
		test.skip( await ignoreAll.count() === 0, 'This site has no group of like maintenance items.' );

		const group = ignoreAll.locator( 'xpath=ancestor::*[@data-actions-queue-group-key][1]' );
		const groupKey = await group.getAttribute( 'data-actions-queue-group-key' );
		const itemCount = await group.locator( '[data-actions-queue-maintenance-kind="ignore"]' ).count();
		const ignoreRequests = [];
		page.on( 'request', ( request ) => {
			if ( request.method() === 'POST' && request.url().includes( '/admin-ajax.php' )
				&& new URLSearchParams( request.postData() || '' ).get( 'ex' ) === 'maintenance_item_ignore' ) {
				ignoreRequests.push( request );
			}
		} );

		await ignoreAll.click();
		const ignoredGroup = page.locator( `[data-actions-queue-section-key="review"] .is-all-ignored[data-actions-queue-group-key="${groupKey}"]` );
		await expect( ignoredGroup ).toHaveCount( 1, { timeout: 30_000 } );
		expect( ignoreRequests ).toHaveLength( 1 );

		// Its items wait behind "N ignored", each with its way back.
		const showIgnored = ignoredGroup.locator( '[data-actions-queue-box-toggle^="ignored:"]' );
		await expect( showIgnored ).toContainText( String( itemCount ) );
		// The whole heading opens them, even clicked well away from the count.
		await ignoredGroup.locator( '.actions-queue-row-group__label' ).click();
		await expect( showIgnored ).toHaveAttribute( 'aria-expanded', 'true' );
		await expect( ignoredGroup.locator( '[data-actions-queue-maintenance-kind="unignore"]' ) ).toHaveCount( itemCount );

		// With them all ignored, they can all be brought back together, in one request.
		const unignoreRequests = [];
		page.on( 'request', ( request ) => {
			if ( request.method() === 'POST' && request.url().includes( '/admin-ajax.php' )
				&& new URLSearchParams( request.postData() || '' ).get( 'ex' ) === 'maintenance_item_unignore' ) {
				unignoreRequests.push( request );
			}
		} );
		await ignoredGroup.locator( '[data-actions-queue-maintenance-all="unignore"]' ).click();
		const restoredGroup = page.locator( `[data-actions-queue-section-key="review"] [data-actions-queue-group-key="${groupKey}"]:not(.is-all-ignored)` );
		await expect( restoredGroup ).toHaveCount( 1, { timeout: 30_000 } );
		expect( unignoreRequests ).toHaveLength( 1 );
		await expect( restoredGroup.locator( '[data-actions-queue-maintenance-kind="ignore"]' ) ).toHaveCount( itemCount );
	} );
} );
