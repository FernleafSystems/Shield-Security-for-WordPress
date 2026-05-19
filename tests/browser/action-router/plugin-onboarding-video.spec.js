const { test, expect } = require( './support/shield-test' );
const { openShieldRoute } = require( './support/shield-browser' );
const {
	expectModalHiddenWithoutAriaModal,
	expectNamedDialog,
} = require( './support/modal-accessibility' );

test.setTimeout( 180_000 );

async function rerunDashboardOnboarding( page, videoEnabled ) {
	return page.evaluate( ( isEnabled ) => {
		const onboarding = window.shield_vars_plugin_onboarding?.comps?.plugin_onboarding;
		if ( !onboarding?.vars?.tour ) {
			throw new Error( 'Missing plugin onboarding localized data.' );
		}

		onboarding.vars.tour.is_available = true;
		onboarding.vars.tour.video_modal.is_enabled = isEnabled;
		onboarding.vars.tour.video_modal.embed_url = isEnabled
			? 'https://player.vimeo.com/video/123456789?h=abc123'
			: '';

		window.dispatchEvent( new Event( 'load' ) );

		return onboarding.vars.tour.video_modal.skip_label;
	}, videoEnabled );
}

test( 'dashboard onboarding shows intro video modal when video feature is enabled', async ( { page } ) => {
	await openShieldRoute( page, {
		nav: 'dashboard',
		nav_sub: 'overview',
		force_tour: '1',
	} );

	const skipVideoLabel = await rerunDashboardOnboarding( page, true );

	const modal = page.locator( '#ShieldModalContainer.modal.show' );
	const footerActions = modal.locator( '.modal-footer' ).getByRole( 'button' );
	await expect( modal ).toBeVisible();
	await expectNamedDialog( page, modal );
	expect( await modal.evaluate( ( node ) => node.contains( document.activeElement ) ) ).toBe( true );
	await expect( modal.locator( '.shield-video-modal' ) ).toBeVisible();
	await expect( modal.locator( 'iframe' ) ).toHaveAttribute( 'src', /player\.vimeo\.com\/video\/123456789/ );
	await expect( footerActions ).toHaveCount( 1 );
	await expect( footerActions.first() ).toHaveText( skipVideoLabel );
	await expect( page.locator( '.introjs-overlay' ) ).toHaveCount( 0 );
	await footerActions.first().click();
	await expectModalHiddenWithoutAriaModal( page, '#ShieldModalContainer' );
	await expect( page.locator( '.introjs-overlay' ).first() ).toBeVisible();
} );

test( 'dashboard onboarding skips intro video modal when video feature is disabled', async ( { page } ) => {
	await openShieldRoute( page, {
		nav: 'dashboard',
		nav_sub: 'overview',
		force_tour: '1',
	} );

	await rerunDashboardOnboarding( page, false );

	await expect( page.locator( '#ShieldModalContainer.modal.show .shield-video-modal' ) ).toHaveCount( 0 );
	await expect( page.locator( '.introjs-overlay' ).first() ).toBeVisible();
} );
