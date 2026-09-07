const { openShieldRoute, test, expect } = require( './support/shield-test' );
const {
	expectFocusWithin,
	expectModalHiddenWithoutAriaModal,
	expectNamedDialog,
} = require( './support/modal-accessibility' );

async function expectUpgradeModal( page, launcher ) {
	const originalUrl = page.url();
	await launcher.focus();
	await page.keyboard.press( 'Enter' );
	const modal = page.locator( '#ShieldModalContainer' );
	await expect( modal ).toBeVisible();
	await expectNamedDialog( page, modal, 'shield-pro-upsell-title' );
	await expectFocusWithin( modal );
	await expect( modal.locator( 'a[href="https://clk.shldscrty.com/shieldgoprofeature"]' ) ).toBeVisible();
	expect( page.url() ).toBe( originalUrl );
	await page.keyboard.press( 'Escape' );
	await expectModalHiddenWithoutAriaModal( page, '#ShieldModalContainer' );
	await expect( launcher ).toBeFocused();
}

for ( const route of [
	{ nav: 'tools', nav_sub: 'importexport' },
	{ nav: 'rules', nav_sub: 'manage' },
	{ nav: 'rules', nav_sub: 'build' },
] ) {
	test( `free upgrade gate on ${route.nav}/${route.nav_sub} opens the shared modal`, async ( { page, fixtureApi } ) => {
		await fixtureApi.withActionsQueueFixture( 'pro_upsell', async () => {
			await openShieldRoute( page, route );
			const launcher = page.locator( '[data-pro-upsell="1"]' );
			await expect( launcher ).toHaveCount( 1 );
			if ( route.nav === 'rules' ) {
				const video = page.locator( '[data-vimeoid="908715157"]' );
				await expect( video ).toBeVisible();
				const tileBounds = await launcher.boundingBox();
				const videoBounds = await video.boundingBox();
				expect( videoBounds.y ).toBeGreaterThan( tileBounds.y + tileBounds.height );
			}
			await launcher.hover();
			await expect.poll( () => launcher.evaluate( ( element ) => {
				const clip = element.closest( '.shield-rail-layout__content' ).getBoundingClientRect();
				return element.getBoundingClientRect().top - clip.top;
			} ), { message: 'hovered tile retains clearance inside its scroll panel' } ).toBeGreaterThan( 1 );
			await expectUpgradeModal( page, launcher );
		} );
	} );
}

test( 'file import remains usable while network sync prompts for an upgrade', async ( { page, fixtureApi } ) => {
	await fixtureApi.withImportExportFileFixture( async () => {
		await openShieldRoute( page, { nav: 'tools', nav_sub: 'importexport' } );
		await expect( page.locator( '#ImportExportFileForm' ) ).toBeVisible();
		await page.locator( '[data-import-export-tab="network_sync"]' ).click();
		await expectUpgradeModal( page, page.locator( '[data-import-export-panel="network_sync"] [data-pro-upsell="1"]' ) );
		await page.locator( '[data-import-export-tab="file"]' ).click();
		await expect( page.locator( '#ImportExportFileForm' ) ).toBeVisible();
	} );
} );
