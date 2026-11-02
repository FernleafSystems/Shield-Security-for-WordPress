const { openShieldRoute, test, expect } = require( './support/shield-test' );

/**
 * Ignoring maintenance items from the Actions Queue landing refreshes the queue in place: the section that
 * was open stays open, and a group of like items can be ignored all at once.
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
		expect( navigations ).toEqual( [] );
	} );
} );

test( 'a group of like items can be ignored all at once', async ( { page, fixtureApi } ) => {
	await fixtureApi.withActionsQueueFixture( 'direct_table', async () => {
		await openReviewSection( page );
		const ignoreAll = section( page, 'review' ).locator( '[data-actions-queue-maintenance-ignore-all="1"]' ).first();
		test.skip( await ignoreAll.count() === 0, 'This site has no group of like maintenance items.' );

		const group = ignoreAll.locator( 'xpath=ancestor::*[@data-actions-queue-group-key][1]' );
		const groupKey = await group.getAttribute( 'data-actions-queue-group-key' );
		await ignoreAll.click();
		await expect( page.locator( `[data-actions-queue-section-key="review"] [data-actions-queue-group-key="${groupKey}"]` ) )
			.toHaveCount( 0, { timeout: 30_000 } );
	} );
} );
