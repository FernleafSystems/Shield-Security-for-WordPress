const { test, expect } = require( '@playwright/test' );
const AxeBuilder = require( '@axe-core/playwright' ).default;
const {
	openShieldRoute,
	withActionsQueueFixture,
} = require( './support/shield-browser' );
const { ActionsQueuePage } = require( './support/actions-queue-page' );

const activeElementDrillLayer = ( page ) => page.evaluate( () => {
	const active = document.activeElement;
	return active instanceof HTMLElement ? active.dataset.drillLayer || '' : '';
} );

const activeElementDrillLayerKey = ( page ) => page.evaluate( () => {
	const active = document.activeElement;
	return active instanceof HTMLElement ? active.dataset.drillLayerKey || '' : '';
} );

const activeElementDrillTarget = ( page ) => page.evaluate( () => {
	const active = document.activeElement;
	return active instanceof HTMLElement ? active.dataset.drillTarget || '' : '';
} );

const activeElementSelectionKey = ( page, selectionAttr ) => page.evaluate( ( attr ) => {
	const active = document.activeElement;
	if ( !( active instanceof HTMLElement ) ) {
		return '';
	}

	const rawSelection = active.getAttribute( attr ) || '';
	try {
		return String( JSON.parse( rawSelection )?.key || '' ).trim();
	}
	catch {
		return '';
	}
}, selectionAttr );

const drillLiveRegion = ( page, rootSelector ) => page.locator( `${rootSelector} [data-drill-live-region="1"]` ).first();

const liveRegionTextLength = ( page, rootSelector ) => drillLiveRegion( page, rootSelector ).evaluate( ( node ) => {
	return String( node.textContent || '' ).trim().length;
} );

async function expectLiveRegionNonEmpty( page, rootSelector ) {
	await expect.poll( () => liveRegionTextLength( page, rootSelector ) ).toBeGreaterThan( 0 );
}

async function observeLiveRegionMutations( page, rootSelector ) {
	await drillLiveRegion( page, rootSelector ).evaluate( ( node ) => {
		globalThis.__shieldDrillLiveRegionMutationCount = 0;
		globalThis.__shieldDrillLiveRegionObserver?.disconnect?.();
		globalThis.__shieldDrillLiveRegionObserver = new MutationObserver( () => {
			globalThis.__shieldDrillLiveRegionMutationCount++;
		} );
		globalThis.__shieldDrillLiveRegionObserver.observe( node, {
			childList: true,
			characterData: true,
			subtree: true,
		} );
	} );
}

const liveRegionMutationCount = ( page ) => page.evaluate( () => {
	return Number( globalThis.__shieldDrillLiveRegionMutationCount || 0 );
} );

async function callDrillController( shell, methodName, args = [] ) {
	await shell.evaluate( ( shellEl, payload ) => {
		const controller = globalThis.shieldAppMain?.components?.drill_down;
		const method = controller?.[ payload.methodName ];
		if ( typeof method !== 'function' ) {
			throw new Error( `Missing drill-down controller method: ${payload.methodName}` );
		}
		method.call( controller, shellEl, ...payload.args );
	}, {
		methodName,
		args,
	} );
}

async function expectNamedRegion( layer ) {
	await expect( layer ).toHaveAttribute( 'role', 'region' );
	await expect.poll( () => layer.evaluate( ( element ) => {
		const labelId = String( element.getAttribute( 'aria-labelledby' ) || '' ).trim();
		const label = labelId.length > 0
			? element.ownerDocument.getElementById( labelId )
			: null;

		return label instanceof HTMLElement
			&& label.isConnected
			&& String( label.textContent || '' ).trim().length > 0;
	} ) ).toBe( true );
}

async function expectLayerContract( layer, { hidden, busy = false } ) {
	await expectNamedRegion( layer );
	await expect( layer ).toHaveAttribute( 'tabindex', '-1' );
	await expect( layer ).toHaveAttribute( 'aria-hidden', hidden ? 'true' : 'false' );
	await expect( layer ).toHaveAttribute( 'aria-busy', busy ? 'true' : 'false' );
}

async function locatorSelectionKey( locator, selectionAttr ) {
	const rawSelection = await locator.getAttribute( selectionAttr ) || '';
	return String( JSON.parse( rawSelection )?.key || '' ).trim();
}

const ajaxRenderSlug = ( request ) => {
	if ( !request.url().includes( '/admin-ajax.php' ) || request.method() !== 'POST' ) {
		return '';
	}

	const params = new URLSearchParams( request.postData() || '' );
	return params.get( 'render_slug' ) || '';
};

const waitForRenderSlugResponse = ( page, expectedRenderSlug ) => page.waitForResponse(
	( response ) => ajaxRenderSlug( response.request() ) === expectedRenderSlug,
	{ timeout: 20_000 }
);

const createDeferred = () => {
	let resolve;
	const promise = new Promise( ( resolver ) => {
		resolve = resolver;
	} );

	return { promise, resolve };
};

async function deferNextRenderSlug( page, expectedRenderSlug ) {
	const deferred = createDeferred();
	let seen = false;

	await page.route( '**/admin-ajax.php*', async ( route ) => {
		if ( !seen && ajaxRenderSlug( route.request() ) === expectedRenderSlug ) {
			seen = true;
			await deferred.promise;
		}

		await route.continue();
	} );

	return {
		resolve: deferred.resolve,
		seen: () => seen,
	};
}

async function failNextRenderSlug( page, expectedRenderSlug ) {
	let seen = false;

	await page.route( '**/admin-ajax.php*', async ( route ) => {
		if ( !seen && ajaxRenderSlug( route.request() ) === expectedRenderSlug ) {
			seen = true;
			await new Promise( ( resolve ) => setTimeout( resolve, 250 ) );
			await route.fulfill( {
				status: 200,
				contentType: 'application/json',
				body: JSON.stringify( {
					success: false,
					data: {
						message: 'panel-failed',
						page_reload: false,
					},
				} ),
			} );
			return;
		}

		await route.continue();
	} );

	return {
		seen: () => seen,
	};
}

async function expectNoAxeViolations( page, selector, excludes = [] ) {
	let builder = new AxeBuilder( { page } ).include( selector );
	excludes.forEach( ( excludedSelector ) => {
		builder = builder.exclude( excludedSelector );
	} );

	const results = await builder.analyze();

	expect( results.violations, JSON.stringify( results.violations, null, 2 ) ).toEqual( [] );
}

test( 'drill layers expose active state and restore focus to the launcher', async ( { page } ) => {
	await openShieldRoute( page, {
		nav: 'zones',
		nav_sub: 'overview',
	} );

	const rootLayer = page.locator( '[data-configure-landing="1"] [data-drill-layer="0"]' ).first();
	const diagnosisLayer = page.locator( '[data-configure-landing="1"] [data-drill-layer="1"]' ).first();
	const zoneLauncher = page.locator( '[data-configure-landing="1"] [data-drill-target="diagnosis"]' ).first();
	const configureShell = page.locator( '[data-configure-landing="1"] [data-drill-shell="1"]' ).first();

	await expectNamedRegion( rootLayer );
	await expectNamedRegion( diagnosisLayer );
	await expect( rootLayer ).toHaveAttribute( 'aria-hidden', 'false' );
	await expect( diagnosisLayer ).toHaveAttribute( 'aria-hidden', 'true' );

	await zoneLauncher.click();
	await expect( diagnosisLayer ).toHaveAttribute( 'aria-hidden', 'false' );
	await expect( rootLayer ).toHaveAttribute( 'aria-hidden', 'true' );
	await expectLiveRegionNonEmpty( page, '[data-configure-landing="1"]' );
	await observeLiveRegionMutations( page, '[data-configure-landing="1"]' );
	await callDrillController( configureShell, 'drillTo', [ 1 ] );
	await page.waitForTimeout( 80 );
	await expect.poll( () => liveRegionMutationCount( page ) ).toBe( 0 );
	const diagnosisHeader = await diagnosisLayer.evaluate( ( layer ) => JSON.parse( layer.getAttribute( 'data-drill-layer-header' ) || '{}' ) );
	await callDrillController( configureShell, 'updateLayerHeader', [ 1, diagnosisHeader, { announce: 'always' } ] );
	await page.waitForTimeout( 80 );
	await observeLiveRegionMutations( page, '[data-configure-landing="1"]' );
	await callDrillController( configureShell, 'updateLayerHeader', [ 1, diagnosisHeader, { announce: 'always' } ] );
	await page.waitForTimeout( 80 );
	await expect.poll( () => liveRegionMutationCount( page ) ).toBe( 0 );
	await expect.poll(
		async () => ( await page.locator( '[data-mode-shell="1"][data-mode="configure"] [data-operator-step-tabs="1"]' ).first().getAttribute( 'aria-label' ) || '' ).trim().length
	).toBeGreaterThan( 0 );
	await expect( page.locator( '[data-mode-shell="1"][data-mode="configure"] [data-operator-step-tab="1"][aria-current="step"]' ) ).toHaveCount( 1 );
	await expect.poll( () => activeElementDrillLayer( page ) ).toBe( '1' );
	await expectNoAxeViolations( page, '[data-configure-section="drilldown"]' );

	await page.locator( '[data-step-tab-drill-index="0"]' ).click();
	await expect( rootLayer ).toHaveAttribute( 'aria-hidden', 'false' );
	await expect( diagnosisLayer ).toHaveAttribute( 'aria-hidden', 'true' );
	await expect.poll( () => activeElementDrillTarget( page ) ).toBe( 'diagnosis' );
} );

test( 'drill back focuses the active layer when the original launcher is gone', async ( { page } ) => {
	await openShieldRoute( page, {
		nav: 'zones',
		nav_sub: 'overview',
	} );

	const rootLayer = page.locator( '[data-configure-landing="1"] [data-drill-layer="0"]' ).first();
	const diagnosisLayer = page.locator( '[data-configure-landing="1"] [data-drill-layer="1"]' ).first();
	const zoneLauncher = page.locator( '[data-configure-landing="1"] [data-drill-target="diagnosis"]' ).first();

	await zoneLauncher.click();
	await expect( diagnosisLayer ).toHaveAttribute( 'aria-hidden', 'false' );
	await expect.poll( () => activeElementDrillLayer( page ) ).toBe( '1' );
	await zoneLauncher.evaluate( ( element ) => element.remove() );

	await page.locator( '[data-step-tab-drill-index="0"]' ).click();
	await expect( rootLayer ).toHaveAttribute( 'aria-hidden', 'false' );
	await expect( diagnosisLayer ).toHaveAttribute( 'aria-hidden', 'true' );
	await expect.poll( () => activeElementDrillLayer( page ) ).toBe( '0' );
} );

test( 'reports workspace drill keeps named regions, focus, and breadcrumb state', async ( { page } ) => {
	await openShieldRoute( page, {
		nav: 'reports',
		nav_sub: 'overview',
	} );

	const rootLayer = page.locator( '[data-reports-landing="1"] [data-drill-layer-key="workspaces"]' ).first();
	const workspaceLayer = page.locator( '[data-reports-landing="1"] [data-drill-layer-key="workspace"]' ).first();
	const workspaceLauncher = page.locator( '[data-reports-landing="1"] [data-drill-target="workspace"]' ).first();

	await expectNamedRegion( rootLayer );
	await expectNamedRegion( workspaceLayer );
	await expect( rootLayer ).toHaveAttribute( 'aria-hidden', 'false' );
	await expect( workspaceLayer ).toHaveAttribute( 'aria-hidden', 'true' );

	await workspaceLauncher.click();
	await expect( workspaceLayer ).toHaveAttribute( 'aria-hidden', 'false' );
	await expect( rootLayer ).toHaveAttribute( 'aria-hidden', 'true' );
	await expect.poll( () => activeElementDrillLayer( page ) ).toBe( '1' );
	await expect( page.locator( '[data-mode-shell="1"][data-mode="reports"] [data-operator-step-tab="1"][aria-current="step"]' ) ).toHaveCount( 1 );
	await expectLiveRegionNonEmpty( page, '[data-reports-landing="1"]' );
	await expectNoAxeViolations( page, '[data-reports-section="drilldown"]' );
} );

test( 'actions queue drill path keeps layer state, focus, announcements, and axe contract', async ( { page } ) => {
	await withActionsQueueFixture( 'direct_table', async ( fixture ) => {
		const actionsQueuePage = new ActionsQueuePage( page );
		await openShieldRoute( page, {
			nav: 'scans',
			nav_sub: 'overview',
		} );

		const bucketsLayer = page.locator( '[data-actions-landing="1"] [data-drill-layer-key="buckets"]' ).first();
		const groupsLayer = page.locator( '[data-actions-landing="1"] [data-drill-layer-key="groups"]' ).first();
		const detailLayer = page.locator( '[data-actions-landing="1"] [data-drill-layer-key="detail"]' ).first();
		const liveRegion = drillLiveRegion( page, '[data-actions-landing="1"]' );
		const bucket = await actionsQueuePage.waitForBucket( fixture.bucket_key );
		const bucketKey = await locatorSelectionKey( bucket, 'data-drill-bucket-selection' );

		await expectLayerContract( bucketsLayer, { hidden: false } );
		await expectLayerContract( groupsLayer, { hidden: true } );
		await expectLayerContract( detailLayer, { hidden: true } );

		await observeLiveRegionMutations( page, '[data-actions-landing="1"]' );
		await actionsQueuePage.clickElement( bucket );
		await expectLayerContract( bucketsLayer, { hidden: true } );
		await expect( groupsLayer ).toHaveAttribute( 'aria-hidden', 'false' );
		await expect.poll( () => activeElementDrillLayerKey( page ) ).toBe( 'groups' );
		await expect.poll( () => liveRegionMutationCount( page ) ).toBeGreaterThan( 0 );
		await expectLiveRegionNonEmpty( page, '[data-actions-landing="1"]' );
		await expect( liveRegion ).toHaveAttribute( 'aria-live', 'polite' );
		await expectNoAxeViolations(
			page,
			'[data-actions-queue-section="drilldown"]',
			[ '[data-actions-queue-section="drilldown"] .drill-layer__body' ]
		);

		const group = await actionsQueuePage.waitForGroup( fixture.group_key );
		expect( group ).not.toBeNull();
		const groupKey = await locatorSelectionKey( group, 'data-drill-group-selection' );

		await observeLiveRegionMutations( page, '[data-actions-landing="1"]' );
		await actionsQueuePage.clickElement( group );
		await expect( detailLayer ).toHaveAttribute( 'aria-hidden', 'false' );
		await expect( bucketsLayer ).toHaveAttribute( 'aria-hidden', 'true' );
		await expect( groupsLayer ).toHaveAttribute( 'aria-hidden', 'true' );
		await expect.poll( () => activeElementDrillLayerKey( page ) ).toBe( 'detail' );
		await expect.poll( () => liveRegionMutationCount( page ) ).toBeGreaterThan( 0 );
		await expect( page.locator( '[data-actions-queue-detail="1"]' ) ).toBeVisible();
		await expect( page.locator( '[data-actions-queue-detail="1"] [data-scan-results-table="1"]' ).first() ).toBeVisible();
		await expect( page.locator( '[data-mode-shell="1"][data-mode="actions"] [data-operator-step-tab="1"][aria-current="step"]' ) ).toHaveCount( 1 );

		await page.locator( '[data-step-tab-drill-index="1"]' ).click();
		await expect( groupsLayer ).toHaveAttribute( 'aria-hidden', 'false' );
		await expect( detailLayer ).toHaveAttribute( 'aria-hidden', 'true' );
		await expect.poll(
			() => activeElementSelectionKey( page, 'data-drill-group-selection' )
		).toBe( groupKey );

		await page.locator( '[data-step-tab-drill-index="0"]' ).click();
		await expect( bucketsLayer ).toHaveAttribute( 'aria-hidden', 'false' );
		await expect( groupsLayer ).toHaveAttribute( 'aria-hidden', 'true' );
		await expect( detailLayer ).toHaveAttribute( 'aria-hidden', 'true' );
		await expect.poll(
			() => activeElementSelectionKey( page, 'data-drill-bucket-selection' )
		).toBe( bucketKey );
	} );
} );

test( 'actions queue drill back focuses the active layer when a group launcher is gone', async ( { page } ) => {
	await withActionsQueueFixture( 'direct_table', async ( fixture ) => {
		const actionsQueuePage = new ActionsQueuePage( page );
		await openShieldRoute( page, {
			nav: 'scans',
			nav_sub: 'overview',
		} );

		const groupsLayer = page.locator( '[data-actions-landing="1"] [data-drill-layer-key="groups"]' ).first();
		const detailLayer = page.locator( '[data-actions-landing="1"] [data-drill-layer-key="detail"]' ).first();
		const bucket = await actionsQueuePage.waitForBucket( fixture.bucket_key );

		await actionsQueuePage.clickElement( bucket );
		await expect( page.locator( '[data-actions-queue-groups="1"]' ) ).toBeVisible();

		const group = await actionsQueuePage.waitForGroup( fixture.group_key );
		expect( group ).not.toBeNull();
		await actionsQueuePage.clickElement( group );
		await expect( detailLayer ).toHaveAttribute( 'aria-hidden', 'false' );
		await expect.poll( () => activeElementDrillLayerKey( page ) ).toBe( 'detail' );

		await group.evaluate( ( element ) => element.remove() );
		await page.locator( '[data-step-tab-drill-index="1"]' ).click();
		await expect( groupsLayer ).toHaveAttribute( 'aria-hidden', 'false' );
		await expect( detailLayer ).toHaveAttribute( 'aria-hidden', 'true' );
		await expect.poll( () => activeElementDrillLayerKey( page ) ).toBe( 'groups' );
	} );
} );

test( 'actions queue clears groups layer busy state without stale announcement when request is cancelled', async ( { page } ) => {
	await withActionsQueueFixture( 'direct_table', async ( fixture ) => {
		const actionsQueuePage = new ActionsQueuePage( page );
		await openShieldRoute( page, {
			nav: 'scans',
			nav_sub: 'overview',
		} );

		const groupsLayer = page.locator( '[data-actions-landing="1"] [data-drill-layer-key="groups"]' ).first();
		const bucket = await actionsQueuePage.waitForBucket( fixture.bucket_key );
		const delayedGroups = await deferNextRenderSlug( page, 'actions_queue_drill_down_groups' );
		const groupsResponse = waitForRenderSlugResponse( page, 'actions_queue_drill_down_groups' );

		await actionsQueuePage.clickElement( bucket );
		await expect.poll( delayedGroups.seen ).toBe( true );
		await expect( groupsLayer ).toHaveAttribute( 'aria-busy', 'true' );
		await expectLiveRegionNonEmpty( page, '[data-actions-landing="1"]' );
		await page.locator( '[data-step-tab-drill-index="0"]' ).click();
		await expect( groupsLayer ).toHaveAttribute( 'aria-hidden', 'true' );
		await expect( groupsLayer ).toHaveAttribute( 'aria-busy', 'false' );
		await page.waitForTimeout( 80 );
		await observeLiveRegionMutations( page, '[data-actions-landing="1"]' );

		delayedGroups.resolve();
		await groupsResponse;
		await page.waitForTimeout( 120 );
		await expect( groupsLayer ).toHaveAttribute( 'aria-busy', 'false' );
		await expect.poll( () => liveRegionMutationCount( page ) ).toBe( 0 );
	} );
} );

test( 'actions queue clears detail layer busy state without stale announcement when request is cancelled', async ( { page } ) => {
	await withActionsQueueFixture( 'direct_table', async ( fixture ) => {
		const actionsQueuePage = new ActionsQueuePage( page );
		await openShieldRoute( page, {
			nav: 'scans',
			nav_sub: 'overview',
		} );

		const detailLayer = page.locator( '[data-actions-landing="1"] [data-drill-layer-key="detail"]' ).first();
		const bucket = await actionsQueuePage.waitForBucket( fixture.bucket_key );

		await actionsQueuePage.clickElement( bucket );
		await expect( page.locator( '[data-actions-queue-groups="1"]' ) ).toBeVisible();

		const group = await actionsQueuePage.waitForGroup( fixture.group_key );
		expect( group ).not.toBeNull();

		const groupSelection = await group.evaluate( ( element ) => JSON.parse( element.getAttribute( 'data-drill-group-selection' ) || '{}' ) );
		const detailRenderSlug = String( groupSelection?.detail_render_action?.render_slug || '' ).trim();
		expect( detailRenderSlug.length ).toBeGreaterThan( 0 );

		const delayedDetail = await deferNextRenderSlug( page, detailRenderSlug );
		const detailResponse = waitForRenderSlugResponse( page, detailRenderSlug );
		await actionsQueuePage.clickElement( group );
		await expect.poll( delayedDetail.seen ).toBe( true );
		await expect( detailLayer ).toHaveAttribute( 'aria-busy', 'true' );
		await expectLiveRegionNonEmpty( page, '[data-actions-landing="1"]' );
		await page.locator( '[data-step-tab-drill-index="1"]' ).click();
		await expect( detailLayer ).toHaveAttribute( 'aria-hidden', 'true' );
		await expect( detailLayer ).toHaveAttribute( 'aria-busy', 'false' );
		await page.waitForTimeout( 80 );
		await observeLiveRegionMutations( page, '[data-actions-landing="1"]' );

		delayedDetail.resolve();
		await detailResponse;
		await page.waitForTimeout( 120 );
		await expect( detailLayer ).toHaveAttribute( 'aria-busy', 'false' );
		await expect.poll( () => liveRegionMutationCount( page ) ).toBe( 0 );
	} );
} );

test( 'actions queue detail failure clears busy state and announces assertively', async ( { page } ) => {
	await withActionsQueueFixture( 'direct_table', async ( fixture ) => {
		const actionsQueuePage = new ActionsQueuePage( page );
		await openShieldRoute( page, {
			nav: 'scans',
			nav_sub: 'overview',
		} );

		const groupsLayer = page.locator( '[data-actions-landing="1"] [data-drill-layer-key="groups"]' ).first();
		const detailLayer = page.locator( '[data-actions-landing="1"] [data-drill-layer-key="detail"]' ).first();
		const liveRegion = drillLiveRegion( page, '[data-actions-landing="1"]' );
		const bucket = await actionsQueuePage.waitForBucket( fixture.bucket_key );

		await actionsQueuePage.clickElement( bucket );
		await expect( page.locator( '[data-actions-queue-groups="1"]' ) ).toBeVisible();

		const group = await actionsQueuePage.waitForGroup( fixture.group_key );
		expect( group ).not.toBeNull();
		const groupKey = await locatorSelectionKey( group, 'data-drill-group-selection' );
		const groupSelection = await group.evaluate( ( element ) => JSON.parse( element.getAttribute( 'data-drill-group-selection' ) || '{}' ) );
		const detailRenderSlug = String( groupSelection?.detail_render_action?.render_slug || '' ).trim();
		expect( detailRenderSlug.length ).toBeGreaterThan( 0 );

		const failedDetail = await failNextRenderSlug( page, detailRenderSlug );
		await actionsQueuePage.clickElement( group );
		await expect( detailLayer ).toHaveAttribute( 'aria-hidden', 'false' );
		await expect( detailLayer ).toHaveAttribute( 'aria-busy', 'true' );
		await expect.poll( () => activeElementDrillLayerKey( page ) ).toBe( 'detail' );
		await expect.poll( failedDetail.seen ).toBe( true );
		await expect( detailLayer ).toHaveAttribute( 'aria-busy', 'false' );
		await expect( liveRegion ).toHaveAttribute( 'aria-live', 'assertive' );
		await expectLiveRegionNonEmpty( page, '[data-actions-landing="1"]' );
		await expect.poll( () => activeElementDrillLayerKey( page ) ).toBe( 'detail' );

		await page.locator( '[data-step-tab-drill-index="1"]' ).click();
		await expect( groupsLayer ).toHaveAttribute( 'aria-hidden', 'false' );
		await expect( detailLayer ).toHaveAttribute( 'aria-hidden', 'true' );
		await expect( liveRegion ).toHaveAttribute( 'aria-live', 'polite' );
		await expect.poll(
			() => activeElementSelectionKey( page, 'data-drill-group-selection' )
		).toBe( groupKey );
	} );
} );

test( 'investigate panel clears busy and loaded state when request is cancelled', async ( { page } ) => {
	await openShieldRoute( page, {
		nav: 'activity',
		nav_sub: 'overview',
	} );

	const panelLayer = page.locator( '[data-investigate-landing="1"] [data-drill-layer-key="panel"]' ).first();
	const panel = page.locator( '[data-investigate-panel="1"]' ).first();
	const panelContent = page.locator( '[data-investigate-panel-content="1"]' ).first();
	const liveSubject = page.locator( '[data-drill-target="panel"][data-investigate-subject="live_traffic"]' ).first();
	const renderAction = await liveSubject.evaluate( ( element ) => JSON.parse( element.getAttribute( 'data-investigate-render-action' ) || '{}' ) );
	const panelRenderSlug = String( renderAction?.render_slug || '' ).trim();
	expect( panelRenderSlug.length ).toBeGreaterThan( 0 );

	const delayedPanel = await deferNextRenderSlug( page, panelRenderSlug );
	await liveSubject.click();
	await expect( panelLayer ).toHaveAttribute( 'aria-hidden', 'false' );
	await expect( panelContent ).toHaveAttribute( 'aria-busy', 'true' );
	await page.locator( '[data-step-tab-drill-index="0"]' ).click();
	await expect( panelLayer ).toHaveAttribute( 'aria-hidden', 'true' );
	await expect( panelContent ).toHaveAttribute( 'aria-busy', 'false' );
	await expect( panel ).toHaveAttribute( 'data-investigate-panel-loaded', '0' );

	delayedPanel.resolve();
	await expect.poll( delayedPanel.seen ).toBe( true );
	await expect( panelContent ).toHaveAttribute( 'aria-busy', 'false' );
	await expect( panel ).toHaveAttribute( 'data-investigate-panel-loaded', '0' );
} );

test( 'investigate panel failure clears busy state and announces assertively', async ( { page } ) => {
	await openShieldRoute( page, {
		nav: 'activity',
		nav_sub: 'overview',
	} );

	const panelLayer = page.locator( '[data-investigate-landing="1"] [data-drill-layer-key="panel"]' ).first();
	const panel = page.locator( '[data-investigate-panel="1"]' ).first();
	const panelContent = page.locator( '[data-investigate-panel-content="1"]' ).first();
	const liveRegion = drillLiveRegion( page, '[data-investigate-landing="1"]' );
	const liveSubject = page.locator( '[data-drill-target="panel"][data-investigate-subject="live_traffic"]' ).first();
	const renderAction = await liveSubject.evaluate( ( element ) => JSON.parse( element.getAttribute( 'data-investigate-render-action' ) || '{}' ) );
	const panelRenderSlug = String( renderAction?.render_slug || '' ).trim();
	expect( panelRenderSlug.length ).toBeGreaterThan( 0 );

	const failedPanel = await failNextRenderSlug( page, panelRenderSlug );
	await liveSubject.click();
	await expect( panelLayer ).toHaveAttribute( 'aria-hidden', 'false' );
	await expect( panelContent ).toHaveAttribute( 'aria-busy', 'true' );
	await expect.poll( () => activeElementDrillLayer( page ) ).toBe( '1' );
	await expect.poll( failedPanel.seen ).toBe( true );
	await expect( panelContent ).toHaveAttribute( 'aria-busy', 'false' );
	await expect( panel ).toHaveAttribute( 'data-investigate-panel-loaded', '0' );
	await expect( liveRegion ).toHaveAttribute( 'aria-live', 'assertive' );
	await expectLiveRegionNonEmpty( page, '[data-investigate-landing="1"]' );
	await expect.poll( () => activeElementDrillLayer( page ) ).toBe( '1' );
	await page.locator( '[data-step-tab-drill-index="0"]' ).click();
	await expect( panelLayer ).toHaveAttribute( 'aria-hidden', 'true' );
	await expect( liveRegion ).toHaveAttribute( 'aria-live', 'polite' );
	await expect.poll( () => activeElementDrillTarget( page ) ).toBe( 'panel' );
} );
