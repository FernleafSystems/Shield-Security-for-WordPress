const { test, expect } = require( './support/shield-test' );
const { openShieldRoute } = require( './support/shield-browser' );

for ( const width of [ 768, 1280, 1920 ] ) {
	test( `lockdown fits the viewport and keeps checkbox labels aligned at ${width}px`, async ( { page } ) => {
		await page.setViewportSize( { width, height: 1000 } );
		await openShieldRoute( page, { nav: 'tools', nav_sub: 'blockdown' } );
		const checkboxes = page.locator( '#FormBlockdown input[type="checkbox"]' );
		await expect( checkboxes.first() ).toBeVisible();

		const overflow = await page.evaluate( () => document.documentElement.scrollWidth - document.documentElement.clientWidth );
		expect.soft( overflow ).toBeLessThanOrEqual( 1 );
		const panelOverflow = await page.locator( '.shield-rail-layout__content' ).first().evaluate( ( panel ) => panel.scrollWidth - panel.clientWidth );
		expect.soft( panelOverflow ).toBeLessThanOrEqual( 1 );
		for ( const checkbox of await checkboxes.all() ) {
			const alignment = await checkbox.evaluate( ( input ) => {
				const control = input.getBoundingClientRect();
				const label = input.labels[ 0 ].getBoundingClientRect();
				return { controlTop: control.top, controlBottom: control.bottom, labelTop: label.top, labelBottom: label.bottom };
			} );
			expect.soft( alignment.labelTop ).toBeLessThan( alignment.controlBottom );
			expect.soft( alignment.labelBottom ).toBeGreaterThan( alignment.controlTop );
		}

		// Verify the layout correction preserves keyboard operation, without submitting lockdown.
		const confirmation = page.locator( '#confirm_cache' );
		await confirmation.focus();
		await page.keyboard.press( 'Space' );
		await expect( confirmation ).toBeChecked();
		await page.keyboard.press( 'Space' );
		await expect( confirmation ).not.toBeChecked();
	} );
}
