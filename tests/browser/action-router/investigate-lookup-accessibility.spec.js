const { test, expect, openShieldRoute } = require( './support/shield-test' );
const {
	expectReferenceTargetNonEmpty,
	getIdReferenceTokens,
} = require( './support/modal-accessibility' );

const panelSelector = '[data-investigate-panel="1"]';

const lookupFieldIds = ( subject, inputName ) => {
	const base = `shield-investigate-${subject}-lookup-${inputName}`;
	return {
		control: `${base}-control`,
		label: `${base}-label`,
		helper: `${base}-helper`,
	};
};

// A subject's own panel, with its picker, opens by link, as pivots and older links do.
const openInvestigatePanel = async ( page, subject ) => {
	await openShieldRoute( page, {
		nav: 'activity',
		nav_sub: 'overview',
		subject,
	} );

	const panel = page.locator( `${panelSelector}[data-investigate-panel-subject="${subject}"]` ).first();
	await expect( panel ).toHaveAttribute( 'data-investigate-panel-loaded', '1' );
	return panel;
};

const expectConnectedIdReferences = async ( page, locator, attribute ) => {
	const ids = await getIdReferenceTokens( locator, attribute );
	expect( ids.length ).toBeGreaterThan( 0 );

	for ( const id of ids ) {
		await expectReferenceTargetNonEmpty( page, id );
	}

	return ids;
};

const expectLookupAccessibilityContract = async ( page, root, { subject, inputName } ) => {
	const ids = lookupFieldIds( subject, inputName );
	const select = root.locator( `select[name="${inputName}"]` ).first();
	await expect( select ).toBeAttached();
	await expect( select ).toHaveAttribute( 'id', ids.control );
	await expect( select ).toHaveAttribute( 'aria-describedby', ids.helper );
	await expect( select ).toHaveAttribute( 'data-investigate-select2-label', ids.label );
	await expect( select ).toHaveAttribute( 'data-investigate-select2-description', ids.helper );

	await expect( root.locator( `#${ids.label}` ) ).toHaveAttribute( 'for', ids.control );
	await expectReferenceTargetNonEmpty( page, ids.label );
	await expectReferenceTargetNonEmpty( page, ids.helper );

	const select2Container = select.locator( 'xpath=following-sibling::*[1]' ).first();
	await expect( select2Container ).toBeVisible();

	const visibleSelection = select2Container.locator( '[role="combobox"]' ).first();
	await expect( visibleSelection ).toBeVisible();

	const labelReferences = await expectConnectedIdReferences( page, visibleSelection, 'aria-labelledby' );
	expect( labelReferences.includes( ids.label ) ).toBe( true );
	expect( labelReferences.some( ( id ) => id !== ids.label ) ).toBe( true );

	const descriptionReferences = await expectConnectedIdReferences( page, visibleSelection, 'aria-describedby' );
	expect( descriptionReferences.includes( ids.helper ) ).toBe( true );
};

test( 'investigate lookup fields connect producer labels and descriptions to Select2 controls', async ( { page } ) => {
	const scenarios = [
		{ subject: 'ip', inputName: 'analyse_ip' },
		{ subject: 'user', inputName: 'user_lookup' },
		{ subject: 'plugin', inputName: 'plugin_slug' },
	];

	for ( const scenario of scenarios ) {
		const panel = await openInvestigatePanel( page, scenario.subject );
		await expectLookupAccessibilityContract( page, panel, scenario );
	}
} );
