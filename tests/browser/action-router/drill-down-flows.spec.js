const { test, expect } = require( '@playwright/test' );
const { openShieldRoute } = require( './support/shield-browser' );

test( 'actions queue drills into groups and details, then drills back out', async ( { page } ) => {
	await openShieldRoute( page, {
		nav: 'scans',
		nav_sub: 'overview',
	} );

	const bucket = page.locator( '[data-actions-landing="1"] [data-drill-target="groups"]:not(:disabled)' ).first();
	await expect( bucket ).toBeVisible();

	await bucket.click();
	await expect( page.locator( '[data-actions-queue-groups="1"]' ) ).toBeVisible();
	await expect( page.locator( '[data-drill-layer="0"]' ) ).toHaveClass( /drill-layer--compact/ );

	const group = page.locator( '[data-actions-landing="1"] [data-drill-target="detail"]' ).first();
	await expect( group ).toBeVisible();

	await group.click();
	await expect( page.locator( '[data-actions-queue-detail="1"]' ) ).toBeVisible();
	await expect( page.locator( '[data-drill-layer="1"]' ) ).toHaveClass( /drill-layer--compact/ );

	await page.locator( '[data-drill-layer="1"] [data-drill-strip="1"]' ).click();
	await expect( page.locator( '[data-actions-queue-groups="1"]' ) ).toBeVisible();
	await expect( page.locator( '[data-drill-layer="2"]' ) ).toHaveClass( /drill-layer--hidden/ );

	await page.locator( '[data-drill-layer="0"] [data-drill-strip="1"]' ).click();
	await expect( page.locator( '[data-actions-landing="1"] [data-drill-target="groups"]' ).first() ).toBeVisible();
});

test( 'configure toggles healthy zones, drills into diagnosis, and drills back out', async ( { page } ) => {
	await openShieldRoute( page, {
		nav: 'zones',
		nav_sub: 'overview',
	} );

	const healthyToggle = page.locator( '[data-configure-healthy-toggle="1"]' );
	const healthyZone = page.locator( '[data-configure-healthy-body="1"] [data-drill-target="diagnosis"]' ).first();
	await expect( healthyToggle ).toBeVisible();
	await expect( healthyZone ).toBeHidden();

	await healthyToggle.click();
	await expect( healthyToggle ).toHaveClass( /is-open/ );
	await expect( healthyZone ).toBeVisible();

	const zone = page.locator( '[data-configure-landing="1"] [data-drill-target="diagnosis"]' )
		.filter( { hasText: /Security Admin/i } )
		.first();
	await expect( zone ).toBeVisible();

	await zone.click();
	await expect( page.locator( '[data-configure-diagnosis="1"]' ) ).toBeVisible();
	await expect( page.locator( '[data-drill-layer="0"]' ) ).toHaveClass( /drill-layer--compact/ );
	await expect( page.locator( '[data-configure-diagnosis="1"] [data-drill-target="editor"]' ) ).toHaveCount( 0 );
	await expect( page.locator( '[data-configure-diagnosis="1"] .zone-summary-header' ).first() ).toBeAttached();
	const expandButton = page.locator( '[data-configure-diagnosis="1"] .setting-card__expand-btn' ).first();
	await expect( expandButton ).toBeVisible();
	await expandButton.click();
	await expect( page.locator( '[data-configure-diagnosis="1"] .shield-detail-expansion.show' ).first() ).toBeVisible();
	await expect( page.locator( '[data-configure-diagnosis="1"] .shield-detail-expansion.show form.options_form_for' ).first() ).toBeVisible();
	const diagnosisHealthyToggle = page.locator( '[data-configure-diagnosis="1"] [data-configure-healthy-settings-toggle="1"]' );
	const diagnosisHealthyBody = page.locator( '[data-configure-diagnosis="1"] [data-configure-healthy-settings-body="1"]' );
	if ( await diagnosisHealthyToggle.count() > 0 ) {
		await expect( diagnosisHealthyToggle ).toBeVisible();
		await expect( diagnosisHealthyBody ).not.toHaveClass( /is-open/ );
		const diagnosisHealthyCards = diagnosisHealthyBody.locator( '.setting-card' );
		expect( await diagnosisHealthyCards.count() ).toBeGreaterThan( 0 );

		await diagnosisHealthyToggle.click();
		await expect( diagnosisHealthyToggle ).toHaveClass( /is-open/ );
		await expect( diagnosisHealthyBody ).toHaveClass( /is-open/ );
		await expect( diagnosisHealthyCards.first() ).toBeVisible();

		await diagnosisHealthyToggle.click();
		await expect( diagnosisHealthyToggle ).not.toHaveClass( /is-open/ );
		await expect( diagnosisHealthyBody ).not.toHaveClass( /is-open/ );
	}

	await page.locator( '[data-drill-layer="1"] [data-drill-strip="1"]' ).click();
	await expect( page.locator( '[data-configure-diagnosis="1"]' ) ).toBeVisible();

	await page.locator( '[data-drill-layer="0"] [data-drill-strip="1"]' ).click();
	await expect( page.locator( '[data-configure-landing="1"] [data-drill-target="diagnosis"]' ).first() ).toBeVisible();
});
