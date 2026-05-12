const { AxeBuilder, buildShieldUrl, test, expect, openShieldRoute } = require( './support/shield-test' );
const { expectModalHiddenWithoutAriaModal } = require( './support/modal-accessibility' );

function requestParams( request ) {
	return new URLSearchParams( request.postData() || '' );
}

function isShieldActionRequest( request, executeSlug, expectedPayload = {} ) {
	if ( request.method() !== 'POST' || !request.url().includes( '/admin-ajax.php' ) ) {
		return false;
	}

	const params = requestParams( request );
	if ( params.get( 'action' ) !== 'shield_action' || params.get( 'ex' ) !== executeSlug ) {
		return false;
	}

	return Object.entries( expectedPayload ).every( ( [ key, value ] ) => params.get( key ) === String( value ) );
}

function installNativeDialogGuard( page ) {
	const nativeDialogs = [];
	page.on( 'dialog', async ( dialog ) => {
		nativeDialogs.push( dialog.type() );
		await dialog.dismiss().catch( () => null );
	} );
	return nativeDialogs;
}

function formatAxeViolations( violations ) {
	return violations.map( ( violation ) => {
		const targets = violation.nodes
		.flatMap( ( node ) => node.target || [] )
		.slice( 0, 5 )
		.join( ', ' );

		return `${ violation.id }: ${ targets }`;
	} ).join( '\n' );
}

async function expectNoAxeViolations( page, selector, disabledRules = [] ) {
	let builder = new AxeBuilder( { page } )
	.include( selector );
	if ( disabledRules.length > 0 ) {
		builder = builder.disableRules( disabledRules );
	}
	const results = await builder.analyze();

	expect( results.violations, formatAxeViolations( results.violations ) ).toEqual( [] );
}

async function expectActionButton( locator ) {
	await expect( locator ).toBeVisible( { timeout: 20_000 } );
	await expect( locator ).toHaveRole( 'button' );
	await expect( locator ).toHaveAttribute( 'type', 'button' );
	expect( await locator.getAttribute( 'href' ) ).toBeNull();
}

async function expectSubmitInputWithoutHref( locator ) {
	await expect( locator ).toBeVisible();
	await expect( locator ).toBeEnabled();
	await expect( locator ).toHaveAttribute( 'type', 'submit' );
	expect( await locator.getAttribute( 'href' ) ).toBeNull();
	expect( await locator.evaluate( ( element ) => element.form instanceof HTMLFormElement ) ).toBe( true );
}

async function expectNamedDialog( page, modal ) {
	await expect( modal ).toHaveAttribute( 'role', 'dialog' );
	await expect( modal ).toHaveAttribute( 'aria-modal', 'true' );
	const labelID = await modal.getAttribute( 'aria-labelledby' );
	expect( labelID || '' ).not.toHaveLength( 0 );
	expect( await page.locator( `#${ labelID }` ).evaluate(
		( node ) => node.isConnected && ( node.textContent || '' ).trim().length > 0
	) ).toBe( true );
}

async function visibleMerlinStepId( page ) {
	return page.locator( '#merlin .wizard-step-pane:not(.d-none)' )
	.evaluate( ( element ) => element.id );
}

test( 'debug contextual action controls are buttons and keep purge and print behavior', async ( { page } ) => {
	const nativeDialogs = installNativeDialogGuard( page );
	await openShieldRoute( page, {
		nav: 'tools',
		nav_sub: 'debug',
	} );
	const originalURL = page.url();

	await page.locator( '.page-action-menu-toggle' ).click();
	const purgeAction = page.locator( 'button.tool_purge_provider_ips.dropdown-item' );
	const printAction = page.locator( 'button.shield_div_print.dropdown-item' );
	await expectActionButton( purgeAction );
	await expectActionButton( printAction );
	await expectNoAxeViolations( page, '.inner-page-header .dropdown-menu' );

	const purgeRequest = page.waitForRequest(
		( request ) => isShieldActionRequest( request, 'tool_purge_provider_ips' ),
		{ timeout: 20_000 }
	);
	await purgeAction.click();
	await purgeRequest;
	expect( page.url() ).toBe( originalURL );

	await page.locator( '.page-action-menu-toggle' ).click();
	await page.evaluate( () => {
		window.__shieldPrintProbe = {
			html: '',
			prints: 0,
			writes: 0,
		};
		window.open = () => ( {
			document: {
				write: ( html ) => {
					window.__shieldPrintProbe.html = html;
					window.__shieldPrintProbe.writes++;
				},
			},
			print: () => {
				window.__shieldPrintProbe.prints++;
			},
		} );
	} );
	await printAction.click();
	await expect.poll( () => page.evaluate( () => window.__shieldPrintProbe.prints ) ).toBe( 1 );
	await expect.poll( () => page.evaluate( () => window.__shieldPrintProbe.writes ) ).toBe( 1 );
	expect( page.url() ).toBe( originalURL );
	expect( nativeDialogs ).toEqual( [] );
} );

test( 'IP rules delete button opens accessible confirm and sends the stable rule payload', async ( { page, fixtureApi } ) => {
	const nativeDialogs = installNativeDialogGuard( page );
	await fixtureApi.withIpRulesTableFixture( async ( fixture ) => {
		await openShieldRoute( page, {
			nav: 'ips',
			nav_sub: 'rules',
		} );

		const deleteAction = page.locator(
			`#ShieldTable-IpRules td.ip_linked button.ip_delete[data-rid="${ fixture.rule_id }"]`
		).first();
		await expectActionButton( deleteAction );

		await deleteAction.focus();
		await page.keyboard.press( 'Enter' );
		const confirmModal = page.locator( '[data-shield-accessible-dialog="1"][aria-modal="true"]:not([aria-hidden="true"])' );
		await expect( confirmModal ).toBeVisible();
		await expectNamedDialog( page, confirmModal );
		await expectNoAxeViolations( page, '[data-shield-accessible-dialog="1"]' );

		await confirmModal.locator( '.shield-accessible-dialog__cancel' ).click();
		await expectModalHiddenWithoutAriaModal( page, '[data-shield-accessible-dialog="1"]' );
		await expect( deleteAction ).toBeFocused();

		const deleteRequest = page.waitForRequest(
			( request ) => isShieldActionRequest( request, 'ip_rule_delete', { rid: fixture.rule_id } ),
			{ timeout: 20_000 }
		);
		await deleteAction.click();
		await expect( confirmModal ).toBeVisible();
		await expectNamedDialog( page, confirmModal );
		await confirmModal.locator( '.shield-accessible-dialog__confirm' ).click();
		await deleteRequest;

		expect( nativeDialogs ).toEqual( [] );
	} );
} );

test( 'Merlin skip-step button advances the wizard by keyboard and click', async ( { page, fixtureApi } ) => {
	await fixtureApi.withMerlinWelcomeFixture( async () => {
		await page.goto( buildShieldUrl( {
			nav: 'merlin',
			nav_sub: 'welcome',
		} ), { waitUntil: 'load' } );
		await expect( page.locator( '#MerlinOverlay' ) ).toBeVisible();

		const licensePane = page.locator( '#step-license' );
		await page.locator( '#merlin .merlin-next' ).click();
		await expect( licensePane ).toBeVisible();
		await expect.poll( () => visibleMerlinStepId( page ) ).toBe( 'step-license' );

		const skipAction = licensePane.locator( 'button.skip-step' );
		await expectActionButton( skipAction );
		await skipAction.focus();
		await page.keyboard.press( 'Enter' );
		await expect.poll( () => visibleMerlinStepId( page ) ).not.toBe( 'step-license' );
		const keyboardNextStep = await visibleMerlinStepId( page );
		expect( keyboardNextStep ).toMatch( /^step-/ );

		await page.locator( '#merlin .merlin-prev' ).click();
		await expect( licensePane ).toBeVisible();
		await skipAction.click();
		await expect.poll( () => visibleMerlinStepId( page ) ).toBe( keyboardNextStep );
		await expectNoAxeViolations( page, '#MerlinOverlay', [ 'heading-order' ] );
	} );
} );

test( 'import file submit control stays usable without an invalid href', async ( { page, fixtureApi } ) => {
	await fixtureApi.withImportExportFileFixture( async () => {
		await openShieldRoute( page, {
			nav: 'tools',
			nav_sub: 'importexport',
		} );

		await page.locator( 'a[data-bs-toggle="tab"][href="#byFile"]' ).click();
		await expect( page.locator( '#byFile' ) ).toBeVisible();
		await expectSubmitInputWithoutHref(
			page.locator( '#ImportExportFileForm input#SubmitForm[type="submit"]' )
		);
		await expectNoAxeViolations( page, '#SectionImportExportFile', [ 'heading-order' ] );
	} );
} );
