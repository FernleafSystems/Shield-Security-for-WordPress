const { test, expect } = require( './support/shield-test' );
const { expectNoAxeViolations } = require( './support/accessibility' );
const { openShieldRoute } = require( './support/shield-browser' );
const {
	expectModalHiddenWithoutAriaModal,
	expectNamedDialog,
} = require( './support/modal-accessibility' );

const SUPER_SEARCH_RENDER_SLUG = 'render_super_search_results';

function isSuperSearchResponseFor( response, search ) {
	const params = new URLSearchParams( response.request().postData() || '' );
	return params.get( 'render_slug' ) === SUPER_SEARCH_RENDER_SLUG
		&& params.get( 'search' ) === search;
}

async function submitSuperSearch( page, input, search ) {
	const response = page.waitForResponse(
		( candidate ) => isSuperSearchResponseFor( candidate, search ),
		{ timeout: 20_000 }
	);
	await input.fill( search );
	await input.press( 'End' );
	await response;
}

test( 'super search opens a named dialog and restores focus to launcher', async ( { page } ) => {
	await openShieldRoute( page, {
		nav: 'dashboard',
		nav_sub: 'overview',
	} );

	const launcher = page.locator( '#SuperSearchLaunchButton' );
	await expect( launcher ).toBeVisible();
	await launcher.click();

	const modal = page.locator( '#ModalSuperSearchBox.modal.show' );
	await expect( modal ).toBeVisible();
	await expectNamedDialog( page, modal, 'ModalSuperSearchTitle' );
	const input = page.locator( '#ModalSuperSearchInput' );
	await expect( input ).toBeFocused();
	await expect( input ).toHaveAccessibleName( /\S/ );
	await expectNoAxeViolations( page, '#ModalSuperSearchBox' );

	await submitSuperSearch( page, input, 'security' );
	await expect( modal.locator( '.modal-body h3' ).first() ).toBeVisible();
	await expect( modal.locator( '.modal-body a' ).first() ).toBeVisible();
	await expectNoAxeViolations( page, '#ModalSuperSearchBox' );

	await submitSuperSearch( page, input, 'shieldguaranteedemptyreplacement987654321' );
	await expect( modal.locator( '.modal-body h3' ) ).toHaveCount( 0 );
	await expect( modal.locator( '.modal-body a' ) ).toHaveCount( 0 );
	await expect( modal.locator( '.modal-body p' ).first() ).toBeVisible();
	await expectNoAxeViolations( page, '#ModalSuperSearchBox' );

	await modal.locator( '.btn-close' ).click();
	await expectModalHiddenWithoutAriaModal( page, '#ModalSuperSearchBox' );
	await expect( launcher ).toBeFocused();
} );
