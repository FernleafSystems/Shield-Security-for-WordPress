const { test, expect } = require( '@playwright/test' );
const {
	openShieldRoute,
	withIpAnalysisActivityMetaFixture,
} = require( './support/shield-browser' );
const { expectNamedOffcanvas } = require( './support/modal-accessibility' );

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

test( 'clicked IP link opens the IP analysis offcanvas with the four investigation tabs', async ( { page } ) => {
	await withIpAnalysisActivityMetaFixture( async ( fixture ) => {
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

test( 'preloaded IP analysis offcanvas activity meta button loads request meta popover', async ( { page } ) => {
	const pageErrors = [];
	page.on( 'pageerror', ( error ) => {
		pageErrors.push( error.message );
	} );

	await withIpAnalysisActivityMetaFixture( async ( fixture ) => {
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
