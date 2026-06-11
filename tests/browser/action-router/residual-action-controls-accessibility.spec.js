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

async function expectSubmitControlWithoutHref( locator ) {
	await expect( locator ).toBeVisible();
	await expect( locator ).toBeEnabled();
	await expect( locator ).toHaveAttribute( 'type', 'submit' );
	expect( await locator.getAttribute( 'href' ) ).toBeNull();
	expect( await locator.evaluate( ( element ) => element.form instanceof HTMLFormElement ) ).toBe( true );
}

async function expectNetworkSyncWorkbenchVisible( page ) {
	await expect( page.locator( '[data-import-export-workbench="1"]' ) ).toBeVisible( { timeout: 15_000 } );
	await expect( page.locator( '[data-import-export-network-disabled="1"]' ) ).toHaveCount( 0 );
}

async function expectNetworkSyncDisabledStateVisible( page ) {
	await expect( page.locator( '[data-import-export-network-disabled="1"]' ) ).toBeVisible( { timeout: 15_000 } );
	await expect( page.locator( '[data-import-export-workbench="1"]' ) ).toHaveCount( 0 );
	await expect( page.locator( '#ShieldTable-ImportExportSites' ) ).toHaveCount( 0 );
}

async function expectNamedDialog( page, modal ) {
	await expect( modal ).toHaveAttribute( 'role', 'dialog' );
	await expect( modal ).toHaveAttribute( 'aria-modal', 'true' );
	const labelID = await modal.getAttribute( 'aria-labelledby' );
	expect( labelID || '' ).not.toHaveLength( 0 );
	await expect( page.locator( `#${ labelID }` ) ).toHaveAccessibleName( /\S/ );
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

		await expect( page.locator( '[data-import-export-panel="file"]' ) ).toBeVisible();
		await expect( page.locator( '[data-import-export-sync-toggle]' ) ).toHaveCount( 0 );
		await expectSubmitControlWithoutHref(
			page.locator( '#ImportExportFileForm #SubmitForm[type="submit"]' )
		);
		await expectNoAxeViolations( page, '#SectionImportExportFile', [ 'heading-order' ] );
	} );
} );

test( 'network verification radios reveal and clear master key field', async ( { page, fixtureApi } ) => {
	await fixtureApi.withImportExportNetworkFixture( async () => {
		await openShieldRoute( page, {
			nav: 'tools',
			nav_sub: 'importexport',
		} );

		const networkPanel = page.locator( '[data-import-export-panel="network_sync"]' );
		const formPanel = page.locator( '[data-import-export-connect-form-panel="1"]' );
		const revealButton = page.locator( '[data-import-export-connect-reveal="1"]' );
		const secretField = page.locator( '[data-import-export-secret-field]' );
		const secretInput = page.locator( '#MasterSiteSecretKey' );
		const masterUrlInput = page.locator( '#MasterSiteUrl' );

		await expect( networkPanel ).toBeVisible();
		await expect( formPanel ).toBeHidden();
		await expect( revealButton ).toHaveAttribute( 'aria-expanded', 'false' );

		const unexpectedImportRequest = page.waitForRequest(
			( request ) => isShieldActionRequest( request, 'import_from_site' ),
			{ timeout: 750 }
		)
		.then( () => true )
		.catch( () => false );
		await revealButton.click();
		await expect( formPanel ).toBeVisible();
		await expect( revealButton ).toHaveAttribute( 'aria-expanded', 'true' );
		await expect( masterUrlInput ).toBeFocused();
		expect( await unexpectedImportRequest ).toBe( false );

		await expect( secretField ).toBeHidden();
		await expect( secretInput ).toBeDisabled();

		await page.locator( '[data-import-export-link-choice][value="Y"]' ).check();
		await expect( page.locator( '[data-import-export-link-choice][value="Y"]' ) ).toBeChecked();

		await page.locator( '[data-import-export-auth-choice="key"]' ).check();
		await expect( secretField ).toBeVisible();
		await expect( secretInput ).toBeEnabled();
		await secretInput.fill( 'master-secret-fixture' );

		await page.locator( '[data-import-export-auth-choice="trusted"]' ).check();
		await expect( secretField ).toBeHidden();
		await expect( secretInput ).toBeDisabled();
		await expect( secretInput ).toHaveValue( '' );
		await expectNoAxeViolations( page, '#SectionImportExportNetworkSync', [ 'heading-order' ] );
	} );
} );

test( 'connected master sync-now button sends existing import request', async ( { page, fixtureApi } ) => {
	await fixtureApi.withImportExportNetworkFixture( async () => {
		await openShieldRoute( page, {
			nav: 'tools',
			nav_sub: 'importexport',
		} );

		const connectedState = page.locator( '[data-import-export-connected-master="1"]' );
		const syncButton = page.locator( '[data-import-export-sync-now="1"]' );

		await expect( connectedState ).toBeVisible( { timeout: 15_000 } );
		await expectActionButton( syncButton );
		await expect( syncButton ).toHaveText( /Sync settings now/ );

		await page.route( '**/admin-ajax.php*', async ( route ) => {
			const request = route.request();
			if ( isShieldActionRequest( request, 'import_from_site' ) ) {
				await route.fulfill( {
					status: 200,
					contentType: 'application/json',
					body: JSON.stringify( {
						success: true,
						data: {
							message: '',
							page_reload: false,
							show_toast: false,
						},
					} ),
				} );
				return;
			}

			await route.continue();
		} );

		const syncRequest = page.waitForRequest( ( request ) => {
			if ( !isShieldActionRequest( request, 'import_from_site' ) ) {
				return false;
			}

			const params = requestParams( request );
			return params.get( 'form_params[confirm]' ) === 'Y'
				&& params.get( 'form_params[MasterSiteUrl]' ) === ''
				&& params.get( 'form_params[MasterSiteSecretKey]' ) === ''
				&& params.get( 'form_params[ShieldNetwork]' ) === 'NC';
		}, { timeout: 20_000 } );

		await syncButton.click();
		await syncRequest;
		await expect( syncButton ).toBeEnabled( { timeout: 15_000 } );
		await expectNoAxeViolations( page, '#SectionImportExportNetworkSync', [ 'heading-order' ] );
	}, [ 'connected-master' ] );
} );

test( 'network sync toggle switches the pro workbench off and on', async ( { page, fixtureApi } ) => {
	await fixtureApi.withImportExportNetworkFixture( async () => {
		await openShieldRoute( page, {
			nav: 'tools',
			nav_sub: 'importexport',
		} );

		const toggle = page.locator( '[data-import-export-sync-toggle]' );

		await expect( page.locator( '[data-import-export-panel="network_sync"]' ) ).toBeVisible();
		await expect( toggle ).toBeChecked();
		await expectNetworkSyncWorkbenchVisible( page );

		const disableRequest = page.waitForRequest(
			( request ) => isShieldActionRequest( request, 'importexport_set_enabled', { enabled: 'N' } ),
			{ timeout: 20_000 }
		);
		await toggle.uncheck();
		await disableRequest;

		await expect( toggle ).not.toBeChecked( { timeout: 15_000 } );
		await expectNetworkSyncDisabledStateVisible( page );

		const enableRequest = page.waitForRequest(
			( request ) => isShieldActionRequest( request, 'importexport_set_enabled', { enabled: 'Y' } ),
			{ timeout: 20_000 }
		);
		await toggle.check();
		await enableRequest;

		await expect( toggle ).toBeChecked( { timeout: 15_000 } );
		await expectNetworkSyncWorkbenchVisible( page );
	} );
} );
