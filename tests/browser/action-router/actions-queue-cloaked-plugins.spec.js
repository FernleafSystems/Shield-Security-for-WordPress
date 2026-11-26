const { openShieldRoute, test, expect } = require( './support/shield-test' );
const { ActionsQueuePage } = require( './support/actions-queue-page' );

/**
 * Cloaked plugins are listed in the Actions Queue itself, each opening in a drawer that holds its details and the
 * same actions as its row. Ignoring one takes it out of the count but leaves it a
 * click away, faded, behind "1 ignored" in the group's heading, from where it can be restored.
 */

const section = ( page, key ) => page.locator( `[data-actions-queue-section-key="${key}"]` );
const cloakedGroup = ( page ) => page.locator( '[data-actions-queue-group-key="hidden_plugins"]' );
const ignoredToggle = ( page ) => cloakedGroup( page ).locator( '[data-actions-queue-box-toggle="ignored:hidden_plugins"]' );
const ignoreButtons = ( page ) => cloakedGroup( page ).locator( '[data-actions-queue-maintenance-kind="ignore"]' );
const restoreButtons = ( page ) => cloakedGroup( page ).locator( '[data-actions-queue-maintenance-kind="unignore"]' );
const alwaysIgnoreButtons = ( page ) => cloakedGroup( page ).locator( '[data-actions-queue-maintenance-kind=""][data-actions-queue-maintenance-action]' );

async function openQueue( page ) {
	await openShieldRoute( page, { nav: 'scans', nav_sub: 'overview' } );
	await new ActionsQueuePage( page ).showSectionsView();
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
			await openSection( page, 'critical' );
			await ignoredToggle( page ).click();

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

			// A plugin's drawer takes the screen's width rather than run off its edge.
			const drawer = page.locator( '#AptoOffcanvas' );
			await cloakedGroup( page ).locator( '[data-actions-queue-row-drawer]' ).first().click();
			await expect( drawer.locator( '.actions-queue-drawer' ) ).toBeVisible();
			await expect( drawer ).not.toHaveClass( /showing/ );
			const box = await drawer.boundingBox();
			expect( box.x, `drawer's left edge at ${width}px` ).toBeGreaterThanOrEqual( 0 );
			expect( box.x + box.width, `drawer's right edge at ${width}px` ).toBeLessThanOrEqual( width );
			await drawer.locator( '.btn-close' ).click();
			await expect( drawer ).toBeHidden();
		}
	} );
} );

test( 'cloaked plugins can be ignored and restored in the queue, singly and together', async ( { page, fixtureApi } ) => {
	await fixtureApi.withActionsQueueFixture( 'ignored_cloaked_plugins', async () => {
		await openQueue( page );
		await openSection( page, 'critical' );

		// With every one ignored the group stays in its lane with nothing left to do. Ignored isn't passing.
		const allIgnoredGroup = section( page, 'critical' ).locator( '.is-all-ignored[data-actions-queue-group-key="hidden_plugins"]' );
		await expect( allIgnoredGroup ).toHaveCount( 1 );
		await expect( section( page, 'passing' ).locator( '[data-actions-queue-group-key="hidden_plugins"]' ) ).toHaveCount( 0 );
		await expect( ignoredToggle( page ) ).toHaveText( /\b2\b/ );

		const navigations = [];
		page.on( 'framenavigated', ( frame ) => frame === page.mainFrame() && navigations.push( frame.url() ) );

		await ignoredToggle( page ).click();
		const ignoredRows = cloakedGroup( page ).locator( '.actions-queue-row.is-ignored' );
		await expect( ignoredRows ).toHaveCount( 2 );
		await expect( ignoredRows.first() ).toHaveCSS( 'opacity', '0.45' );

		await cloakedGroup( page ).locator( '[data-actions-queue-maintenance-all="unignore"]' ).click();
		await expect( allIgnoredGroup ).toHaveCount( 0, { timeout: 20_000 } );
		await openSection( page, 'critical' );
		await expect( ignoreButtons( page ) ).toHaveCount( 2 );
		await expect( page.locator( '[data-actions-queue-detail="1"]' ) ).toBeHidden();

		// The reason is on the row and nothing more: the rest is in the drawer the plugin's name opens.
		const firstRow = cloakedGroup( page ).locator( '.actions-queue-row--item' ).first();
		await expect( firstRow.locator( '.actions-queue-row__desc' ) ).not.toBeEmpty();
		await expect( firstRow.locator( 'details' ) ).toHaveCount( 0 );
		const drawer = page.locator( '#AptoOffcanvas' );
		const openRows = cloakedGroup( page ).locator( '.actions-queue-row--item:not(.is-ignored) [data-actions-queue-row-drawer]' );
		const firstName = ( await firstRow.locator( '[data-actions-queue-row-drawer]' ).innerText() ).trim();
		await firstRow.locator( '[data-actions-queue-row-drawer]' ).click();
		await expect( drawer.locator( '.actions-queue-drawer code' ).first() ).toContainText( 'wp-content/plugins/shield-browser-cloaked-fixture' );

		// A button in the drawer does what the row's does: the plugin is ignored, and the drawer closes.
		await drawer.locator( '[data-actions-queue-drawer-press="ignore"]' ).click();
		await expect( drawer ).toBeHidden();
		await expect( ignoreButtons( page ) ).toHaveCount( 1, { timeout: 20_000 } );
		await expect( ignoredToggle( page ) ).toHaveText( /\b1\b/ );
		// It's the plugin the drawer showed that was ignored, not the other one.
		expect( ( await openRows.innerText() ).trim() ).not.toBe( firstName );

		// An ignored plugin's drawer stops ignoring it.
		await ignoredToggle( page ).click();
		await cloakedGroup( page ).locator( '.actions-queue-row.is-ignored [data-actions-queue-row-drawer]' ).click();
		await drawer.locator( '[data-actions-queue-drawer-press="unignore"]' ).click();
		await expect( drawer ).toBeHidden();
		await expect( ignoreButtons( page ) ).toHaveCount( 2, { timeout: 20_000 } );
		await expect( ignoredToggle( page ) ).toHaveCount( 0 );

		// The row's own buttons still work without the drawer.
		await ignoreButtons( page ).first().click();
		await expect( ignoreButtons( page ) ).toHaveCount( 1, { timeout: 20_000 } );
		await ignoredToggle( page ).click();
		await restoreButtons( page ).click();
		await expect( ignoreButtons( page ) ).toHaveCount( 2, { timeout: 20_000 } );
		await expect( ignoredToggle( page ) ).toHaveCount( 0 );

		// "Always ignore" sits beside "Ignore" on each row, outside "Ignore all", and works on its own.
		await expect( alwaysIgnoreButtons( page ) ).toHaveCount( 2 );
		await alwaysIgnoreButtons( page ).first().click();
		await expect( alwaysIgnoreButtons( page ) ).toHaveCount( 1, { timeout: 20_000 } );
		await expect( ignoredToggle( page ) ).toHaveText( /\b1\b/ );

		await ignoredToggle( page ).click();
		await restoreButtons( page ).click();
		await expect( ignoreButtons( page ) ).toHaveCount( 2, { timeout: 20_000 } );

		await cloakedGroup( page ).locator( '[data-actions-queue-maintenance-all="ignore"]' ).click();
		await expect( allIgnoredGroup ).toHaveCount( 1, { timeout: 20_000 } );
		expect( navigations ).toEqual( [] );
	} );
} );
