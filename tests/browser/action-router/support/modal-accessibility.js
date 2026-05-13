const { expect } = require( './shield-test' );

async function expectNamedDialog( page, modal, expectedLabelId = null ) {
	await expect( modal ).toHaveAttribute( 'role', 'dialog' );
	await expect( modal ).toHaveAttribute( 'aria-modal', 'true' );
	const labelId = await expectConnectedNonEmptyReference( page, modal, 'aria-labelledby' );
	if ( expectedLabelId !== null ) {
		expect( labelId ).toBe( expectedLabelId );
	}
}

async function expectNamedOffcanvas( page, offcanvas, expectedLabelId = null ) {
	await expect( offcanvas ).toHaveAttribute( 'role', 'dialog' );
	await expect( offcanvas ).toHaveAttribute( 'aria-modal', 'true' );
	const labelId = await expectConnectedNonEmptyReference( page, offcanvas, 'aria-labelledby' );
	if ( expectedLabelId !== null ) {
		expect( labelId ).toBe( expectedLabelId );
	}
}

async function expectAccessibleMessageDialog( page ) {
	const dialog = page.locator( '[data-shield-accessible-dialog="1"][aria-modal="true"]:not([aria-hidden="true"])' );
	await expect( dialog ).toBeVisible();
	await expectNamedDialog( page, dialog );
	await expectOptionalDescription( page, dialog );
	await expectFocusWithin( dialog );
	return dialog;
}

async function expectLabelledControl( control ) {
	await expect( control ).toBeVisible();
	const label = await control.getAttribute( 'aria-label' );
	expect( label || '' ).not.toHaveLength( 0 );
}

async function expectFocusWithin( element ) {
	await expect.poll( async () => element.evaluate( ( node ) => node.contains( document.activeElement ) ) ).toBe( true );
}

async function expectOptionalDescription( page, dialog ) {
	const descriptionId = await dialog.getAttribute( 'aria-describedby' );
	if ( descriptionId === null || descriptionId.length < 1 ) {
		return null;
	}
	return expectConnectedNonEmptyReference( page, dialog, 'aria-describedby' );
}

async function expectConnectedNonEmptyReference( page, element, attribute ) {
	const referenceIds = await getIdReferenceTokens( element, attribute );
	expect( referenceIds.length ).toBeGreaterThan( 0 );
	for ( const referenceId of referenceIds ) {
		await expectReferenceTargetNonEmpty( page, referenceId );
	}
	return referenceIds.length === 1 ? referenceIds[ 0 ] : referenceIds;
}

async function expectReferenceTargetNonEmpty( page, referenceId ) {
	const reference = page.locator( `#${referenceId}` );
	await expect( reference ).toHaveCount( 1 );
	await expect( reference ).not.toHaveAttribute( 'aria-hidden', 'true' );
}

const getIdReferenceTokens = async ( locator, attribute ) => locator.evaluate(
	( element, currentAttribute ) => String( element.getAttribute( currentAttribute ) || '' )
		.split( /\s+/ )
		.filter( ( id ) => id.length > 0 ),
	attribute
);

async function expectModalHiddenWithoutAriaModal( page, modalSelector ) {
	await expect( page.locator( `${modalSelector}[aria-modal="true"]:not([aria-hidden="true"])` ) ).toHaveCount( 0 );
}

module.exports = {
	expectAccessibleMessageDialog,
	expectFocusWithin,
	expectConnectedNonEmptyReference,
	expectLabelledControl,
	expectModalHiddenWithoutAriaModal,
	expectNamedDialog,
	expectNamedOffcanvas,
	expectOptionalDescription,
	expectReferenceTargetNonEmpty,
	getIdReferenceTokens,
};
