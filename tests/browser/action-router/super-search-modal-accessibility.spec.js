const { test, expect } = require( './support/shield-test' );
const { openShieldRoute } = require( './support/shield-browser' );
const {
	expectModalHiddenWithoutAriaModal,
	expectNamedDialog,
} = require( './support/modal-accessibility' );

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
	await expect( page.locator( '#ModalSuperSearchInput' ) ).toBeFocused();

	await modal.locator( '.btn-close' ).click();
	await expectModalHiddenWithoutAriaModal( page, '#ModalSuperSearchBox' );
	await expect( launcher ).toBeFocused();
} );
