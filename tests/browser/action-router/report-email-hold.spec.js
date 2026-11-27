const { openShieldRoute, test, expect } = require( './support/shield-test' );
const { ActionsQueuePage } = require( './support/actions-queue-page' );

/**
 * While Shield holds its first scan alerts for a report email, choosing one leads Review next in the Actions Queue.
 * The item can't be ignored, and its link opens the setup guide on the report email step, which can't be skipped
 * either. Either save on that step ends the hold, and the item goes.
 */

const review = ( page ) => page.locator( '[data-actions-queue-section-key="review"]' );
const reportEmailLink = ( page ) => review( page ).locator( 'a[href*="setup_step=report_email"]' );
const reportEmailPane = ( page ) => page.locator( '#ShieldSetupGuide [data-setup-guide-pane="report_email"]' );
// The step's second way of saving: the button that belongs to the form holding the "use the site admin email" choice.
const useSiteAdminEmail = ( page ) => reportEmailPane( page ).locator( '[type="submit"][form^="ReportEmailUseAdmin"]' );

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

/**
 * Where reports go has to be chosen: the step offers its two ways of saving, and no way of skipping it.
 */
async function expectReportEmailStepCannotBeSkipped( page ) {
	await openShieldRoute( page, { nav: 'dashboard', nav_sub: 'overview', setup_guide: 'open' } );
	await expect( reportEmailPane( page ) ).toBeVisible();
	await expect( reportEmailPane( page ).locator( '[type="submit"]' ) ).toHaveCount( 2 );
	await expect( reportEmailPane( page ).locator( '[data-setup-guide-skip], .skip-step' ) ).toHaveCount( 0 );
}

async function expectReportEmail( fixtureApi, expected ) {
	await expect.poll(
		async () => ( await fixtureApi.inspectReportEmailHoldFixture() ).state.report_email,
		{ timeout: 20_000 }
	).toBe( expected );
}

test( 'the report email item leads Review next, cannot be ignored, and keeping the admin email ends it', async ( { page, fixtureApi } ) => {
	await fixtureApi.withReportEmailHoldFixture( async ( contract ) => {
		await expectReportEmailStepCannotBeSkipped( page );
		await openReviewSection( page );

		await expect( review( page ).locator( '[data-actions-queue-box-toggle]' ).first() )
		.toHaveAttribute( 'data-actions-queue-box-toggle', /report_email/ );
		await expect( review( page ).locator( '[data-actions-queue-group-key^="report_email"] [data-actions-queue-maintenance-kind="ignore"]' ) )
		.toHaveCount( 0 );

		await reportEmailLink( page ).click();
		await expect( reportEmailPane( page ) ).toBeVisible();
		await useSiteAdminEmail( page ).click();
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
		await guide.locator( '.shield-setup-guide__leave > [data-setup-guide-minimise="1"]' ).click();
		await expect( toggle ).toHaveAttribute( 'aria-expanded', 'false' );

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

test( 'a finished setup guide step can be gone over again from its own link', async ( { page, fixtureApi } ) => {
	await fixtureApi.withReportEmailHoldFixture( async ( contract ) => {
		await openShieldRoute( page, { nav: 'dashboard', nav_sub: 'overview', setup_guide: 'open' } );
		await useSiteAdminEmail( page ).click();
		await expectReportEmail( fixtureApi, contract.site_admin_email );

		// A page that wasn't asked to show the guide draws a finished step without its form.
		await openShieldRoute( page, { nav: 'dashboard', nav_sub: 'overview' } );
		const guide = page.locator( '#ShieldSetupGuide' );
		const emailField = reportEmailPane( page ).locator( '[name="ReportEmail"]' );
		await guide.locator( '[data-setup-guide-toggle="1"]' ).click();
		await guide.locator( '[data-setup-guide-step="report_email"]' ).click();
		await expect( emailField ).toHaveCount( 0 );

		await reportEmailPane( page ).locator( 'a[href*="setup_step=report_email"]' ).click();
		await expect( emailField ).toBeVisible();
	} );
} );

test( 'the licence step has one way to skip it, beside its button', async ( { page, fixtureApi } ) => {
	// The guide has a licence step only on a site without a licence.
	await fixtureApi.withMerlinWelcomeFixture( () => fixtureApi.withReportEmailHoldFixture( async () => {
		await openShieldRoute( page, { nav: 'dashboard', nav_sub: 'overview' } );
		const guide = page.locator( '#ShieldSetupGuide' );
		const licenceStep = guide.locator( '[data-setup-guide-step="license"]' );
		await expect( licenceStep ).not.toHaveClass( /is-done/ );

		await guide.locator( '[data-setup-guide-toggle="1"]' ).click();
		await licenceStep.click();
		// The wizard's own "skip to the next step" wording isn't shown in the guide.
		const pane = guide.locator( '[data-setup-guide-pane="license"]' );
		await expect( pane.locator( '.skip-step' ) ).toBeHidden();
		await pane.locator( '.shield-setup-guide__actions [data-setup-guide-skip]' ).click();
		await expect( licenceStep ).toHaveClass( /is-skipped/ );
		await expect( licenceStep ).not.toHaveClass( /is-done/ );
	} ) );
} );

test( 'a typed report email saved from the queue link ends the hold', async ( { page, fixtureApi } ) => {
	await fixtureApi.withReportEmailHoldFixture( async () => {
		await openReviewSection( page );
		await reportEmailLink( page ).click();
		await expect( reportEmailPane( page ) ).toBeVisible();

		await reportEmailPane( page ).locator( '[data-report-email-input="1"]' ).fill( 'agency@example.test' );
		await reportEmailPane( page ).locator( '[data-report-email-save="1"]' ).click();
		await expectReportEmail( fixtureApi, 'agency@example.test' );

		await openReviewSection( page );
		await expect( reportEmailLink( page ) ).toHaveCount( 0 );
	} );
} );
