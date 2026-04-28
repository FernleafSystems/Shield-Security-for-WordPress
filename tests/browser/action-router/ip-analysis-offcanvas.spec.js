const { test, expect } = require( './support/shield-test' );
const {
	openShieldRoute,
} = require( './support/shield-browser' );
const {
	expectModalHiddenWithoutAriaModal,
	expectNamedDialog,
	expectNamedOffcanvas,
	expectOptionalDescription,
} = require( './support/modal-accessibility' );

const CONFIRM_DIALOG_SELECTOR = '#AptoGeneralPurposeDialog';
const CONFIRM_DIALOG_ACTIVE_SELECTOR = `${CONFIRM_DIALOG_SELECTOR}.modal.show[aria-modal="true"]`;
const CONFIRM_BUTTON_SELECTOR = '[data-shield-dialog-confirm="1"]';
const CANCEL_BUTTON_SELECTOR = '[data-shield-dialog-cancel="1"]';
const IP_ANALYSIS_CONFIRM_ACTION_SELECTOR = [
	'.ip_analyse_action[data-ip_action="block"]',
	'.ip_analyse_action[data-ip_action="bypass"]',
	'.ip_analyse_action[data-ip_action="reset_offenses"]',
	'.ip_analyse_action[data-ip_action="delete_notbot"]',
].join( ', ' );

const investigationTableRequestMatcher = ( tableType ) => ( response ) => {
	if ( !response.url().includes( '/admin-ajax.php' ) ) {
		return false;
	}

	const request = response.request();
	const postData = request.postData() || '';

	return request.method() === 'POST'
		&& postData.includes( 'sub_action=retrieve_table_data' )
		&& postData.includes( `table_type=${tableType}` );
};

const requestMetaResponseMatcher = ( rid ) => ( response ) => {
	if ( !response.url().includes( '/admin-ajax.php' ) ) {
		return false;
	}

	const request = response.request();
	const postData = request.postData() || '';

	return request.method() === 'POST'
		&& postData.includes( 'sub_action=get_request_meta' )
		&& postData.includes( `rid=${rid}` );
};

const ipAnalysisOffcanvasRequestMatcher = ( request ) => {
	if ( !request.url().includes( '/admin-ajax.php' ) ) {
		return false;
	}

	const postData = request.postData() || '';
	return request.method() === 'POST' && postData.includes( 'render_slug=offcanvas_ipanalysis' );
};

const isIpAnalysisActionRequest = ( request, expectedAction ) => {
	if ( !request.url().includes( '/admin-ajax.php' ) || request.method() !== 'POST' ) {
		return false;
	}

	const params = new URLSearchParams( request.postData() || '' );
	return params.get( 'ip_action' ) === expectedAction;
};

const investigationTabLabels = {
	sessions: 'User Sessions',
	activity: 'Activity Log',
	traffic: 'Recent Traffic',
};

const openIpAnalysisOffcanvasFromClick = async ( page, ip ) => {
	await openShieldRoute( page, {
		nav: 'activity',
		nav_sub: 'logs',
	} );

	const trigger = page.locator( `.offcanvas_ip_analysis[data-ip="${ip}"]` ).first();
	await expect( trigger ).toBeVisible();
	await trigger.click();

	const offcanvas = page.locator( '#AptoOffcanvas.show' );
	await expect( offcanvas ).toBeVisible();
	await expectNamedOffcanvas( page, offcanvas, 'AptoOffcanvasLabel' );

	const inlineTabs = offcanvas.locator( '[data-investigate-panel-tabs="1"] [data-investigate-panel-tab="1"]' );
	await expect( inlineTabs ).toHaveCount( 4 );

	return { offcanvas, inlineTabs };
};

const openIpAnalysisOffcanvas = async ( page, ip ) => {
	await openShieldRoute( page, {
		nav: 'ips',
		nav_sub: 'rules',
		analyse_ip: ip,
	} );

	const offcanvas = page.locator( '#AptoOffcanvas.show' );
	await expect( offcanvas ).toBeVisible();
	await expectNamedOffcanvas( page, offcanvas, 'AptoOffcanvasLabel' );

	const inlineTabs = offcanvas.locator( '[data-investigate-panel-tabs="1"] [data-investigate-panel-tab="1"]' );
	await expect( inlineTabs ).toHaveCount( 4 );

	return { offcanvas, inlineTabs };
};

const getInvestigationTab = ( inlineTabs, tableType ) => inlineTabs.filter( {
	hasText: investigationTabLabels[ tableType ],
} ).first();

const expectInvestigationTableInitialized = async ( offcanvas, tableType ) => {
	const table = offcanvas.locator( `.tab-pane.active.show table[data-investigation-table="1"][data-table-type="${tableType}"]` ).first();
	await expect( table ).toBeVisible();
	await expect.poll(
		async () => table.evaluate( ( el ) => {
			return !!globalThis.jQuery?.fn?.dataTable?.isDataTable?.( el );
		} ),
		{
			message: `Expected ${tableType} investigation table to be initialized by DataTables.`,
		}
	).toBe( true );
};

const expectRequestMetaPopover = async ( page, offcanvas, rid, expectedMeta ) => {
	const metaButton = offcanvas.locator( '.tab-pane.active.show td.meta > button[data-toggle="popover"]' ).first();
	await expect( metaButton ).toBeVisible();

	await Promise.all( [
		page.waitForResponse( requestMetaResponseMatcher( rid ) ),
		metaButton.click(),
	] );

	const popoverBody = offcanvas.locator( '.popover.show .popover-body' ).last();
	await expect( popoverBody ).toBeVisible();

	for ( const marker of expectedMeta ) {
		await expect( popoverBody ).toContainText( marker );
	}
};

test( 'clicked IP link opens the IP analysis offcanvas with the four investigation tabs', async ( { page, fixtureApi } ) => {
	await fixtureApi.withIpAnalysisActivityMetaFixture( async ( fixture ) => {
		let delayedOffcanvasRequest = false;
		const delayHandler = async ( route ) => {
			if ( !delayedOffcanvasRequest && ipAnalysisOffcanvasRequestMatcher( route.request() ) ) {
				delayedOffcanvasRequest = true;
				await new Promise( ( resolve ) => setTimeout( resolve, 600 ) );
			}
			await route.continue();
		};
		await page.route( '**/admin-ajax.php*', delayHandler );

		const { offcanvas, inlineTabs } = await openIpAnalysisOffcanvasFromClick( page, fixture.ip );
		await page.unroute( '**/admin-ajax.php*', delayHandler ).catch( () => null );
		await expectNamedOffcanvas( page, offcanvas, 'AptoOffcanvasLabel' );
		await expect( inlineTabs ).toHaveText( [ 'Overview', 'User Sessions', 'Activity Log', 'Recent Traffic' ] );

		const targetTab = getInvestigationTab( inlineTabs, 'sessions' );
		const targetLabel = await targetTab.textContent();
		await targetTab.click();

		await expect( targetTab ).toHaveClass( /is-active/ );
		await expect(
			offcanvas.locator( '.shield-options-rail-nav .nav-link.active' )
		).toContainText( targetLabel ? targetLabel.trim() : '' );
	} );
} );

test( 'IP analysis actions use accessible confirm without native dialog', async ( { page } ) => {
	const { offcanvas } = await openIpAnalysisOffcanvas( page, '198.51.100.20' );
	const action = offcanvas.locator( IP_ANALYSIS_CONFIRM_ACTION_SELECTOR ).first();
	await expect( action ).toBeVisible();
	const expectedAction = await action.getAttribute( 'data-ip_action' );
	expect( expectedAction || '' ).not.toHaveLength( 0 );

	let nativeDialogCount = 0;
	let actionRequestCount = 0;
	page.on( 'dialog', async ( dialog ) => {
		nativeDialogCount++;
		await dialog.dismiss();
	} );
	page.on( 'request', ( request ) => {
		if ( isIpAnalysisActionRequest( request, expectedAction ) ) {
			actionRequestCount++;
		}
	} );

	await action.click();

	const confirmModal = page.locator( CONFIRM_DIALOG_ACTIVE_SELECTOR );
	await expect( confirmModal ).toBeVisible();
	await expectNamedDialog( page, confirmModal, 'AptoGeneralPurposeDialogTitle' );
	await expectOptionalDescription( page, confirmModal );

	await confirmModal.locator( CANCEL_BUTTON_SELECTOR ).click();
	await expectModalHiddenWithoutAriaModal( page, CONFIRM_DIALOG_SELECTOR );
	if ( await action.evaluate( ( element ) => element.isConnected ) ) {
		await expect( action ).toBeFocused();
	}
	await expect.poll( () => nativeDialogCount ).toBe( 0 );
	await page.waitForTimeout( 500 );
	expect( actionRequestCount ).toBe( 0 );

	const requestsBeforeConfirm = actionRequestCount;
	const actionRequest = page.waitForRequest(
		( request ) => isIpAnalysisActionRequest( request, expectedAction ),
		{ timeout: 20_000 }
	);
	await action.evaluate( ( element ) => {
		element.click();
		element.click();
	} );

	await expect( confirmModal ).toHaveCount( 1 );
	await expect( confirmModal ).toBeVisible();
	await expectNamedDialog( page, confirmModal, 'AptoGeneralPurposeDialogTitle' );
	await confirmModal.locator( CONFIRM_BUTTON_SELECTOR ).click();
	await actionRequest;

	await expect.poll( () => nativeDialogCount ).toBe( 0 );
	await page.waitForTimeout( 500 );
	expect( actionRequestCount ).toBe( requestsBeforeConfirm + 1 );
} );

test( 'preloaded IP analysis offcanvas loads investigation tables without runtime errors', async ( { page } ) => {
	const pageErrors = [];
	page.on( 'pageerror', ( error ) => {
		pageErrors.push( error.message );
	} );

	const { offcanvas, inlineTabs } = await openIpAnalysisOffcanvas( page, '198.51.100.20' );

	for ( const tableType of [ 'sessions', 'activity', 'traffic' ] ) {
		const targetTab = getInvestigationTab( inlineTabs, tableType );
		const tableResponsePromise = page.waitForResponse( investigationTableRequestMatcher( tableType ) );

		await targetTab.click();
		await tableResponsePromise;
		await expect( targetTab ).toHaveClass( /is-active/ );
		await expectInvestigationTableInitialized( offcanvas, tableType );
	}

	await expect.poll(
		() => pageErrors,
		{ message: `Expected no browser runtime errors while loading IP analysis investigation tables: ${pageErrors.join( '; ' )}` }
	).toEqual( [] );
} );

test( 'preloaded IP analysis offcanvas activity meta button loads request meta popover', async ( { page, fixtureApi } ) => {
	const pageErrors = [];
	page.on( 'pageerror', ( error ) => {
		pageErrors.push( error.message );
	} );

	await fixtureApi.withIpAnalysisActivityMetaFixture( async ( fixture ) => {
		const { offcanvas, inlineTabs } = await openIpAnalysisOffcanvas( page, fixture.ip );
		const targetTab = getInvestigationTab( inlineTabs, 'activity' );

		await Promise.all( [
			page.waitForResponse( investigationTableRequestMatcher( 'activity' ) ),
			targetTab.click(),
		] );

		await expect( targetTab ).toHaveClass( /is-active/ );
		await expectInvestigationTableInitialized( offcanvas, 'activity' );
		await expectRequestMetaPopover( page, offcanvas, fixture.rid, fixture.expected_meta );
	} );

	await expect.poll(
		() => pageErrors,
		{ message: `Expected no browser runtime errors while opening the offcanvas request-meta popover: ${pageErrors.join( '; ' )}` }
	).toEqual( [] );
} );
