const { openShieldRoute, test, expect } = require( './support/shield-test' );
const { ActionsQueuePage } = require( './support/actions-queue-page' );

/**
 * Plugin and theme housekeeping never ends on a busy site, so each kind can be left out of Review next. The switch
 * in a kind's heading asks first, then the kind goes without the page reloading; the Review Next Items setting,
 * opened beside the queue, brings it back. A lane with nothing in it stays, with a zero, rather than vanishing.
 * The fixture installs a plugin that isn't active, so there is always inactive-plugin housekeeping to report.
 */

const KIND = 'wp_plugins_inactive';

const section = ( page, key ) => page.locator( `[data-actions-queue-section-key="${key}"]` );
const sectionHead = ( page, key ) => section( page, key ).locator( '[data-actions-queue-section-toggle="1"]' );
const kindBox = ( page ) => section( page, 'review' ).locator( `[data-actions-queue-kind="${KIND}"]` );
const reviewCount = async ( page ) => Number( await section( page, 'review' ).locator( '.actions-queue-section__count' ).innerText() );

async function openQueue( page ) {
	await openShieldRoute( page, { nav: 'scans', nav_sub: 'overview' } );
	await new ActionsQueuePage( page ).showSectionsView();
}

async function openReviewSection( page ) {
	await openQueue( page );
	const head = sectionHead( page, 'review' );
	if ( await head.getAttribute( 'aria-expanded' ) !== 'true' ) {
		await head.click();
	}
	await expect( head ).toHaveAttribute( 'aria-expanded', 'true' );
}

test( 'a kind left out of Review next goes once confirmed, and the setting beside the queue brings it back', async ( { page, fixtureApi } ) => {
	await fixtureApi.withActionsQueueFixture( 'inactive_plugins', async () => {
		await openReviewSection( page );
		const alertSwitch = kindBox( page ).getByRole( 'switch' );
		await expect( alertSwitch ).toBeChecked();
		const countWithKind = await reviewCount( page );

		const navigations = [];
		page.on( 'framenavigated', ( frame ) => frame === page.mainFrame() && navigations.push( frame.url() ) );

		// Declining leaves everything as it was. The switch works from the keyboard as it does by clicking.
		await alertSwitch.focus();
		await page.keyboard.press( 'Space' );
		const dialog = page.getByRole( 'dialog' );
		await expect( dialog ).toBeVisible();
		await expect( dialog ).toContainText( 'Configure' );
		await dialog.getByRole( 'button', { name: 'Cancel' } ).click();
		await expect( dialog ).toBeHidden();
		await expect( alertSwitch ).toBeChecked();
		await expect( kindBox( page ) ).toHaveCount( 1 );

		await alertSwitch.click();
		await page.getByRole( 'dialog' ).getByRole( 'button', { name: 'Turn off alerts' } ).click();
		await expect( kindBox( page ) ).toHaveCount( 0, { timeout: 30_000 } );
		// The lane stays, counting fewer.
		await expect( section( page, 'review' ) ).toHaveCount( 1 );
		expect( await reviewCount( page ) ).toBeLessThan( countWithKind );

		// The setting opens beside the queue; saving it refreshes the queue in place.
		await section( page, 'review' ).locator( '.zone_component_action' ).click();
		const offcanvas = page.locator( '#AptoOffcanvas.show' );
		const choice = offcanvas.locator( `input[name="maintenance_alert_items[]"][value="${KIND}"]` );
		await expect( choice ).not.toBeChecked();
		await choice.check();
		await offcanvas.locator( 'form button[type="submit"]' ).click();
		await expect( kindBox( page ) ).toHaveCount( 1, { timeout: 30_000 } );
		expect( await reviewCount( page ) ).toBe( countWithKind );
		expect( navigations ).toEqual( [] );
	} );
} );

test( 'Fix now and Review next always show, and a lane with nothing to do is folded, with a zero and a line of text', async ( { page, fixtureApi } ) => {
	await fixtureApi.withActionsQueueFixture( 'inactive_plugins', async () => {
		await openQueue( page );

		// The fixture puts nothing in Fix now, and an inactive plugin in Review next.
		await expect( page.locator( '[data-actions-queue-tab="critical"]' ) ).toHaveCount( 1 );
		const fixNow = section( page, 'critical' );
		await expect( fixNow ).toHaveClass( /is-empty/ );
		await expect( fixNow.locator( '.actions-queue-section__count' ) ).toHaveText( '0' );
		await expect( sectionHead( page, 'critical' ) ).toHaveAttribute( 'aria-expanded', 'false' );
		await expect( sectionHead( page, 'critical' ).locator( '.is-folded-only' ) ).toBeVisible();
		await sectionHead( page, 'critical' ).click();
		await expect( fixNow.locator( '.actions-queue-section__empty' ) ).toBeVisible();

		await expect( page.locator( '[data-actions-queue-tab="review"]' ) ).toHaveCount( 1 );
		const review = section( page, 'review' );
		await expect( review ).not.toHaveClass( /is-empty/ );
		await expect( review.locator( '.actions-queue-section__empty' ) ).toHaveCount( 0 );
		expect( await reviewCount( page ) ).toBeGreaterThan( 0 );
	} );
} );
