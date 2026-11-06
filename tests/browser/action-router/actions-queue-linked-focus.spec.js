const { openShieldRoute, test, expect } = require( './support/shield-test' );

/**
 * The admin bar's scan alerts link to their kind of problem in the Actions Queue (aq_focus), which opens it,
 * brings it into view and highlights it.
 */
test( 'a link to a kind of problem opens it in the queue and highlights it', async ( { page, fixtureApi } ) => {
	await fixtureApi.withActionsQueueFixture( 'direct_table', async () => {
		await openShieldRoute( page, { nav: 'scans', nav_sub: 'overview', aq_focus: 'plugins' } );

		const box = page.locator( '[data-actions-queue-sections="1"] [data-actions-queue-kind="plugins"]' ).first();
		await expect( box ).toHaveClass( /is-linked/ );
		await expect( box ).toBeInViewport();
		await expect( box ).toBeVisible();
	} );
} );
