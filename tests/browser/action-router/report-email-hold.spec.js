const { openShieldRoute, test, expect } = require( './support/shield-test' );
const { ActionsQueuePage } = require( './support/actions-queue-page' );

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
	await new ActionsQueuePage( page ).showSectionsView();
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

test( 'a report email saved in Configure completes the setup guide step on the same page', async ( { page, fixtureApi } ) => {
	await fixtureApi.withReportEmailHoldFixture( async () => {
		await openShieldRoute( page, { nav: 'zones', nav_sub: 'overview', zone: 'alerts' } );
		const guide = page.locator( '#ShieldSetupGuide' );
		const step = guide.locator( '[data-setup-guide-step="report_email"]' );
		await expect( step ).not.toHaveClass( /is-done/ );

		// A protection level picked in the guide, and not yet applied, is a choice still being made.
		const toggle = guide.locator( '[data-setup-guide-toggle="1"]' );
		await toggle.click();
		await guide.locator( '[data-setup-guide-step="apply_security_profile"]' ).click();
		const level = guide.locator( '[data-protection-level][aria-pressed="false"]' ).first();
		const levelKey = await level.getAttribute( 'data-protection-level' );
		await level.click();
		await expect( guide.locator( `[data-protection-level="${levelKey}"]` ) ).toHaveAttribute( 'aria-pressed', 'true' );
		await toggle.click();

		const navigations = [];
		page.on( 'framenavigated', ( frame ) => frame === page.mainFrame() && navigations.push( frame.url() ) );

		const diagnosis = page.locator( '[data-configure-diagnosis="1"]' );
		const trigger = diagnosis.locator( '[data-configure-row-key="reports"] [data-shield-expand-trigger="1"]' );
		if ( await trigger.getAttribute( 'aria-expanded' ) !== 'true' ) {
			await trigger.click();
		}
		await diagnosis.locator( '[data-configure-row-key="reports"] [name="block_send_email_address"]' ).fill( 'agency@example.test' );
		await diagnosis.locator( '[data-configure-savebar-save="1"]' ).click();
		await expectReportEmail( fixtureApi, 'agency@example.test' );

		// The guide is the same element, brought up to date, rather than a page reload. The step the save didn't
		// touch is left as it was, level picked and all.
		await expect( step ).toHaveClass( /is-done/, { timeout: 20_000 } );
		expect( navigations ).toEqual( [] );
		await expect( guide.locator( `[data-protection-level="${levelKey}"]` ) ).toHaveAttribute( 'aria-pressed', 'true' );
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
