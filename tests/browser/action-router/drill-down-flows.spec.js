const { test, expect } = require( '@playwright/test' );
const {
	openShieldRoute,
	withActionsQueueDetailFixture,
} = require( './support/shield-browser' );

test( 'actions queue drills into groups and back out, opening details when available', async ( { page } ) => {
	await withActionsQueueDetailFixture( async () => {
		await openShieldRoute( page, {
			nav: 'scans',
			nav_sub: 'overview',
		} );

		const bucket = page.locator( '[data-actions-landing="1"] [data-drill-target="groups"]:not(:disabled)' )
			.filter( { hasText: /Fix now/i } )
			.first();
		await expect( bucket ).toBeVisible();

		const healthyToggle = page.locator( '[data-actions-landing="1"] [data-healthy-disclosure-toggle="1"]' ).first();
		const healthyBody = page.locator( '[data-actions-landing="1"] [data-healthy-disclosure-body="1"]' ).first();
		if ( await healthyToggle.count() > 0 ) {
			await expect( healthyToggle ).toBeVisible();
			await expect( healthyBody ).not.toHaveClass( /is-open/ );
			await expect( healthyToggle ).toHaveAttribute( 'aria-expanded', 'false' );
			await expect( healthyBody ).toHaveAttribute( 'aria-hidden', 'true' );

			await healthyToggle.click();
			await expect( healthyToggle ).toHaveClass( /is-open/ );
			await expect( healthyBody ).toHaveClass( /is-open/ );
			await expect( healthyToggle ).toHaveAttribute( 'aria-expanded', 'true' );
			await expect( healthyBody ).toHaveAttribute( 'aria-hidden', 'false' );

			await healthyToggle.click();
			await expect( healthyToggle ).not.toHaveClass( /is-open/ );
			await expect( healthyBody ).not.toHaveClass( /is-open/ );
			await expect( healthyToggle ).toHaveAttribute( 'aria-expanded', 'false' );
			await expect( healthyBody ).toHaveAttribute( 'aria-hidden', 'true' );
		}

		await bucket.click();
		await expect( page.locator( '[data-actions-queue-groups="1"]' ) ).toBeVisible();
		await expect( page.locator( '[data-drill-layer="0"]' ) ).toHaveClass( /drill-layer--compact/ );
		const actionTabs = page.locator( '[data-operator-step-tab="1"]' );
		await expect( actionTabs ).toHaveCount( 3 );
		await expect( actionTabs.first() ).toHaveAttribute( 'data-color-key', 'home' );
		await expect( page.locator( '[data-step-tab-drill-index="0"]' ) ).toHaveText( /Actions Queue/i );
		await expect( page.locator( '[data-operator-context-rail="1"] .operator-context-rail__title' ) ).not.toHaveText( '' );

		const group = page.locator( '[data-actions-landing="1"] [data-drill-target="detail"]' ).first();
		await expect( group ).toBeVisible();

		await group.click();
		await expect( page.locator( '[data-actions-queue-detail="1"]' ) ).toBeVisible();
		await expect( page.locator( '[data-drill-layer="1"]' ) ).toHaveClass( /drill-layer--compact/ );

		await page.locator( '[data-step-tab-drill-index="1"]' ).click();
		await expect( page.locator( '[data-actions-queue-groups="1"]' ) ).toBeVisible();
		await expect( page.locator( '[data-drill-layer="2"]' ) ).toHaveClass( /drill-layer--hidden/ );

		await page.locator( '[data-step-tab-drill-index="0"]' ).click();
		await expect( page.locator( '[data-actions-landing="1"] [data-drill-target="groups"]' ).first() ).toBeVisible();
	} );
} );

test( 'configure toggles healthy zones, drills into diagnosis, and drills back out', async ( { page } ) => {
	await openShieldRoute( page, {
		nav: 'zones',
		nav_sub: 'overview',
	} );

	const healthyToggle = page.locator( '[data-healthy-disclosure-toggle="1"]' ).first();
	const healthyBody = page.locator( '[data-healthy-disclosure-body="1"]' ).first();
	const healthyZone = page.locator( '[data-healthy-disclosure-body="1"] [data-drill-target="diagnosis"]' ).first();
	await expect( healthyToggle ).toBeVisible();
	await expect( healthyToggle ).toHaveAttribute( 'aria-expanded', 'false' );
	await expect( healthyBody ).toHaveAttribute( 'aria-hidden', 'true' );
	await healthyToggle.click();
	await expect( healthyToggle ).toHaveAttribute( 'aria-expanded', 'true' );
	await expect( healthyBody ).toHaveAttribute( 'aria-hidden', 'false' );
	await expect( healthyZone ).toBeVisible();

	const zone = page.locator( '[data-configure-landing="1"] [data-drill-target="diagnosis"]' )
		.filter( { hasText: /Security Admin/i } )
		.first();
	await expect( zone ).toBeVisible();

	await zone.click();
	await expect( page.locator( '[data-configure-diagnosis="1"]' ) ).toBeVisible();
	await expect( page.locator( '[data-drill-layer="0"]' ) ).toHaveClass( /drill-layer--compact/ );
	const configureTabs = page.locator( '[data-operator-step-tab="1"]' );
	await expect( configureTabs ).toHaveCount( 3 );
	await expect( configureTabs.first() ).toHaveAttribute( 'data-color-key', 'home' );
	await expect( page.locator( '[data-step-tab-drill-index="0"]' ) ).toHaveText( /Configure/i );
	await expect( page.locator( '[data-operator-context-rail="1"] .operator-context-rail__title' ) ).toHaveText( /Security Admin/i );
	await expect( page.locator( '[data-configure-diagnosis="1"] [data-drill-target="editor"]' ) ).toHaveCount( 0 );
	await expect( page.locator( '[data-configure-diagnosis="1"] .zone-summary-header' ) ).toHaveCount( 0 );
	const expandRow = page.locator( '[data-configure-diagnosis="1"] [data-shield-expand-trigger="1"]' ).first();
	await expect( expandRow.locator( '.shield-detail-row__expand-cta' ) ).toBeVisible();
	await expandRow.click();
	await expect( page.locator( '[data-configure-diagnosis="1"] .shield-detail-expansion.show' ).first() ).toBeVisible();
	await expect( page.locator( '[data-configure-diagnosis="1"] .shield-detail-expansion.show form.options_form_for' ).first() ).toBeVisible();
	const diagnosisHealthyToggle = page.locator( '[data-configure-diagnosis="1"] [data-healthy-disclosure-toggle="1"]' );
	const diagnosisHealthyBody = page.locator( '[data-configure-diagnosis="1"] [data-healthy-disclosure-body="1"]' );
	if ( await diagnosisHealthyToggle.count() > 0 ) {
		await expect( diagnosisHealthyToggle ).toBeVisible();
		await expect( diagnosisHealthyBody ).not.toHaveClass( /is-open/ );
		await expect( diagnosisHealthyToggle ).toHaveAttribute( 'aria-expanded', 'false' );
		await expect( diagnosisHealthyBody ).toHaveAttribute( 'aria-hidden', 'true' );
		const diagnosisHealthyRows = diagnosisHealthyBody.locator( '.shield-detail-row' );
		expect( await diagnosisHealthyRows.count() ).toBeGreaterThan( 0 );

		await diagnosisHealthyToggle.click();
		await expect( diagnosisHealthyToggle ).toHaveClass( /is-open/ );
		await expect( diagnosisHealthyBody ).toHaveClass( /is-open/ );
		await expect( diagnosisHealthyToggle ).toHaveAttribute( 'aria-expanded', 'true' );
		await expect( diagnosisHealthyBody ).toHaveAttribute( 'aria-hidden', 'false' );
		await expect( diagnosisHealthyRows.first() ).toBeVisible();

		await diagnosisHealthyToggle.click();
		await expect( diagnosisHealthyToggle ).not.toHaveClass( /is-open/ );
		await expect( diagnosisHealthyBody ).not.toHaveClass( /is-open/ );
		await expect( diagnosisHealthyToggle ).toHaveAttribute( 'aria-expanded', 'false' );
		await expect( diagnosisHealthyBody ).toHaveAttribute( 'aria-hidden', 'true' );
	}

	await page.locator( '[data-step-tab-drill-index="0"]' ).click();
	await expect( page.locator( '[data-configure-landing="1"] [data-drill-target="diagnosis"]' ).first() ).toBeVisible();
} );
