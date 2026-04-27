const { expect } = require( '@playwright/test' );

async function expectNamedDialog( page, modal, expectedLabelId = null ) {
	await expect( modal ).toHaveAttribute( 'role', 'dialog' );
	await expect( modal ).toHaveAttribute( 'aria-modal', 'true' );
	const labelId = await expectConnectedNonEmptyReference( page, modal, 'aria-labelledby' );
	if ( expectedLabelId !== null ) {
		expect( labelId ).toBe( expectedLabelId );
	}
}

async function expectNamedOffcanvas( page, offcanvas, expectedLabelId = null ) {
	const labelId = await expectConnectedNonEmptyReference( page, offcanvas, 'aria-labelledby' );
	if ( expectedLabelId !== null ) {
		expect( labelId ).toBe( expectedLabelId );
	}
}

async function expectOptionalDescription( page, dialog ) {
	const descriptionId = await dialog.getAttribute( 'aria-describedby' );
	if ( descriptionId === null || descriptionId.length < 1 ) {
		return null;
	}
	await expectReferenceTargetNonEmpty( page, descriptionId );
	return descriptionId;
}

async function expectConnectedNonEmptyReference( page, element, attribute ) {
	const referenceId = await element.getAttribute( attribute );
	expect( referenceId || '' ).not.toHaveLength( 0 );
	await expectReferenceTargetNonEmpty( page, referenceId );
	return referenceId;
}

async function expectReferenceTargetNonEmpty( page, referenceId ) {
	expect( await page.locator( `#${referenceId}` ).evaluate( ( node ) => node.isConnected && ( node.textContent || '' ).trim().length > 0 ) ).toBe( true );
}

async function expectModalHiddenWithoutAriaModal( page, modalSelector ) {
	await expect( page.locator( modalSelector ) ).not.toHaveAttribute( 'aria-modal', 'true' );
}

module.exports = {
	expectModalHiddenWithoutAriaModal,
	expectNamedDialog,
	expectNamedOffcanvas,
	expectOptionalDescription,
};
