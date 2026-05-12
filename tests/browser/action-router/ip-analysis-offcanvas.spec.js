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
const {
	expectActiveInlineTabState,
	expectInlineTabsContract,
	expectKeyboardTabActivation,
	getInlineTabByIndex,
	getInlineTabByTableType,
} = require( './support/investigate-inline-tabs' );
const {
	collectRuntimeErrors,
	expectInvestigationTableInitialized,
	expectNoRuntimeErrors,
	expectRequestMetaPopover,
	investigationTableResponseMatcher,
	isAdminAjaxRequest,
	requestPostParam,
} = require( './support/security-assertions' );

const CONFIRM_DIALOG_SELECTOR = '[data-shield-accessible-dialog="1"]';
const CONFIRM_DIALOG_ACTIVE_SELECTOR = `${CONFIRM_DIALOG_SELECTOR}[aria-modal="true"]:not([aria-hidden="true"])`;
const CONFIRM_BUTTON_SELECTOR = '.shield-accessible-dialog__confirm';
const CANCEL_BUTTON_SELECTOR = '.shield-accessible-dialog__cancel';
const IP_ANALYSIS_CONFIRM_ACTION_SELECTOR = [
	'.ip_analyse_action[data-ip_action="block"]',
	'.ip_analyse_action[data-ip_action="bypass"]',
	'.ip_analyse_action[data-ip_action="reset_offenses"]',
	'.ip_analyse_action[data-ip_action="delete_notbot"]',
].join( ', ' );

const ipAnalysisOffcanvasRequestMatcher = ( request ) => {
	return isAdminAjaxRequest( request )
		&& requestPostParam( request, 'render_slug' ) === 'offcanvas_ipanalysis';
};

const isIpAnalysisActionRequest = ( request, expectedAction ) => {
	return isAdminAjaxRequest( request )
		&& requestPostParam( request, 'ip_action' ) === expectedAction;
};

const openIpAnalysisOffcanvasFromClick = async ( page, ip ) => {
	await openShieldRoute( page, {
		nav: 'activity',
		nav_sub: 'logs',
	} );

	const trigger = page.locator( `.offcanvas_ip_analysis[data-ip="${ip}"]` ).first();
	await expect( trigger ).toBeVisible();
	await Promise.all( [
		page.waitForResponse( ( response ) => ipAnalysisOffcanvasRequestMatcher( response.request() ) ),
		trigger.click(),
	] );

	const offcanvas = page.locator( '#AptoOffcanvas.show' );
	await expect( offcanvas ).toBeVisible();
	await expectNamedOffcanvas( page, offcanvas, 'AptoOffcanvasLabel' );

	return { offcanvas };
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

	return { offcanvas };
};

const reloadActiveInvestigationTable = async ( page, offcanvas, tableType ) => {
	const table = offcanvas.locator( `table[data-investigation-table="1"][data-table-type="${tableType}"]` ).first();
	await expect( table ).toBeVisible();

	await Promise.all( [
		page.waitForResponse( investigationTableResponseMatcher( tableType ) ),
		table.evaluate( ( element ) => {
			globalThis.jQuery( element ).DataTable().ajax.reload( null, false );
		} ),
	] );

	await expect.poll( async () => {
		return table.evaluate( ( element ) => {
			const container = element.closest( '[aria-busy]' );
			return container?.getAttribute( 'aria-busy' ) === 'true' ? 'busy' : 'ready';
		} );
	}, { timeout: 20_000 } ).toBe( 'ready' );
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

		const { offcanvas } = await openIpAnalysisOffcanvasFromClick( page, fixture.ip );
		await page.unroute( '**/admin-ajax.php*', delayHandler ).catch( () => null );
		await expectNamedOffcanvas( page, offcanvas, 'AptoOffcanvasLabel' );
		await expectInlineTabsContract( offcanvas );

		const targetTab = await getInlineTabByTableType( offcanvas, 'sessions' );
		await targetTab.click();

		await expectActiveInlineTabState( offcanvas, targetTab );
	} );
} );

test( 'IP analysis actions use accessible confirm without native dialog', async ( { page } ) => {
	const { offcanvas } = await openIpAnalysisOffcanvas( page, '198.51.100.20' );
	const action = offcanvas.locator( IP_ANALYSIS_CONFIRM_ACTION_SELECTOR ).first();
	await expect( action ).toBeVisible();
	await expect( action ).toHaveRole( 'button' );
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
	await expectNamedDialog( page, confirmModal );
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
	await expectNamedDialog( page, confirmModal );
	await confirmModal.locator( CONFIRM_BUTTON_SELECTOR ).click();
	await actionRequest;

	await expect.poll( () => nativeDialogCount ).toBe( 0 );
	await page.waitForTimeout( 500 );
	expect( actionRequestCount ).toBe( requestsBeforeConfirm + 1 );
} );

test( 'preloaded IP analysis offcanvas loads investigation tables without runtime errors', async ( { page } ) => {
	const runtimeErrors = collectRuntimeErrors( page );

	const { offcanvas } = await openIpAnalysisOffcanvas( page, '198.51.100.20' );

	for ( const tableType of [ 'sessions', 'activity', 'traffic' ] ) {
		await expectInlineTabsContract( offcanvas );
		const targetTab = await getInlineTabByTableType( offcanvas, tableType );
		const tableResponsePromise = page.waitForResponse( investigationTableResponseMatcher( tableType ) );

		await targetTab.click();
		await tableResponsePromise;
		await expectActiveInlineTabState( offcanvas, targetTab );
		await expectInvestigationTableInitialized( offcanvas, tableType );
	}
	const activityTab = await getInlineTabByTableType( offcanvas, 'activity' );
	const trafficTab = await getInlineTabByTableType( offcanvas, 'traffic' );
	const firstTab = await getInlineTabByIndex( offcanvas, 0 );
	const lastTab = await getInlineTabByIndex( offcanvas, 3 );

	await expectKeyboardTabActivation( page, offcanvas, trafficTab, 'ArrowLeft', activityTab );
	await expectInvestigationTableInitialized( offcanvas, 'activity' );
	await expectKeyboardTabActivation( page, offcanvas, activityTab, 'ArrowRight', trafficTab );
	await expectInvestigationTableInitialized( offcanvas, 'traffic' );
	await expectKeyboardTabActivation( page, offcanvas, trafficTab, 'ArrowUp', activityTab );
	await expectInvestigationTableInitialized( offcanvas, 'activity' );
	await expectKeyboardTabActivation( page, offcanvas, activityTab, 'ArrowDown', trafficTab );
	await expectInvestigationTableInitialized( offcanvas, 'traffic' );
	await expectKeyboardTabActivation( page, offcanvas, trafficTab, 'Home', firstTab );
	await expectKeyboardTabActivation( page, offcanvas, firstTab, 'End', lastTab );
	await expectInvestigationTableInitialized( offcanvas, 'traffic' );

	await expectNoRuntimeErrors( runtimeErrors, 'Expected no browser runtime errors while loading IP analysis investigation tables' );
} );

test( 'preloaded IP analysis offcanvas activity meta button loads request meta popover', async ( { page, fixtureApi } ) => {
	const runtimeErrors = collectRuntimeErrors( page );

	await fixtureApi.withIpAnalysisActivityMetaFixture( async ( fixture ) => {
		const evidence = await fixtureApi.inspectIpAnalysisActivityMetaFixture();
		expect( evidence.request_log ).toMatchObject( {
			exists: true,
			rid: fixture.rid,
			ip: fixture.ip,
			path: '/fixture/ip-analysis-meta',
			verb: 'POST',
			code: 418,
			offense: false,
			meta: {
				ts: '1710000000',
				ua: 'IP Analysis Browser Fixture/1.0',
			},
		} );
		expect( evidence.activity_event ).toMatchObject( {
			exists: true,
			event_slug: 'user_login',
			request_rid: fixture.rid,
		} );

		const { offcanvas } = await openIpAnalysisOffcanvas( page, fixture.ip );
		await expectInlineTabsContract( offcanvas );
		const targetTab = await getInlineTabByTableType( offcanvas, 'activity' );

		await Promise.all( [
			page.waitForResponse( investigationTableResponseMatcher( 'activity' ) ),
			targetTab.click(),
		] );

		await expectActiveInlineTabState( offcanvas, targetTab );
		await expectInvestigationTableInitialized( offcanvas, 'activity' );
		await expectRequestMetaPopover( page, offcanvas, fixture.rid, fixture.expected_meta );
		await reloadActiveInvestigationTable( page, offcanvas, 'activity' );
		await expect( page.locator( '[role="tooltip"]' ) ).toHaveCount( 0 );
		await expectRequestMetaPopover( page, offcanvas, fixture.rid, fixture.expected_meta );
	} );

	await expectNoRuntimeErrors( runtimeErrors, 'Expected no browser runtime errors while opening the offcanvas request-meta popover' );
} );
