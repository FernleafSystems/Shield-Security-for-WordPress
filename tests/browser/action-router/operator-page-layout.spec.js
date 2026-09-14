const { test, expect } = require( './support/shield-test' );
const { openShieldRoute } = require( './support/shield-browser' );

for ( const [ nav, navSub ] of [
	[ 'tools', 'importexport' ],
	[ 'tools', 'debug' ],
	[ 'rules', 'manage' ],
	[ 'rules', 'build' ],
	[ 'rules', 'summary' ],
	[ 'license', 'check' ],
	[ 'scans', 'run' ],
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

test( 'operator context menu remains beside the breadcrumb path at narrow widths', async ( { page } ) => {
	await openShieldRoute( page, { nav: 'scans', nav_sub: 'run' } );
	const path = page.locator( '[data-operator-step-tabs-list="1"]' );
	await expect( path.locator( 'button' ).last() ).toBeVisible();
	const menu = page.locator( '.page-action-menu-toggle' );
	for ( const width of [ 1920, 1280, 768, 500, 375 ] ) {
		await page.setViewportSize( { width, height: 1000 } );
		const pathBounds = await path.boundingBox();
		const menuBounds = await menu.boundingBox();
		expect.soft( menuBounds.y, `menu stays in path row at ${width}px` ).toBeLessThan( pathBounds.y + pathBounds.height );
		expect.soft( menuBounds.x, `menu does not overlap path at ${width}px` ).toBeGreaterThanOrEqual( pathBounds.x + pathBounds.width - 1 );
		await menu.click();
		await expect( page.locator( '.operator-step-tabs__actions .dropdown-menu' ) ).toBeVisible();
		await page.keyboard.press( 'Escape' );
	}
} );
