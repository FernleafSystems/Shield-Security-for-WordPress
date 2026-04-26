const { test, expect } = require( '@playwright/test' );
const { openShieldRoute } = require( './support/shield-browser' );
const {
	expectModalHiddenWithoutAriaModal,
	expectNamedDialog,
} = require( './support/modal-accessibility' );

test.setTimeout( 180_000 );

async function rerunDashboardOnboarding( page, videoEnabled ) {
	await page.evaluate( ( isEnabled ) => {
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
	}, videoEnabled );
}

test( 'dashboard onboarding shows intro video modal when video feature is enabled', async ( { page } ) => {
	await openShieldRoute( page, {
		nav: 'dashboard',
		nav_sub: 'overview',
		force_tour: '1',
	} );

	await rerunDashboardOnboarding( page, true );

	const modal = page.locator( '#ShieldModalContainer.modal.show' );
	await expect( modal ).toBeVisible();
	await expectNamedDialog( page, modal );
	expect( await modal.evaluate( ( node ) => node.contains( document.activeElement ) ) ).toBe( true );
	await expect( modal.locator( '.shield-video-modal' ) ).toBeVisible();
	await expect( modal.locator( 'iframe' ) ).toHaveAttribute( 'src', /player\.vimeo\.com\/video\/123456789/ );
	await expect( page.locator( '.introjs-overlay' ) ).toHaveCount( 0 );
	await modal.locator( '.btn-close' ).click();
	await expectModalHiddenWithoutAriaModal( page, '#ShieldModalContainer' );
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
