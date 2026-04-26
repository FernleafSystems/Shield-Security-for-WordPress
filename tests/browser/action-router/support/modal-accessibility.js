const { expect } = require( '@playwright/test' );

async function expectNamedDialog( page, modal, expectedLabelId = null ) {
	await expect( modal ).toHaveAttribute( 'role', 'dialog' );
	await expect( modal ).toHaveAttribute( 'aria-modal', 'true' );
	const labelId = await modal.getAttribute( 'aria-labelledby' );
	expect( labelId || '' ).not.toHaveLength( 0 );
	if ( expectedLabelId !== null ) {
		expect( labelId ).toBe( expectedLabelId );
	}
	expect( await page.locator( `#${labelId}` ).evaluate( ( node ) => node.isConnected && ( node.textContent || '' ).trim().length > 0 ) ).toBe( true );
}

async function expectModalHiddenWithoutAriaModal( page, modalSelector ) {
	await expect( page.locator( modalSelector ) ).not.toHaveAttribute( 'aria-modal', 'true' );
}

module.exports = {
	expectModalHiddenWithoutAriaModal,
	expectNamedDialog,
};
