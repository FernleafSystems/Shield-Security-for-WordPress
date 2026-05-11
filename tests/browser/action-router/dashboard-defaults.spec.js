const { test, expect } = require( './support/shield-test' );
const { dismissBlockingDialogs, openShieldRoute } = require( './support/shield-browser' );
const {
	collectRuntimeErrors,
	expectNoRuntimeErrors,
	expectShieldAjaxSuccess,
	expectStatusLiveRegion,
	isAjaxRenderRequest,
	isShieldAjaxBatchRequestWithRenderSlugs,
	requestBatchRenderSlugs,
	setDashboardLiveMonitorCollapsed,
	waitForShieldAjaxAction,
} = require( './support/security-assertions' );

test.setTimeout( 180_000 );

function assertDashboardDefaultsContract( contract ) {
	expect( contract ).toEqual( expect.objectContaining( {
		routes: expect.any( Object ),
		configure_focus: expect.any( Object ),
		action_slugs: expect.any( Object ),
		render_slugs: expect.any( Object ),
		operator_modes: expect.any( Array ),
		options: expect.any( Object ),
		option_keys: expect.any( Array ),
		manual_remainder: expect.any( String ),
	} ) );

	for ( const key of contract.option_keys ) {
		expect( contract.options ).toHaveProperty( key );
		expect( contract.options[ key ] ).toEqual( expect.objectContaining( {
			key,
			type: expect.any( String ),
			control_id: `Opt-${key}`,
		} ) );
	}

	return contract;
}

function expectedOptionValues( contract, propertyName ) {
	const values = {};
	for ( const key of contract.option_keys ) {
		values[ key ] = contract.options[ key ][ propertyName ];
	}
	return values;
}

async function openFocusedConfigureForm( page, contract ) {
	await openShieldRoute( page, {
		...contract.routes.configure,
		zone: contract.configure_focus.zone_key,
		row_key: contract.configure_focus.row_key,
		config_item: contract.configure_focus.config_item,
	} );
	await dismissBlockingDialogs( page );

	const row = page.locator( `[data-configure-row-key="${contract.configure_focus.row_key}"]` );
	await expect( row ).toBeVisible( { timeout: 30_000 } );
	const form = row.locator( 'form.options_form_for' ).first();
	await expect( form ).toBeVisible( { timeout: 30_000 } );
	return { row, form };
}

async function expectControlValue( form, option, expectedValue ) {
	const control = form.locator( `#${option.control_id}` );
	await expect( control ).toBeAttached();

	if ( option.type === 'checkbox' ) {
		await expect( control ).toBeChecked( {
			checked: expectedValue === 'Y',
		} );
		return;
	}

	await expect( control ).toHaveValue( String( expectedValue ) );
}

async function applyOptionValue( form, option ) {
	const control = form.locator( `#${option.control_id}` );
	await expect( control ).toBeAttached();

	if ( option.type === 'checkbox' ) {
		await control.setChecked( option.save === 'Y' );
		return;
	}

	if ( option.type === 'select' ) {
		await control.selectOption( String( option.save ) );
		return;
	}

	await control.fill( String( option.save ) );
}

function configureUrlMatches( contract ) {
	return ( url ) => url.searchParams.get( 'nav' ) === contract.routes.configure.nav
		&& url.searchParams.get( 'nav_sub' ) === contract.routes.configure.nav_sub;
}

test( 'global dashboard defaults fixture opens Shield routes and restores cleanup state', async ( { page, fixtureApi } ) => {
	let originalOptions = {};
	await fixtureApi.withDashboardDefaultsFixture( async ( rawContract ) => {
		const contract = assertDashboardDefaultsContract( rawContract );
		originalOptions = contract.original_options;
		const runtimeErrors = collectRuntimeErrors( page );

		await openShieldRoute( page, contract.routes.dashboard );
		await expect( page.locator( '[data-mode-shell="1"][data-mode="dashboard"]' ) ).toBeVisible();
		await expect( page.locator( contract.selectors.dashboard_live_monitor ) ).toBeVisible();

		await openShieldRoute( page, contract.routes.configure );
		await expect( page.locator( contract.selectors.configure_landing ) ).toBeVisible();
		await expectNoRuntimeErrors( runtimeErrors, 'dashboard/defaults global harness' );
	} );

	const inspection = await fixtureApi.inspectDashboardDefaultsFixture();
	expect( inspection.fixture_state_present ).toBe( false );
	expect( inspection.current_options ).toEqual( originalOptions );
} );

test( 'reset defaults restores representative option defaults and reflected controls', async ( { page, fixtureApi } ) => {
	await fixtureApi.withDashboardDefaultsFixture( async ( rawContract ) => {
		const contract = assertDashboardDefaultsContract( rawContract );
		const resetContract = await fixtureApi.resetDashboardDefaultsFixture();
		expect( resetContract.before_reset_options ).toEqual( expectedOptionValues( contract, 'expected' ) );
		expect( resetContract.after_reset_options ).toEqual( expectedOptionValues( contract, 'default' ) );

		const { form } = await openFocusedConfigureForm( page, contract );
		for ( const key of contract.option_keys ) {
			await expectControlValue( form, contract.options[ key ], contract.options[ key ].default );
		}
	} );
} );

test( 'configuration save persists representative defaults-section values through the real options path', async ( { page, fixtureApi } ) => {
	await fixtureApi.withDashboardDefaultsFixture( async ( rawContract ) => {
		const contract = assertDashboardDefaultsContract( rawContract );
		await fixtureApi.resetDashboardDefaultsFixture();

		const { row, form } = await openFocusedConfigureForm( page, contract );
		for ( const key of contract.option_keys ) {
			await applyOptionValue( form, contract.options[ key ] );
		}

		const savedEvent = page.evaluate( () => new Promise( ( resolve ) => {
			document.addEventListener( 'shield:expansion-form-saved', () => resolve( true ), { once: true } );
		} ) );
		const saveResponse = waitForShieldAjaxAction( page, contract.action_slugs.module_options_save );
		await row.locator( '.shield-detail-expansion__btn-save' ).click();
		await expectShieldAjaxSuccess( await saveResponse );
		await savedEvent;

		const inspection = await fixtureApi.inspectDashboardDefaultsFixture();
		expect( inspection.current_options ).toEqual( expectedOptionValues( contract, 'expected' ) );

		await page.reload( { waitUntil: 'domcontentloaded' } );
		await dismissBlockingDialogs( page );
		const reloaded = await openFocusedConfigureForm( page, contract );
		for ( const key of contract.option_keys ) {
			await expectControlValue( reloaded.form, contract.options[ key ], contract.options[ key ].expected );
		}
	} );
} );

test( 'dashboard general checks expose operator modes live monitor ajax and wp dashboard widget contracts', async ( { page, fixtureApi } ) => {
	await fixtureApi.withDashboardDefaultsFixture( async ( rawContract ) => {
		const contract = assertDashboardDefaultsContract( rawContract );
		const observedBatchRenderSlugs = [];
		page.on( 'request', ( request ) => {
			if ( isShieldAjaxBatchRequestWithRenderSlugs(
				request,
				contract.action_slugs.ajax_batch_requests,
				[
					contract.render_slugs.live_monitor_ticker,
					contract.render_slugs.traffic_live_logs,
				]
			) ) {
				observedBatchRenderSlugs.push( requestBatchRenderSlugs( request ) );
			}
		} );

		await page.setViewportSize( { width: 1500, height: 1000 } );
		await openShieldRoute( page, contract.routes.dashboard );
		await dismissBlockingDialogs( page );
		await setDashboardLiveMonitorCollapsed( page, false );
		await page.reload( { waitUntil: 'domcontentloaded' } );
		await dismissBlockingDialogs( page );

		for ( const mode of contract.operator_modes ) {
			await expect( page.locator( `[data-mode="${mode}"]` ).first() ).toBeVisible();
		}

		await Promise.all( [
			page.waitForURL( configureUrlMatches( contract ), { timeout: 20_000 } ),
			page.locator( '[data-mode="configure"]' ).first().click(),
		] );
		await expect( page.locator( contract.selectors.configure_landing ) ).toBeVisible();

		await openShieldRoute( page, contract.routes.dashboard );
		await dismissBlockingDialogs( page );
		const liveMonitor = page.locator( contract.selectors.dashboard_live_monitor );
		await expect( liveMonitor ).toHaveAttribute( 'data-is-collapsed', '0' );
		await expectStatusLiveRegion( liveMonitor.locator( contract.selectors.status_region ) );

		const stateResponse = waitForShieldAjaxAction( page, contract.action_slugs.dashboard_live_monitor_state );
		await page.locator( '[data-live-monitor-toggle="1"]' ).click();
		await expectShieldAjaxSuccess( await stateResponse );
		await expect( liveMonitor ).toHaveAttribute( 'data-is-collapsed', '1' );

		await expect.poll( () => observedBatchRenderSlugs.length ).toBeGreaterThan( 0 );

		const widgetResponse = page.waitForResponse( ( response ) => {
			return isAjaxRenderRequest( response.request(), contract.render_slugs.dashboard_widget );
		} );
		await page.goto( contract.routes.wp_dashboard.path, { waitUntil: 'load' } );
		await expectShieldAjaxSuccess( await widgetResponse );

		const widget = page.locator( contract.selectors.dashboard_widget );
		await expect( widget ).toBeVisible();
		await expect( widget ).not.toHaveAttribute( 'aria-busy', 'true' );
		await expect( widget.locator( '.shield-dashboard-widget[data-shield-status]' ) ).toBeVisible();
	} );
} );
