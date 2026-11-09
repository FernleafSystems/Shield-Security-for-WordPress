const { openShieldRoute, test, expect } = require( './support/shield-test' );

/**
 * Cloaked plugins are listed in the Actions Queue itself. Ignoring one takes it out of the count but leaves it a
 * click away, faded, behind "1 ignored" in the group's heading, from where it can be restored.
 */

const section = ( page, key ) => page.locator( `[data-actions-queue-section-key="${key}"]` );
const cloakedGroup = ( page ) => page.locator( '[data-actions-queue-group-key="hidden_plugins"]' );
const ignoredToggle = ( page ) => cloakedGroup( page ).locator( '[data-actions-queue-box-toggle="ignored:hidden_plugins"]' );
const ignoreButtons = ( page ) => cloakedGroup( page ).locator( '[data-actions-queue-maintenance-kind="ignore"]' );
const restoreButtons = ( page ) => cloakedGroup( page ).locator( '[data-actions-queue-maintenance-kind="unignore"]' );

async function openQueue( page ) {
	await openShieldRoute( page, { nav: 'scans', nav_sub: 'overview' } );
	const sectionsView = page.locator( '[data-actions-queue-view="sections"]' );
	if ( await sectionsView.getAttribute( 'aria-pressed' ) !== 'true' ) {
		await sectionsView.click();
	}
}

async function openSection( page, key ) {
	const head = section( page, key ).locator( '[data-actions-queue-section-toggle="1"]' );
	if ( await head.getAttribute( 'aria-expanded' ) !== 'true' ) {
		await head.click();
	}
	await expect( head ).toHaveAttribute( 'aria-expanded', 'true' );
}

test( 'the queue fits a phone, with nothing cut off by its box', async ( { page, fixtureApi } ) => {
	await fixtureApi.withActionsQueueFixture( 'ignored_cloaked_plugins', async () => {
		for ( const width of [ 390, 320 ] ) {
			await page.setViewportSize( { width, height: 844 } );
			await openQueue( page );
			await openSection( page, 'passing' );
			await ignoredToggle( page ).click();
			await cloakedGroup( page ).locator( 'summary' ).first().click();

			const measured = await page.evaluate( () => {
				const content = document.querySelector( '.shield-rail-layout__content' );
				const cutOff = [ ...document.querySelectorAll( '.actions-queue-box' ) ].flatMap( ( box ) => {
					const edge = box.getBoundingClientRect().right + 1;
					return [ ...box.querySelectorAll( '*' ) ]
						.filter( ( el ) => el.getClientRects().length > 0 && el.getBoundingClientRect().right > edge )
						.map( ( el ) => el.className );
				} );
				return { overflow: content.scrollWidth - content.clientWidth, cutOff };
			} );
			expect( measured, `at ${width}px` ).toEqual( { overflow: 0, cutOff: [] } );
		}
	} );
} );

test( 'cloaked plugins can be ignored and restored in the queue, singly and together', async ( { page, fixtureApi } ) => {
	await fixtureApi.withActionsQueueFixture( 'ignored_cloaked_plugins', async () => {
		await openQueue( page );
		await openSection( page, 'passing' );

		// With every one ignored the group is passing, and says so rather than claiming none were found.
		const passingGroup = section( page, 'passing' ).locator( '[data-actions-queue-group-key="hidden_plugins"]' );
		await expect( passingGroup ).toHaveCount( 1 );
		await expect( ignoredToggle( page ) ).toHaveText( /2 ignored/ );
		await expect( passingGroup ).not.toContainText( 'No cloaked plugins detected' );

		const navigations = [];
		page.on( 'framenavigated', ( frame ) => frame === page.mainFrame() && navigations.push( frame.url() ) );

		await ignoredToggle( page ).click();
		const ignoredRows = cloakedGroup( page ).locator( '.actions-queue-row.is-ignored' );
		await expect( ignoredRows ).toHaveCount( 2 );
		await expect( ignoredRows.first() ).toHaveCSS( 'opacity', '0.45' );

		await cloakedGroup( page ).locator( '[data-actions-queue-maintenance-all="unignore"]' ).click();
		await expect( section( page, 'critical' ).locator( '[data-actions-queue-group-key="hidden_plugins"]' ) ).toHaveCount( 1, { timeout: 20_000 } );
		await openSection( page, 'critical' );
		await expect( ignoreButtons( page ) ).toHaveCount( 2 );
		await expect( page.locator( '[data-actions-queue-detail="1"]' ) ).toBeHidden();

		// The reason is on the row, and the rest is a click away.
		const firstRow = cloakedGroup( page ).locator( '.actions-queue-row--item' ).first();
		await expect( firstRow.locator( '.actions-queue-row__desc' ) ).not.toBeEmpty();
		const path = firstRow.locator( '.actions-queue-row__details code' );
		await expect( path ).toBeHidden();
		await firstRow.locator( 'summary' ).click();
		await expect( path ).toBeVisible();
		await expect( path ).toContainText( 'wp-content/plugins/shield-browser-cloaked-fixture' );

		await ignoreButtons( page ).first().click();
		await expect( ignoreButtons( page ) ).toHaveCount( 1, { timeout: 20_000 } );
		await expect( ignoredToggle( page ) ).toHaveText( /1 ignored/ );

		await ignoredToggle( page ).click();
		await restoreButtons( page ).click();
		await expect( ignoreButtons( page ) ).toHaveCount( 2, { timeout: 20_000 } );
		await expect( ignoredToggle( page ) ).toHaveCount( 0 );

		await cloakedGroup( page ).locator( '[data-actions-queue-maintenance-all="ignore"]' ).click();
		await expect( passingGroup ).toHaveCount( 1, { timeout: 20_000 } );
		expect( navigations ).toEqual( [] );
	} );
} );
