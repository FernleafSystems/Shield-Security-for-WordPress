const { openShieldRoute, test, expect } = require( './support/shield-test' );

/**
 * Abandoned plugins are listed in the Actions Queue itself. Ignoring one takes it out of the count but leaves it
 * a click away, faded, behind "1 ignored" in the group's heading, from where it can be restored.
 */

const section = ( page, key ) => page.locator( `[data-actions-queue-section-key="${key}"]` );
const abandonedGroup = ( page ) => page.locator( '[data-actions-queue-group-key="abandoned"]' );
const ignoredToggle = ( page ) => abandonedGroup( page ).locator( '[data-actions-queue-box-toggle="ignored:abandoned"]' );
const ignoreButtons = ( page ) => abandonedGroup( page ).locator( '[data-actions-queue-maintenance-kind="ignore"]' );
const restoreButtons = ( page ) => abandonedGroup( page ).locator( '[data-actions-queue-maintenance-kind="unignore"]' );

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

test( 'an abandoned plugin can be ignored in place, seen faded, and restored', async ( { page, fixtureApi } ) => {
	await fixtureApi.withActionsQueueFixture( 'abandoned_plugins', async () => {
		await openQueue( page );
		await openSection( page, 'critical' );

		await expect( ignoreButtons( page ) ).toHaveCount( 2 );
		await expect( abandonedGroup( page ).locator( '[data-actions-queue-maintenance-ignore-all="1"]' ) ).toBeVisible();
		await expect( ignoredToggle( page ) ).toHaveCount( 0 );

		const navigations = [];
		page.on( 'framenavigated', ( frame ) => frame === page.mainFrame() && navigations.push( frame.url() ) );

		await ignoreButtons( page ).first().click();
		await expect( ignoreButtons( page ) ).toHaveCount( 1, { timeout: 20_000 } );
		await expect( ignoredToggle( page ) ).toHaveText( /1 ignored/ );
		await expect( ignoredToggle( page ) ).toHaveAttribute( 'aria-expanded', 'false' );
		await expect( restoreButtons( page ) ).toBeHidden();

		await ignoredToggle( page ).click();
		await expect( ignoredToggle( page ) ).toHaveAttribute( 'aria-expanded', 'true' );
		const ignoredRow = abandonedGroup( page ).locator( '.actions-queue-row.is-ignored' );
		await expect( ignoredRow ).toBeVisible();
		await expect( ignoredRow ).toHaveCSS( 'opacity', '0.6' );

		await restoreButtons( page ).click();
		await expect( ignoreButtons( page ) ).toHaveCount( 2, { timeout: 20_000 } );
		await expect( ignoredToggle( page ) ).toHaveCount( 0 );
		expect( navigations ).toEqual( [] );
	} );
} );

test( 'with every abandoned plugin ignored, the group is passing and they can still be restored', async ( { page, fixtureApi } ) => {
	await fixtureApi.withActionsQueueFixture( 'abandoned_plugins', async () => {
		await openQueue( page );
		await openSection( page, 'critical' );

		await abandonedGroup( page ).locator( '[data-actions-queue-maintenance-ignore-all="1"]' ).click();
		await expect( section( page, 'passing' ).locator( '[data-actions-queue-group-key="abandoned"]' ) ).toHaveCount( 1, { timeout: 30_000 } );

		// Ignored results count for nothing, in the sidebar or the admin bar. A fresh page also has to refresh in
		// place once it restores one, without an earlier action in the same page having prepared the queue.
		await openQueue( page );
		await expect( page.locator( '.mode-badge.status-critical' ) ).toHaveCount( 0 );
		await expect( page.locator( '#wpadminbar .shield-counter--issue' ) ).toHaveCount( 0 );

		await openSection( page, 'passing' );
		await expect( ignoredToggle( page ) ).toHaveText( /2 ignored/ );
		await expect( ignoreButtons( page ) ).toHaveCount( 0 );

		await ignoredToggle( page ).click();
		await expect( abandonedGroup( page ).locator( '.actions-queue-row.is-ignored' ) ).toHaveCount( 2 );

		await restoreButtons( page ).first().click();
		await expect( section( page, 'critical' ).locator( '[data-actions-queue-group-key="abandoned"]' ) ).toHaveCount( 1, { timeout: 20_000 } );
	} );
} );
