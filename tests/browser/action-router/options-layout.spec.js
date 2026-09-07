const { test, expect } = require( './support/shield-test' );
const { openShieldRoute } = require( './support/shield-browser' );

test( 'multi-section settings stack navigation before the options become cramped', async ( { page } ) => {
	await openShieldRoute( page, {
		nav: 'zones',
		nav_sub: 'overview',
		component: 'module_integrations',
	} );
	const form = page.locator( '.offcanvas.show .options_form_for--modern' );
	await expect( form ).toBeVisible();
	for ( const [ width, stacked ] of [ [ 768, true ], [ 1280, true ], [ 1440, true ], [ 1920, false ] ] ) {
		await page.setViewportSize( { width, height: 1000 } );
		await expect.poll( () => form.evaluate( ( element ) => {
			const rail = element.querySelector( '.shield-options-rail' ).getBoundingClientRect();
			const content = element.querySelector( '.shield-options-content' ).getBoundingClientRect();
			return rail.bottom <= content.top;
		} ), `navigation placement at ${width}px` ).toBe( stacked );
		const layout = await form.evaluate( ( element ) => {
			const content = element.querySelector( '.shield-options-content' );
			return {
				unusedWidth: element.clientWidth - content.clientWidth,
				overflow: element.scrollWidth - element.clientWidth,
			};
		} );
		expect( layout.overflow, `form overflow at ${width}px` ).toBeLessThanOrEqual( 1 );
		if ( stacked ) {
			expect( layout.unusedWidth, `options use the full form width at ${width}px` ).toBeLessThanOrEqual( 1 );
		}
		await expect( form.locator( '.shield-options-rail-save button[type="submit"]' ) ).toBeVisible();
	}
} );
