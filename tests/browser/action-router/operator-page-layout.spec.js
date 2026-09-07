const { test, expect } = require( './support/shield-test' );
const { openShieldRoute } = require( './support/shield-browser' );

for ( const [ nav, navSub ] of [
	[ 'tools', 'importexport' ],
	[ 'tools', 'debug' ],
	[ 'rules', 'manage' ],
	[ 'rules', 'build' ],
	[ 'rules', 'summary' ],
	[ 'license', 'check' ],
] ) {
	test( `${nav}/${navSub} contains its page body without horizontal panel scrolling`, async ( { page } ) => {
		await openShieldRoute( page, { nav, nav_sub: navSub } );
		const panel = page.locator( '.shield-rail-layout__content' ).first();
		await expect( panel ).toBeVisible();
		for ( const width of [ 768, 1280, 1920 ] ) {
			await page.setViewportSize( { width, height: 1000 } );
			const overflow = await panel.evaluate( ( element ) => ( {
				panel: element.scrollWidth - element.clientWidth,
				page: document.documentElement.scrollWidth - document.documentElement.clientWidth,
			} ) );
			expect.soft( overflow.panel, `content panel at ${width}px` ).toBeLessThanOrEqual( 1 );
			expect.soft( overflow.page, `page at ${width}px` ).toBeLessThanOrEqual( 1 );
		}
	} );
}
