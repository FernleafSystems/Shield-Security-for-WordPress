const { AxeBuilder, expect } = require( './shield-test' );
const {
	expectConnectedNonEmptyReference,
	expectReferenceTargetNonEmpty,
} = require( './modal-accessibility' );

function collectRuntimeErrors( page, { consoleErrors = false } = {} ) {
	const errors = [];
	page.on( 'pageerror', ( error ) => {
		errors.push( error.message );
	} );
	if ( consoleErrors ) {
		page.on( 'console', ( message ) => {
			if ( message.type() === 'error' ) {
				errors.push( message.text() );
			}
		} );
	}
	return errors;
}

async function expectNoRuntimeErrors( errors, label ) {
	await expect.poll(
		() => errors.slice(),
		{ message: `${label}: ${errors.join( '; ' )}` }
	).toEqual( [] );
}

function requestActionSlug( request ) {
	return requestPostParam( request, 'ex' );
}

function requestPostParams( request ) {
	const postData = request.postData();
	if ( typeof postData !== 'string' ) {
		return new URLSearchParams();
	}

	return new URLSearchParams( postData );
}

function requestPostParam( request, name ) {
	return requestPostParams( request ).get( name );
}

function isAdminAjaxRequest( request ) {
	if ( request.method() !== 'POST' ) {
		return false;
	}

	try {
		return new URL( request.url() ).pathname.endsWith( '/admin-ajax.php' );
	}
	catch ( error ) {
		return false;
	}
}

function waitForShieldAjaxAction( page, actionSlug ) {
	return page.waitForResponse( ( response ) => {
		const request = response.request();
		return isAdminAjaxRequest( request )
			&& requestActionSlug( request ) === actionSlug;
	} );
}

function requestBatchRenderSlugs( request ) {
	const renderSlugs = [];
	for ( const [ key, value ] of requestPostParams( request ).entries() ) {
		if ( /^requests\[\d+\]\[request\]\[render_slug\]$/.test( key ) ) {
			renderSlugs.push( value );
		}
	}
	return renderSlugs;
}

function isShieldAjaxBatchRequestWithRenderSlugs( request, actionSlug, renderSlugs ) {
	if ( !isAdminAjaxRequest( request ) || requestActionSlug( request ) !== actionSlug ) {
		return false;
	}
	const actualRenderSlugs = requestBatchRenderSlugs( request );
	return renderSlugs.every( ( renderSlug ) => actualRenderSlugs.includes( renderSlug ) );
}

function isAjaxRenderRequest( request, renderSlug ) {
	return isAdminAjaxRequest( request )
		&& requestActionSlug( request ) === 'ajax_render'
		&& requestPostParam( request, 'render_slug' ) === renderSlug;
}

function parseShieldAjaxJson( rawPayload ) {
	const openJsonTag = '##APTO_OPEN##';
	const closeJsonTag = '##APTO_CLOSE##';
	if ( rawPayload.includes( openJsonTag ) ) {
		const start = rawPayload.indexOf( openJsonTag ) + openJsonTag.length;
		const end = rawPayload.lastIndexOf( closeJsonTag );
		if ( end <= start ) {
			throw new Error( 'Malformed wrapped Shield AJAX response.' );
		}
		return JSON.parse( rawPayload.substring( start, end ) );
	}

	return JSON.parse( rawPayload );
}

async function setDashboardLiveMonitorCollapsed( page, isCollapsed ) {
	const result = await page.evaluate( async ( nextCollapsed ) => {
		const requestData = window.shield_vars_main?.comps?.dashboard_live_monitor?.ajax?.set_state;
		if ( !requestData || typeof requestData.ajaxurl !== 'string' || requestData.ajaxurl.length < 1 ) {
			throw new Error( 'Missing dashboard live monitor set_state AJAX payload.' );
		}

		const response = await fetch( requestData.ajaxurl, {
			method: 'POST',
			credentials: 'same-origin',
			headers: {
				'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8',
			},
			body: new URLSearchParams( {
				...requestData,
				is_collapsed: nextCollapsed ? '1' : '0',
			} ),
		} );

		return {
			ok: response.ok,
			payload: await response.json(),
		};
	}, isCollapsed );

	expect( result.ok ).toBeTruthy();
	expect( result.payload ).toHaveProperty( 'success', true );
	return result.payload;
}

async function expectShieldAjaxSuccess( response ) {
	expect( response.ok() ).toBeTruthy();
	const payload = parseShieldAjaxJson( await response.text() );
	expect( payload ).toHaveProperty( 'success', true );
	return payload;
}

function collectShieldAjaxActionUrls( page, actionSlug ) {
	const urls = [];
	page.on( 'response', ( response ) => {
		const request = response.request();
		if ( isAdminAjaxRequest( request ) && requestActionSlug( request ) === actionSlug ) {
			urls.push( response.url() );
		}
	} );
	return urls;
}

const investigationTableResponseMatcher = ( tableType ) => ( response ) => {
	const request = response.request();

	return isAdminAjaxRequest( request )
		&& requestPostParam( request, 'sub_action' ) === 'retrieve_table_data'
		&& requestPostParam( request, 'table_type' ) === tableType;
};

const requestMetaResponseMatcher = ( rid ) => ( response ) => {
	const request = response.request();

	return isAdminAjaxRequest( request )
		&& requestPostParam( request, 'sub_action' ) === 'get_request_meta'
		&& requestPostParam( request, 'rid' ) === rid;
};

async function expectInvestigationTableInitialized( root, tableType ) {
	const table = root.locator( `table[data-investigation-table="1"][data-table-type="${tableType}"]` ).first();
	await expect( table ).toBeVisible();
	await expect.poll(
		async () => table.evaluate( ( el ) => {
			return !!globalThis.jQuery?.fn?.dataTable?.isDataTable?.( el );
		} ),
		{
			message: `Expected ${tableType} investigation table to be initialized by DataTables.`,
		}
	).toBe( true );
}

async function expectRequestMetaPopover( page, root, rid, expectedMeta ) {
	const metaButton = root.locator( 'td.meta > button[data-toggle="popover"]' ).first();
	await expect( metaButton ).toBeVisible();

	await Promise.all( [
		page.waitForResponse( requestMetaResponseMatcher( rid ) ),
		metaButton.click(),
	] );

	const popover = page.locator( '[role="tooltip"]' ).last();
	await expect( popover ).toBeVisible();

	for ( const marker of expectedMeta ) {
		await expect( popover ).toContainText( marker );
	}
}

async function openPublicBlockPage( page, url ) {
	await page.goto( url, { waitUntil: 'load' } );
	await expect( page.locator( 'body' ) ).toBeVisible();
}

async function openBlockRecoveryModal( page, ids ) {
	const launcher = page.locator( `#${ids.launcher}` );
	await expect( launcher ).toBeVisible();
	await expect( launcher ).toHaveAttribute( 'data-bs-target', `#${ids.dialog}` );
	await launcher.click();

	const modal = page.locator( `#${ids.dialog}` );
	await expect( modal ).toBeVisible();
	await expect( modal ).toHaveAttribute( 'role', 'dialog' );
	await expectConnectedNonEmptyReference( page, modal, 'aria-labelledby' );
	await expectConnectedNonEmptyReference( page, modal, 'aria-describedby' );

	const dismiss = modal.locator( 'button[data-bs-dismiss="modal"]' );
	await expect( dismiss ).toHaveCount( 1 );
	expect( ( await dismiss.getAttribute( 'aria-label' ) || '' ).trim().length ).toBeGreaterThan( 0 );
	return modal;
}

async function expectNoAxeViolationsInDialog( page, ids ) {
	const results = await new AxeBuilder( { page } )
	.include( `#${ids.dialog}` )
	.analyze();

	expect( results.violations, JSON.stringify( results.violations, null, 2 ) ).toEqual( [] );
}

async function expectAutoRecoverControls( page, ids ) {
	const checkbox = page.locator( `#${ids.confirm}` );
	const label = page.locator( `label[for="${ids.confirm}"]` );
	const submit = page.locator( `#${ids.submit}` );

	await expect( checkbox ).toHaveAttribute( 'name', '_confirm' );
	await expect( label ).toHaveCount( 1 );
	expect( await label.evaluate( ( node ) => {
		return node.isConnected && ( node.textContent || '' ).trim().length > 0;
	} ) ).toBe( true );
	await expectConnectedNonEmptyReference( page, submit, 'aria-describedby' );
	await expect( submit ).toBeDisabled();
	await checkbox.check();
	await expect( submit ).toBeEnabled();
}

async function expectStatusLiveRegion( status ) {
	await expect( status ).toHaveAttribute( 'role', 'status' );
	await expect( status ).toHaveAttribute( 'aria-live', 'polite' );
	await expect( status ).toHaveAttribute( 'aria-atomic', 'true' );
}

async function expectResponseHeaders( response, expectedHeaders ) {
	expect( response ).not.toBeNull();
	const headers = response.headers();
	for ( const [ name, value ] of Object.entries( expectedHeaders ) ) {
		const normalizedName = name.toLowerCase();
		expect( headers ).toHaveProperty( normalizedName );
		expect( headers[ normalizedName ] ).toBe( value );
	}
}

module.exports = {
	collectRuntimeErrors,
	collectShieldAjaxActionUrls,
	expectAutoRecoverControls,
	expectConnectedNonEmptyReference,
	expectInvestigationTableInitialized,
	expectNoAxeViolationsInDialog,
	expectNoRuntimeErrors,
	expectReferenceTargetNonEmpty,
	expectRequestMetaPopover,
	expectResponseHeaders,
	expectShieldAjaxSuccess,
	expectStatusLiveRegion,
	investigationTableResponseMatcher,
	isAjaxRenderRequest,
	isAdminAjaxRequest,
	isShieldAjaxBatchRequestWithRenderSlugs,
	openBlockRecoveryModal,
	openPublicBlockPage,
	parseShieldAjaxJson,
	requestActionSlug,
	requestBatchRenderSlugs,
	requestMetaResponseMatcher,
	requestPostParam,
	requestPostParams,
	setDashboardLiveMonitorCollapsed,
	waitForShieldAjaxAction,
};
