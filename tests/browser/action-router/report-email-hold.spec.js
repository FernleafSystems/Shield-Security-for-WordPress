const { openShieldRoute, test, expect } = require( './support/shield-test' );

/**
 * While Shield holds its first scan alerts for a report email, choosing one leads Review next in the Actions Queue.
 * The item can't be ignored, and its link opens the setup guide on the report email step, even when that step was
 * skipped before. Either save on that step ends the hold, and the item goes.
 */

const review = ( page ) => page.locator( '[data-actions-queue-section-key="review"]' );
const reportEmailLink = ( page ) => review( page ).locator( 'a[href*="setup_step=report_email"]' );
const reportEmailPane = ( page ) => page.locator( '#ShieldSetupGuide [data-setup-guide-pane="report_email"]' );

async function openReviewSection( page ) {
	await openShieldRoute( page, { nav: 'scans', nav_sub: 'overview' } );
	const sectionsView = page.locator( '[data-actions-queue-view="sections"]' );
	if ( await sectionsView.getAttribute( 'aria-pressed' ) !== 'true' ) {
		await sectionsView.click();
	}
	const head = review( page ).locator( '[data-actions-queue-section-toggle="1"]' );
	if ( await head.getAttribute( 'aria-expanded' ) !== 'true' ) {
		await head.click();
	}
	await expect( head ).toHaveAttribute( 'aria-expanded', 'true' );
	// Review next's boxes start folded; open each to reach its items.
	const toggles = review( page ).locator( '[data-actions-queue-box-toggle][aria-expanded="false"]' );
	while ( await toggles.count() > 0 ) {
		await toggles.first().click();
	}
}

async function skipReportEmailStep( page ) {
	await openShieldRoute( page, { nav: 'dashboard', nav_sub: 'overview', setup_guide: 'open' } );
	await expect( reportEmailPane( page ) ).toBeVisible();
	await reportEmailPane( page ).locator( '[data-setup-guide-skip="report_email"]' ).click();
	await expect( reportEmailPane( page ) ).toBeHidden();
}

async function expectReportEmail( fixtureApi, expected ) {
	await expect.poll(
		async () => ( await fixtureApi.inspectReportEmailHoldFixture() ).state.report_email,
		{ timeout: 20_000 }
	).toBe( expected );
}

test( 'the report email item leads Review next, cannot be ignored, and keeping the admin email ends it', async ( { page, fixtureApi } ) => {
	await fixtureApi.withReportEmailHoldFixture( async ( contract ) => {
		await skipReportEmailStep( page );
		await openReviewSection( page );

		await expect( review( page ).locator( '[data-actions-queue-box-toggle]' ).first() )
		.toHaveAttribute( 'data-actions-queue-box-toggle', /report_email/ );
		await expect( review( page ).locator( '[data-actions-queue-group-key^="report_email"] [data-actions-queue-maintenance-kind="ignore"]' ) )
		.toHaveCount( 0 );

		await reportEmailLink( page ).click();
		await expect( reportEmailPane( page ) ).toBeVisible();
		await reportEmailPane( page ).getByRole( 'button', { name: /Use the site admin email/ } ).click();
		await expectReportEmail( fixtureApi, contract.site_admin_email );

		await openReviewSection( page );
		await expect( reportEmailLink( page ) ).toHaveCount( 0 );
	} );
} );

test( 'a typed report email saved from the queue link ends the hold', async ( { page, fixtureApi } ) => {
	await fixtureApi.withReportEmailHoldFixture( async () => {
		await openReviewSection( page );
		await reportEmailLink( page ).click();
		await expect( reportEmailPane( page ) ).toBeVisible();

		await reportEmailPane( page ).getByLabel( 'Report email' ).fill( 'agency@example.test' );
		await reportEmailPane( page ).getByRole( 'button', { name: 'Save Report Email' } ).click();
		await expectReportEmail( fixtureApi, 'agency@example.test' );

		await openReviewSection( page );
		await expect( reportEmailLink( page ) ).toHaveCount( 0 );
	} );
} );
