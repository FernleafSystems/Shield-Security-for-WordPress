const { openShieldRoute, test, expect } = require( './support/shield-test' );
const { ActionsQueuePage } = require( './support/actions-queue-page' );
const {
	expectFocusWithin,
	expectModalHiddenWithoutAriaModal,
	expectNamedDialog,
} = require( './support/modal-accessibility' );

test( 'free Actions Queue upgrade cards open the shared Pro modal without drilling down', async ( { page, fixtureApi } ) => {
	await fixtureApi.withActionsQueueFixture( 'pro_upsell', async ( fixture ) => {
		const actionsQueuePage = new ActionsQueuePage( page );
		await openShieldRoute( page, {
			nav: 'scans',
			nav_sub: 'overview',
		} );
		await page.setViewportSize( { width: 500, height: 900 } );

		const bucket = await actionsQueuePage.waitForBucket( fixture.bucket_key );
		await bucket.click();
		await expect( page.locator( '[data-actions-queue-groups="1"]' ) ).toBeVisible();

		const modal = page.locator( '#ShieldModalContainer' );
		const modalContent = modal.locator( '.modal-content' );
		const requests = [];
		page.on( 'request', ( request ) => {
			if ( request.url().includes( 'admin-ajax.php' ) ) {
				requests.push( request );
			}
		} );

		for ( const groupKey of fixture.context.pro_upsell_group_keys ) {
			const launcher = await actionsQueuePage.waitForGroupOuter( groupKey );
			await expect( launcher ).toHaveAttribute( 'data-actions-queue-pro-upsell', '1' );
			await expect( launcher ).not.toHaveAttribute( 'data-drill-target' );
			await expect( launcher ).not.toHaveAttribute( 'data-drill-bucket-selection' );
			await expect( launcher ).not.toHaveAttribute( 'data-drill-group-selection' );

			requests.length = 0;
			await launcher.click();

			await expect( modal ).toBeVisible();
			await expectNamedDialog( page, modal, 'actions-queue-pro-upsell-title' );
			await expectFocusWithin( modal );
			await expect( modalContent.locator( '.actions-queue-pro-upsell__table' ) ).toBeVisible();
			for ( const [ selector, href ] of [
				[ '.actions-queue-pro-upsell__button', 'https://clk.shldscrty.com/shieldgoprofeature' ],
				[ '.actions-queue-pro-upsell__compare-link', 'https://clk.shldscrty.com/gp' ],
			] ) {
				const link = modalContent.locator( selector );
				await expect( link ).toHaveAttribute( 'href', href );
				await expect( link ).toHaveAttribute( 'target', '_blank' );
				await expect( link ).toHaveAttribute( 'rel', 'noopener noreferrer' );
			}
			expect( requests ).toEqual( [] );
			await expect( page.locator( '[data-actions-queue-detail="1"]' ) ).toHaveCount( 0 );

			if ( groupKey === fixture.context.pro_upsell_group_keys[ 0 ] ) {
				await modalContent.locator( '[data-bs-dismiss="modal"]' ).click();
			}
			else {
				await page.keyboard.press( 'Escape' );
			}
			await expectModalHiddenWithoutAriaModal( page, '#ShieldModalContainer' );
			await expect( launcher ).toBeFocused();
		}
	} );
} );
