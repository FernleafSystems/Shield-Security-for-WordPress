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

test( 'configure drills into diagnosis and editor, then drills back out', async ( { page } ) => {
	await openShieldRoute( page, {
		nav: 'zones',
		nav_sub: 'overview',
	} );

	const zone = page.locator( '[data-configure-landing="1"] [data-drill-target="diagnosis"]' )
		.filter( { hasText: /Security Admin/i } )
		.first();
	await expect( zone ).toBeVisible();

	await zone.click();
	await expect( page.locator( '[data-configure-diagnosis="1"]' ) ).toBeVisible();
	await expect( page.locator( '[data-drill-layer="0"]' ) ).toHaveClass( /drill-layer--compact/ );

	const cta = page.locator( '[data-configure-diagnosis="1"] [data-drill-target="editor"]' );
	await expect( cta ).toBeVisible();

	await cta.click();
	await expect( page.locator( '[data-configure-editor="1"]' ) ).toBeVisible();
	await expect( page.locator( '[data-drill-layer="1"]' ) ).toHaveClass( /drill-layer--compact/ );

	await page.locator( '[data-drill-layer="1"] [data-drill-strip="1"]' ).click();
	await expect( page.locator( '[data-configure-diagnosis="1"]' ) ).toBeVisible();

	await page.locator( '[data-drill-layer="0"] [data-drill-strip="1"]' ).click();
	await expect( page.locator( '[data-configure-landing="1"] [data-drill-target="diagnosis"]' ).first() ).toBeVisible();
});
