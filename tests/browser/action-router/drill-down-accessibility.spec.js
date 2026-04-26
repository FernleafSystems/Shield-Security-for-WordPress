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

const activeElementDrillTarget = ( page ) => page.evaluate( () => {
	const active = document.activeElement;
	return active instanceof HTMLElement ? active.dataset.drillTarget || '' : '';
} );

const ajaxRenderSlug = ( request ) => {
	if ( !request.url().includes( '/admin-ajax.php' ) || request.method() !== 'POST' ) {
		return '';
	}

	const params = new URLSearchParams( request.postData() || '' );
	return params.get( 'render_slug' ) || '';
};

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

async function expectNoAxeViolations( page, selector ) {
	const results = await new AxeBuilder( { page } )
		.include( selector )
		.analyze();

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

	await expect( rootLayer ).toHaveAttribute( 'role', 'region' );
	await expect( rootLayer ).toHaveAttribute( 'aria-hidden', 'false' );
	await expect( diagnosisLayer ).toHaveAttribute( 'aria-hidden', 'true' );
	await expect.poll(
		async () => ( await diagnosisLayer.getAttribute( 'aria-labelledby' ) || '' ).trim().length
	).toBeGreaterThan( 0 );

	await zoneLauncher.click();
	await expect( diagnosisLayer ).toHaveAttribute( 'aria-hidden', 'false' );
	await expect( rootLayer ).toHaveAttribute( 'aria-hidden', 'true' );
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

test( 'actions queue clears groups layer busy state when request is cancelled', async ( { page } ) => {
	await withActionsQueueFixture( 'direct_table', async ( fixture ) => {
		const actionsQueuePage = new ActionsQueuePage( page );
		await openShieldRoute( page, {
			nav: 'scans',
			nav_sub: 'overview',
		} );

		const groupsLayer = page.locator( '[data-actions-landing="1"] [data-drill-layer-key="groups"]' ).first();
		const bucket = await actionsQueuePage.waitForBucket( fixture.bucket_key );
		const delayedGroups = await deferNextRenderSlug( page, 'actions_queue_drill_down_groups' );

		await actionsQueuePage.clickElement( bucket );
		await expect( groupsLayer ).toHaveAttribute( 'aria-busy', 'true' );
		await page.locator( '[data-step-tab-drill-index="0"]' ).click();
		await expect( groupsLayer ).toHaveAttribute( 'aria-hidden', 'true' );
		await expect( groupsLayer ).toHaveAttribute( 'aria-busy', 'false' );

		delayedGroups.resolve();
		await expect.poll( delayedGroups.seen ).toBe( true );
		await expect( groupsLayer ).toHaveAttribute( 'aria-busy', 'false' );
	} );
} );

test( 'actions queue clears detail layer busy state when request is cancelled', async ( { page } ) => {
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

		const group = await actionsQueuePage.waitForGroupWithRetry( bucket, fixture.group_key );
		expect( group ).not.toBeNull();

		const groupSelection = await group.evaluate( ( element ) => JSON.parse( element.getAttribute( 'data-drill-group-selection' ) || '{}' ) );
		const detailRenderSlug = String( groupSelection?.detail_render_action?.render_slug || '' ).trim();
		expect( detailRenderSlug.length ).toBeGreaterThan( 0 );

		const delayedDetail = await deferNextRenderSlug( page, detailRenderSlug );
		await actionsQueuePage.clickElement( group );
		await expect( detailLayer ).toHaveAttribute( 'aria-busy', 'true' );
		await page.locator( '[data-step-tab-drill-index="1"]' ).click();
		await expect( detailLayer ).toHaveAttribute( 'aria-hidden', 'true' );
		await expect( detailLayer ).toHaveAttribute( 'aria-busy', 'false' );

		delayedDetail.resolve();
		await expect.poll( delayedDetail.seen ).toBe( true );
		await expect( detailLayer ).toHaveAttribute( 'aria-busy', 'false' );
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
